<?php

namespace Database\Seeders;

use App\Models\PodProduit;
use App\Models\PodProduitChamp;
use App\Models\PodProduitEcran;
use App\Models\PodProduitScript;
use App\Models\PodTrancheFrais;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Produit « FRAIS DE DEMANDE DE CREDIT » : le n° de compte saisi déclenche une recherche Flexcube
 * (agence, intitulé, matricule, solde net, encours global dû) avant de continuer.
 */
class PodFraisDemandeCreditSeeder extends Seeder
{
    private const CODE = 'FDC';

    private const SCRIPT = 'INFOS_COMPTE';

    /** Barèmes par type de crédit : [libellé, montant min, montant max (null = au-delà), frais]. */
    private const TRANCHES = [
        'PRET' => [
            ['De 100 000 à 5 000 000', 100000, 5000000, 5000],
            ['De 5 000 001 à 25 000 000', 5000001, 25000000, 15000],
            ['De 25 000 001 à 50 000 000', 25000001, 50000000, 30000],
            ['Au-delà de 50 000 000', 50000001, null, 50000],
        ],
        'COFI_EXPRESS' => [
            ['De 100 000 à 500 000', 100000, 500000, 5000],
            ['De 500 001 à 1 000 000', 500001, 1000000, 10000],
            ['Plus de 1 000 000', 1000001, null, 15000],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $produit = PodProduit::query()->firstOrNew(['code' => self::CODE]);
            $produit->fill([
                'libelle' => 'FRAIS DE DEMANDE DE CREDIT',
                'type_operation' => 'Frais de dossier crédit',
                'devise' => 'XOF',
                'mode_frais' => PodProduit::MODE_TRANCHE,
                'frais_fixe' => null,
                'bareme_champ' => 'TYPE_CREDIT',
                'taxe_champ' => null,
                'taux_taf' => 0,
                'compte_client_mask' => '251XXXXXX',
                'statut' => PodProduit::STATUT_VALIDE,
                'actif' => true,
            ]);
            $produit->save();

            if ($produit->operations()->exists()) {
                return;
            }

            $produit->champs()->delete();
            $produit->ecrans()->delete();
            $produit->scripts()->delete();
            $produit->tranches()->delete();

            foreach (self::TRANCHES as $critere => $tranches) {
                foreach ($tranches as $i => [$libelle, $min, $max, $frais]) {
                    PodTrancheFrais::create([
                        'pod_produit_id' => $produit->id,
                        'libelle' => $libelle,
                        'critere' => $critere,
                        'montant_min' => $min,
                        'montant_max' => $max,
                        'frais_fixe' => $frais,
                        'sort_order' => ($critere === 'PRET' ? 0 : 100) + $i,
                    ]);
                }
            }

            PodProduitScript::create([
                'pod_produit_id' => $produit->id,
                'code' => self::SCRIPT,
                'libelle' => 'Informations du compte (Flexcube)',
                'moteur' => 'requete',
                'parametres' => ['requete' => file_get_contents(database_path('seeders/sql/pod_infos_compte_credit.sql'))],
                'actif' => true,
                'sort_order' => 0,
            ]);

            $ecran = PodProduitEcran::create([
                'pod_produit_id' => $produit->id,
                'code' => 'ECR01',
                'libelle' => 'Compte du client',
                'description' => 'Saisie du compte et récupération des informations Flexcube',
                'profils' => [],
                'actif' => true,
                'sort_order' => 0,
            ]);

            $champs = [
                ['code' => 'NUMERO_COMPTE', 'libelle' => 'Numéro de compte', 'type' => 'texte', 'obligatoire' => true, 'gris' => false, 'options' => ['max_length' => 20, 'recherche' => self::SCRIPT]],
                ['code' => 'AGENCE', 'libelle' => 'Agence', 'type' => 'texte', 'gris' => true, 'options' => ['max_length' => 255]],
                ['code' => 'COMPTE', 'libelle' => 'Intitulé du compte', 'type' => 'texte', 'gris' => true, 'options' => ['max_length' => 255]],
                ['code' => 'MATRICULE', 'libelle' => 'Matricule client', 'type' => 'texte', 'gris' => true, 'options' => ['max_length' => 30]],
                ['code' => 'SOLDE_NET', 'libelle' => 'Solde net', 'type' => 'numerique', 'gris' => true, 'options' => ['decimales' => 0]],
                ['code' => 'ENCOURS_GLOBAL_DUE', 'libelle' => 'Encours global dû', 'type' => 'numerique', 'gris' => true, 'options' => ['decimales' => 0]],
                ['code' => 'TYPE_CREDIT', 'libelle' => 'Type de crédit', 'type' => 'liste', 'obligatoire' => true, 'gris' => false, 'options' => ['valeurs' => [
                    ['code' => 'PRET', 'libelle' => 'Demande de prêt', 'actif' => true, 'ordre' => 0],
                    ['code' => 'COFI_EXPRESS', 'libelle' => 'Demande Cofi Express', 'actif' => true, 'ordre' => 1],
                ]]],
            ];

            foreach ($champs as $i => $champ) {
                PodProduitChamp::create([
                    'pod_produit_id' => $produit->id,
                    'pod_produit_ecran_id' => $ecran->id,
                    'code' => $champ['code'],
                    'libelle' => $champ['libelle'],
                    'type' => $champ['type'],
                    'obligatoire' => $champ['obligatoire'] ?? false,
                    'visible' => true,
                    'gris' => $champ['gris'],
                    'valeur_defaut' => null,
                    'options' => $champ['options'],
                    'sort_order' => $i,
                ]);
            }
        });
    }
}
