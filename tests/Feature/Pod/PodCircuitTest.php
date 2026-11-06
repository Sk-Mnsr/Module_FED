<?php

use App\Models\PodOperation;
use App\Models\PodProduit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PodPepDepotSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function podUser(string $roleSlug): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $roleSlug)->value('id'));

    return $user->fresh();
}

function soumettrePep(User $agence): PodOperation
{
    $produit = PodProduit::query()->where('code', 'PEP-DEP')->firstOrFail();

    test()->actingAs($agence)->post('/pod/operations', [
        'pod_produit_id' => $produit->id,
        'champs_saisis' => [
            'NUMERO_PRET' => 'PR-001',
            'TYPE_DEMANDE' => 'LIB_PEP',
            'MOTIF_DEMANDE' => 'CLOTURE',
        ],
        'fichiers' => ['PIECES_DEMANDE' => [UploadedFile::fake()->create('demande.pdf', 20, 'application/pdf')]],
    ])->assertSessionHasNoErrors();

    return PodOperation::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(PodPepDepotSeeder::class);
});

test('la soumission crée le dossier à l’étape Crédit / Risque avec la pièce jointe', function () {
    $operation = soumettrePep(podUser('pep_agence'));

    expect($operation->statut)->toBe(PodOperation::STATUT_EN_COURS)
        ->and($operation->etape_code)->toBe('ECR02')
        ->and($operation->champs_saisis['NUMERO_PRET'])->toBe('PR-001')
        ->and($operation->pieces()->count())->toBe(1);
});

test('la pièce obligatoire manquante bloque la soumission', function () {
    $produit = PodProduit::query()->where('code', 'PEP-DEP')->firstOrFail();

    $this->actingAs(podUser('pep_agence'))->post('/pod/operations', [
        'pod_produit_id' => $produit->id,
        'compte_client' => '251000001',
        'champs_saisis' => ['NUMERO_PRET' => 'PR-001', 'TYPE_DEMANDE' => 'LIB_PEP', 'MOTIF_DEMANDE' => 'CLOTURE'],
    ])->assertSessionHasErrors('champs_saisis.PIECES_DEMANDE');

    expect(PodOperation::count())->toBe(0);
});

test('un rôle hors écran 1 ne peut pas initier', function () {
    $produit = PodProduit::query()->where('code', 'PEP-DEP')->firstOrFail();

    $this->actingAs(podUser('credit_risque'))->post('/pod/operations', [
        'pod_produit_id' => $produit->id,
        'compte_client' => '251000001',
    ])->assertSessionHas('error');

    expect(PodOperation::count())->toBe(0);
});

test('seul le rôle de l’étape courante peut la valider', function () {
    $operation = soumettrePep(podUser('pep_agence'));

    $this->actingAs(podUser('pod_operations'))
        ->post("/pod/operations/{$operation->id}/valider", [])
        ->assertSessionHas('error');

    expect($operation->fresh()->etape_code)->toBe('ECR02');
});

test('un même utilisateur ne peut pas traiter deux étapes', function () {
    $operation = soumettrePep(podUser('pep_agence'));
    $multi = podUser('credit_risque');
    $multi->roles()->attach(Role::query()->where('slug', 'pod_operations')->value('id'));

    $this->actingAs($multi)->post("/pod/operations/{$operation->id}/valider", [
        'champs_saisis' => ['MONTANT_A_SOLDER' => 1600000, 'IMPAYES' => 'NON', 'AUTRES_ENGAGEMENTS' => 'NON', 'OPPOSITION' => 'NON'],
    ])->assertSessionHasNoErrors();

    expect($operation->fresh()->etape_code)->toBe('ECR03');

    $this->actingAs($multi->fresh())
        ->post("/pod/operations/{$operation->id}/valider", ['champs_saisis' => ['PEP_MOBILISABLE' => 2000000]])
        ->assertSessionHas('error');

    expect($operation->fresh()->etape_code)->toBe('ECR03');
});

test('le SuperAdmin peut traiter tout le circuit seul', function () {
    Role::query()->firstOrCreate(['slug' => 'it'], ['nom' => 'SuperAdmin', 'module' => 'config', 'actif' => true]);
    $admin = podUser('it');
    $operation = soumettrePep($admin);

    $this->actingAs($admin)->post("/pod/operations/{$operation->id}/valider", [
        'champs_saisis' => ['MONTANT_A_SOLDER' => 1600000, 'IMPAYES' => 'NON', 'AUTRES_ENGAGEMENTS' => 'NON', 'OPPOSITION' => 'NON'],
    ])->assertSessionHasNoErrors()->assertSessionMissing('error');

    expect($operation->fresh()->etape_code)->toBe('ECR03');
});

