<?php

use App\Models\PodOperation;
use App\Models\PodProduit;
use App\Models\Role;
use App\Models\User;
use App\Support\ModuleAccess;
use App\Services\Integrations\FlexcubeQueryRunner;
use Database\Seeders\PodFraisDemandeCreditSeeder;

function agentPod(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'pod'], ['nom' => 'Produits divers', 'module' => 'pod', 'actif' => true]);
    $role->syncModuleKeys(['pod']);
    ModuleAccess::clearModuleRolesCache();
    $user = User::factory()->create();
    $user->roles()->attach($role->id);

    return $user->fresh();
}

beforeEach(function () {
    $this->seed(PodFraisDemandeCreditSeeder::class);
    $this->produit = PodProduit::query()->where('code', 'FDC')->firstOrFail();

    $this->mock(FlexcubeQueryRunner::class, function ($mock) {
        $mock->shouldReceive('select')->andReturnUsing(function (string $sql, array $binds) {
            if (($binds['NUMERO_COMPTE'] ?? null) !== '251541568001') {
                return [];
            }

            return [[
                'CODE_AGENCE' => '528',
                'AGENCE' => 'COFINA EXPRESS SAINT-LOUIS',
                'NO_COMPTE' => '251541568001',
                'COMPTE' => 'WADE FATOU',
                'MATRICULE' => '541568',
                'SOLDE_NET' => 0,
                'ENCOURS_GLOBAL_DUE' => 0,
            ]];
        });
    });
});

test('la requête Flexcube est limitée à un SELECT en lecture seule', function () {
    expect(FlexcubeQueryRunner::assertLectureSeule("with a as (select 1 x from dual) select * from a where b = :NUMERO_COMPTE"))
        ->toContain('select')
        ->and(FlexcubeQueryRunner::bindNames("select 'a:b' from t where x = :NUMERO_COMPTE and y = :Autre"))
        ->toBe(['NUMERO_COMPTE', 'AUTRE']);

    expect(fn () => FlexcubeQueryRunner::assertLectureSeule('delete from comptes'))->toThrow(RuntimeException::class);
    expect(fn () => FlexcubeQueryRunner::assertLectureSeule('select 1 from dual; drop table x'))->toThrow(RuntimeException::class);
    expect(fn () => FlexcubeQueryRunner::assertLectureSeule('select * from t for update'))->toThrow(RuntimeException::class);
});

test('la recherche renvoie les informations du compte', function () {
    $this->actingAs(agentPod())
        ->postJson('/pod/operations/recherche', [
            'pod_produit_id' => $this->produit->id,
            'champ' => 'NUMERO_COMPTE',
            'champs_saisis' => ['NUMERO_COMPTE' => '251541568001'],
        ])
        ->assertOk()
        ->assertJsonPath('valeurs.COMPTE', 'WADE FATOU')
        ->assertJsonPath('systeme.code_agence', '528');
});

test('un compte inconnu est signalé', function () {
    $this->actingAs(agentPod())
        ->postJson('/pod/operations/recherche', [
            'pod_produit_id' => $this->produit->id,
            'champ' => 'NUMERO_COMPTE',
            'champs_saisis' => ['NUMERO_COMPTE' => '251000000000'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', fn ($m) => str_contains($m, 'n’existe pas dans Flexcube'));
});

test('l’enregistrement reprend les valeurs de Flexcube, pas celles du navigateur', function () {
    $this->actingAs(agentPod())->post('/pod/operations', [
        'pod_produit_id' => $this->produit->id,
        'montant_demande' => 3000000,
        'champs_saisis' => [
            'NUMERO_COMPTE' => '251541568001',
            'COMPTE' => 'VALEUR TRAFIQUEE',
            'SOLDE_NET' => 999999,
            'TYPE_CREDIT' => 'PRET',
        ],
    ])->assertSessionHasNoErrors();

    $operation = PodOperation::query()->latest('id')->firstOrFail();
    expect((float) $operation->calcul_detail['solde_apres_frais'])->toBe(-5000.0)
        ->and((float) $operation->frais_ht)->toBe(5000.0)
        ->and((float) $operation->montant_taf)->toBe(0.0)
        ->and($operation->champs_saisis['COMPTE'])->toBe('WADE FATOU')
        ->and($operation->champs_saisis['SOLDE_NET'])->toBe(0)
        ->and($operation->compte_client)->toBe('251541568001')
        ->and($operation->code_agence)->toBe('528')
        ->and($operation->nom_client)->toBe('WADE FATOU');
});

test('les frais suivent le barème du type de crédit, sans taxe', function (string $type, int $montant, float $frais) {
    $this->actingAs(agentPod())
        ->postJson('/pod/operations/preview', [
            'pod_produit_id' => $this->produit->id,
            'compte_client' => '251541568001',
            'montant_demande' => $montant,
            'champs_saisis' => ['TYPE_CREDIT' => $type],
        ])
        ->assertOk()
        ->assertJsonPath('frais_ht', fn ($v) => (float) $v === $frais)
        ->assertJsonPath('montant_taf', fn ($v) => (float) $v === 0.0);
})->with([
    'prêt 3 M' => ['PRET', 3000000, 5000.0],
    'prêt 30 M' => ['PRET', 30000000, 30000.0],
    'prêt 60 M' => ['PRET', 60000000, 50000.0],
    'Cofi Express 500 001' => ['COFI_EXPRESS', 500001, 10000.0],
    'Cofi Express 2 M' => ['COFI_EXPRESS', 2000000, 15000.0],
]);

test('un montant sous le barème est refusé', function () {
    $this->actingAs(agentPod())
        ->postJson('/pod/operations/preview', [
            'pod_produit_id' => $this->produit->id,
            'compte_client' => '251541568001',
            'montant_demande' => 50000,
            'champs_saisis' => ['TYPE_CREDIT' => 'PRET'],
        ])
        ->assertStatus(422);
});

test('l’enregistrement est refusé si le compte est introuvable', function () {
    $this->actingAs(agentPod())->post('/pod/operations', [
        'pod_produit_id' => $this->produit->id,
        'montant_demande' => 3000000,
        'champs_saisis' => ['NUMERO_COMPTE' => '251000000000', 'TYPE_CREDIT' => 'PRET'],
    ])->assertSessionHasErrors('champs_saisis.NUMERO_COMPTE');

    expect(PodOperation::count())->toBe(0);
});
