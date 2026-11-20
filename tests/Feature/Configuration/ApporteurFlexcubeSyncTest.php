<?php

use App\Models\Agence;
use App\Models\CoficarteApporteur;
use App\Services\Integrations\FlexcubeApporteurSync;
use App\Services\Integrations\FlexcubeQueryRunner;

test('la synchronisation crée, met à jour et ignore les apporteurs selon Flexcube', function () {
    $agence = Agence::create(['nom' => 'Agence test', 'code' => 'AGT-SYNC']);

    CoficarteApporteur::create(['agence_id' => null, 'code' => 'AP-M0334', 'nom' => 'Ancien nom', 'actif' => false]);
    CoficarteApporteur::create(['agence_id' => $agence->id, 'code' => 'AP-M9999', 'nom' => 'Local agence', 'actif' => true]);

    $this->mock(FlexcubeQueryRunner::class, function ($mock) {
        $mock->shouldReceive('select')
            ->once()
            ->withArgs(fn (string $sql, array $binds, int $max) => str_contains($sql, 'UDTM_LOV') && $binds === [])
            ->andReturn([
                ['LOV' => 'AP-M0334', 'LOV_DESC' => 'Mansour seck'],
                ['LOV' => 'ap-m0500', 'LOV_DESC' => 'Nouvel apporteur'],
                ['LOV' => 'AP-M0600', 'LOV_DESC' => ''],
                ['LOV' => 'AP-M9999', 'LOV_DESC' => 'Conflit agence'],
                ['LOV' => 'AP-M0500', 'LOV_DESC' => 'Doublon ignoré'],
            ]);
    });

    $resultat = app(FlexcubeApporteurSync::class)->synchroniser();

    expect($resultat)
        ->toMatchArray(['crees' => 1, 'mis_a_jour' => 1, 'inchanges' => 0, 'ignores' => 3, 'total_flexcube' => 5])
        ->and($resultat['erreurs'])->toHaveCount(1);

    $ap = CoficarteApporteur::query()->whereNull('agence_id')->where('code', 'AP-M0334')->firstOrFail();
    expect($ap->nom)->toBe('Mansour seck')
        ->and($ap->actif)->toBeFalse();

    $nouveau = CoficarteApporteur::query()->where('code', 'AP-M0500')->firstOrFail();
    expect($nouveau->nom)->toBe('Nouvel apporteur')
        ->and($nouveau->agence_id)->toBeNull()
        ->and($nouveau->actif)->toBeTrue();

    expect(CoficarteApporteur::query()->where('code', 'AP-M9999')->value('agence_id'))->toBe($agence->id);
});

test('une relance sans changement ne modifie rien', function () {
    $lignes = [['LOV' => 'AP-M0334', 'LOV_DESC' => 'Mansour seck']];

    $this->mock(FlexcubeQueryRunner::class, function ($mock) use ($lignes) {
        $mock->shouldReceive('select')->twice()->andReturn($lignes);
    });

    $sync = app(FlexcubeApporteurSync::class);
    $sync->synchroniser();
    $resultat = $sync->synchroniser();

    expect($resultat)->toMatchArray(['crees' => 0, 'mis_a_jour' => 0, 'inchanges' => 1]);
    expect(CoficarteApporteur::query()->where('code', 'AP-M0334')->count())->toBe(1);
});