test('suspension puis reprise par l’initiateur', function () {
    $agence = podUser('pep_agence');
    $operation = soumettrePep($agence);

    $this->actingAs(podUser('credit_risque'))
        ->post("/pod/operations/{$operation->id}/suspendre", ['motif' => 'Tableau d’amortissement manquant'])
        ->assertSessionHasNoErrors();

    expect($operation->fresh()->statut)->toBe(PodOperation::STATUT_SUSPENDUE);

    $this->actingAs($agence)
        ->post("/pod/operations/{$operation->id}/reprendre", ['commentaire' => 'Ajouté', 'champs_saisis' => ['NUMERO_PRET' => 'PR-001', 'TYPE_DEMANDE' => 'LIB_PEP', 'MOTIF_DEMANDE' => 'CLOTURE']])
        ->assertSessionHasNoErrors();

    $operation->refresh();
    expect($operation->statut)->toBe(PodOperation::STATUT_EN_COURS)
        ->and($operation->etape_code)->toBe('ECR02');
});

test('le rejet exige un motif', function () {
    $operation = soumettrePep(podUser('pep_agence'));
    $risque = podUser('credit_risque');

    $this->actingAs($risque)->post("/pod/operations/{$operation->id}/rejeter", ['motif' => ''])
        ->assertSessionHasErrors('motif');

    $this->actingAs($risque)->post("/pod/operations/{$operation->id}/rejeter", ['motif' => 'Impayés non régularisés'])
        ->assertSessionHasNoErrors();

    expect($operation->fresh()->statut)->toBe(PodOperation::STATUT_REJETE);
});

test('la fiche et la liste montrent l’étape à traiter', function () {
    $operation = soumettrePep(podUser('pep_agence'));
    $risque = podUser('credit_risque');

    $this->actingAs($risque)->get("/pod/operations/{$operation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Pod/Operations/Show')
            ->has('circuit', 5)
            ->where('circuit.1.etat', 'courant')
            ->where('action.peut_agir', true)
            ->has('action.champs', 5));

    $this->actingAs($risque)->get('/pod/operations?vue=a_traiter')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('aTraiterCount', 1)->has('operations.data', 1));

    $this->actingAs(podUser('pep_agence'))->get('/pod/operations/create?produit_id='.$operation->pod_produit_id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('produit.champs', 7)->has('produit.circuit', 5));
});

test('le circuit complet valide le dossier', function () {
    $operation = soumettrePep(podUser('pep_agence'));

    $this->actingAs(podUser('credit_risque'))->post("/pod/operations/{$operation->id}/valider", [
        'champs_saisis' => ['MONTANT_A_SOLDER' => 1600000, 'IMPAYES' => 'NON', 'AUTRES_ENGAGEMENTS' => 'NON', 'OPPOSITION' => 'NON'],
    ])->assertSessionHasNoErrors();

    $this->actingAs(podUser('pod_operations'))->post("/pod/operations/{$operation->id}/valider", [
        'champs_saisis' => ['PEP_MOBILISABLE' => 2000000],
    ])->assertSessionHasNoErrors();

    $this->actingAs(podUser('pod_responsable'))->post("/pod/operations/{$operation->id}/valider", [])
        ->assertSessionHasNoErrors();

    $this->actingAs(podUser('pod_operations'))->post("/pod/operations/{$operation->id}/valider", [
        'champs_saisis' => ['REF_FLEXCUBE' => 'FT26290001', 'DATE_EXECUTION' => '2026-10-18'],
        'fichiers' => ['ATTESTATION' => [UploadedFile::fake()->create('attestation.pdf', 10, 'application/pdf')]],
    ])->assertSessionHasNoErrors();

    $operation->refresh();
    expect($operation->statut)->toBe(PodOperation::STATUT_VALIDE)
        ->and($operation->champs_saisis['REF_FLEXCUBE'])->toBe('FT26290001')
        ->and($operation->etapes()->where('action', 'validee')->count())->toBe(4)
        ->and($operation->pieces()->count())->toBe(2);
});
