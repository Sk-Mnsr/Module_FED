<?php

use App\Models\PodLigneComptable;
use App\Models\PodProduit;
use App\Models\PodProduitConstante;
use App\Models\PodProduitEcran;
use App\Models\PodProduitScript;
use App\Models\Role;
use App\Models\User;
use App\Support\PodConstanteResolver;
use App\Support\PodEcranAccess;
use App\Support\PodProduitWorkflow;
use App\Support\PodScriptRegistry;
use Illuminate\Validation\ValidationException;

function makePodProduit(array $overrides = []): PodProduit
{
    return PodProduit::query()->create(array_merge([
        'code' => 'TST'.uniqid(),
        'libelle' => 'Produit test',
        'devise' => 'XOF',
        'mode_frais' => PodProduit::MODE_FIXE,
        'frais_fixe' => 1000,
        'taux_frais' => 1.5,
        'compte_produit' => '70100000',
        'compte_taf' => '44310000',
        'initiateur' => 'Agence',
        'statut' => PodProduit::STATUT_BROUILLON,
        'actif' => true,
    ], $overrides));
}

test('workflow refuse la validation sans schéma comptable', function () {
    $produit = makePodProduit();

    expect(fn () => PodProduitWorkflow::assertCanTransition($produit, PodProduit::STATUT_VALIDE))
        ->toThrow(ValidationException::class);
});

test('workflow autorise brouillon → valide avec une ligne comptable', function () {
    $produit = makePodProduit();
    PodLigneComptable::query()->create([
        'pod_produit_id' => $produit->id,
        'sort_order' => 1,
        'sens' => 'D',
        'compte' => '37100000',
        'type_montant' => 'total_client',
        'obligatoire' => true,
    ]);

    PodProduitWorkflow::assertCanTransition($produit, PodProduit::STATUT_VALIDE);
    expect(true)->toBeTrue();
});

test('workflow refuse production → brouillon', function () {
    $produit = makePodProduit(['statut' => PodProduit::STATUT_PRODUCTION]);

    expect(fn () => PodProduitWorkflow::assertCanTransition($produit, PodProduit::STATUT_BROUILLON))
        ->toThrow(ValidationException::class);
});

test('constante mode défaut est résolue', function () {
    $produit = makePodProduit();
    PodProduitConstante::query()->create([
        'pod_produit_id' => $produit->id,
        'code' => 'TAUX_REF',
        'libelle' => 'Taux référence',
        'type' => 'numerique',
        'mode' => PodProduitConstante::MODE_DEFAUT,
        'valeur_reference' => '18',
        'obligatoire' => true,
        'sort_order' => 1,
    ]);

    $resolved = PodConstanteResolver::resolveAll($produit);
    expect($resolved['TAUX_REF']['valeur'])->toEqual(18.0);
});

test('constante mode champ lit la saisie', function () {
    $produit = makePodProduit();
    PodProduitConstante::query()->create([
        'pod_produit_id' => $produit->id,
        'code' => 'REF_EXT',
        'libelle' => 'Réf externe',
        'type' => 'texte',
        'mode' => PodProduitConstante::MODE_CHAMP,
        'valeur_reference' => 'NUM_DOSSIER',
        'obligatoire' => true,
        'sort_order' => 1,
    ]);

    $resolved = PodConstanteResolver::resolveAll($produit, [
        'champs_saisis' => ['NUM_DOSSIER' => 'D-42'],
    ]);

    expect($resolved['REF_EXT']['valeur'])->toBe('D-42');
});

test('script frais_taux_demande calcule correctement', function () {
    $produit = makePodProduit(['taux_frais' => 2]);
    $script = PodProduitScript::query()->create([
        'pod_produit_id' => $produit->id,
        'code' => 'CALC_FRAIS',
        'libelle' => 'Calcul frais',
        'moteur' => 'frais_taux_demande',
        'parametres' => [],
        'actif' => true,
        'sort_order' => 1,
    ]);

    $valeur = PodScriptRegistry::run($script, $produit, ['montant_demande' => 100_000]);
    expect($valeur)->toEqual(2000.0);
});

test('script valeur_fixe retourne le paramètre', function () {
    $produit = makePodProduit();
    $script = PodProduitScript::query()->create([
        'pod_produit_id' => $produit->id,
        'code' => 'FIXE',
        'libelle' => 'Fixe',
        'moteur' => 'valeur_fixe',
        'parametres' => ['valeur' => 500],
        'actif' => true,
        'sort_order' => 1,
    ]);

    expect(PodScriptRegistry::run($script, $produit, []))->toEqual(500);
});

test('écran sans profils est accessible à tout utilisateur', function () {
    $user = User::factory()->create();
    $produit = makePodProduit();
    $ecran = PodProduitEcran::query()->create([
        'pod_produit_id' => $produit->id,
        'code' => 'ECR01',
        'libelle' => 'Base',
        'profils' => [],
        'sort_order' => 1,
        'actif' => true,
    ]);

    expect(PodEcranAccess::userCanAccess($user, $ecran))->toBeTrue();
});

test('écran profil agence refuse un utilisateur sans rôle ops/pod', function () {
    $user = User::factory()->create();
    $produit = makePodProduit();
    $ecran = PodProduitEcran::query()->create([
        'pod_produit_id' => $produit->id,
        'code' => 'ECR01',
        'libelle' => 'Base',
        'profils' => ['agence'],
        'sort_order' => 1,
        'actif' => true,
    ]);

    expect(PodEcranAccess::userCanAccess($user, $ecran))->toBeFalse();
});

test('écran profil agence accepte un utilisateur avec rôle ops', function () {
    $user = User::factory()->create();
    $role = Role::query()->create([
        'nom' => 'Ops',
        'slug' => 'ops',
        'name' => 'ops',
        'label' => 'Ops',
        'module' => 'pod',
        'access_profile' => 'other',
        'actif' => true,
        'is_super_admin' => false,
    ]);
    $user->roles()->attach($role->id);

    $produit = makePodProduit();
    $ecran = PodProduitEcran::query()->create([
        'pod_produit_id' => $produit->id,
        'code' => 'ECR01',
        'libelle' => 'Base',
        'profils' => ['agence'],
        'sort_order' => 1,
        'actif' => true,
    ]);

    expect(PodEcranAccess::userCanAccess($user, $ecran))->toBeTrue();
});
