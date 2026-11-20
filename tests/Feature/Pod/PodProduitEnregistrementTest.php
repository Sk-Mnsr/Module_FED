<?php

use App\Models\PodProduit;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\FlexcubeQueryRunner;
use App\Support\ModuleAccess;
use Database\Seeders\PodFraisDemandeCreditSeeder;

test('le produit FDC se réenregistre depuis son formulaire sans modification', function () {
    $this->seed(PodFraisDemandeCreditSeeder::class);
    $this->mock(FlexcubeQueryRunner::class);

    $role = Role::query()->firstOrCreate(['slug' => 'pod'], ['nom' => 'Produits divers', 'module' => 'pod', 'actif' => true]);
    $role->syncModuleKeys(['pod']);
    ModuleAccess::clearModuleRolesCache();
    $user = User::factory()->create();
    $user->roles()->attach($role->id);

    $produit = PodProduit::query()->where('code', 'FDC')->firstOrFail();

    $props = null;
    $this->actingAs($user->fresh())
        ->get("/pod/produits/{$produit->id}/edit")
        ->assertOk()
        ->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    $p = $props['produit'];
    $payload = [
        ...collect($p)->only([
            'code', 'libelle', 'type_operation', 'devise', 'code_taf', 'libelle_ecriture_produit',
            'libelle_ecriture_taf', 'compte_produit', 'compte_taf', 'compte_client_mask', 'initiateur',
            'mode_frais', 'frais_fixe', 'taux_frais', 'frais_min', 'frais_max', 'taux_taf', 'bareme_champ',
            'taxe_champ', 'base_calcul', 'notes', 'statut', 'actif', 'tranches', 'lignes', 'ecrans', 'champs',
            'constantes', 'scripts',
        ])->all(),
        'compte_produit' => '706100000',
        'validateur' => '',
    ];

    $this->actingAs($user->fresh())
        ->put("/pod/produits/{$produit->id}", $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});
