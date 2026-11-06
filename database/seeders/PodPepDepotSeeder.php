<?php

namespace Database\Seeders;

use App\Models\PodProduit;
use App\Models\PodProduitChamp;
use App\Models\PodProduitEcran;
use App\Models\Role;
use App\Support\ModuleAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Produit « Libération PEP / dépôt de garantie / mainlevée » (cahier des charges PEP-DEPOT).
 */
class PodPepDepotSeeder extends Seeder
{
    private const CODE = 'PEP-DEP';

    private const ROLES = [
        ['slug' => 'pep_agence', 'nom' => 'Agence / CAF', 'description' => 'Crée les demandes de libération PEP / dépôt de garantie'],
        ['slug' => 'credit_risque', 'nom' => 'Crédit / Risque', 'description' => 'Vérifie crédits, impayés et engagements'],
        ['slug' => 'pod_operations', 'nom' => 'Opérations (produits divers)', 'description' => 'Contrôle des soldes et exécution'],
        ['slug' => 'pod_responsable', 'nom' => 'Responsable habilité', 'description' => 'Décision de validation ou de rejet'],
    ];

    private const OUI_NON = [
        ['code' => 'NON', 'libelle' => 'Non'],
        ['code' => 'OUI', 'libelle' => 'Oui'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::ROLES as $data) {
                $role = Role::updateOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'nom' => $data['nom'],
                        'module' => 'pod',
                        'access_profile' => 'other',
                        'description' => $data['description'],
                        'actif' => true,
                    ],
                );
                $role->syncModuleKeys(['pod']);
            }

            $produit = PodProduit::query()->firstOrNew(['code' => self::CODE]);
            $produit->fill([
                'libelle' => 'Libération PEP / dépôt de garantie / mainlevée',
                'type_operation' => 'Libération de garantie',
                'devise' => 'XOF',
                'mode_frais' => PodProduit::MODE_GRATUIT,
                'initiateur' => 'pep_agence',
                'validateur' => null,
                'compte_client_mask' => '251XXXXXX',
                'base_calcul' => 'Aucune libération si les fonds mobilisables ne couvrent pas le montant à solder (RG-03).',
                'statut' => PodProduit::STATUT_VALIDE,
                'actif' => true,
            ]);
            $produit->save();

            if ($produit->operations()->exists()) {
                return;
            }

            $produit->champs()->delete();
            $produit->ecrans()->delete();

            foreach ($this->ecrans() as $order => $def) {
                $ecran = PodProduitEcran::create([
                    'pod_produit_id' => $produit->id,
                    'code' => $def['code'],
                    'libelle' => $def['libelle'],
                    'description' => $def['description'],
                    'profils' => [$def['role']],
                    'actif' => true,
                    'sort_order' => $order,
                ]);

                foreach ($def['champs'] as $i => $champ) {
                    PodProduitChamp::create([
                        'pod_produit_id' => $produit->id,
                        'pod_produit_ecran_id' => $ecran->id,
                        'code' => $champ['code'],
                        'libelle' => $champ['libelle'],
                        'type' => $champ['type'],
                        'obligatoire' => $champ['obligatoire'] ?? false,
                        'visible' => true,
                        'gris' => false,
                        'valeur_defaut' => null,
                        'options' => $champ['options'] ?? [],
                        'sort_order' => $order * 100 + $i,
                    ]);
                }
            }
        });

        ModuleAccess::clearModuleRolesCache();
    }

    /**
     * @return list<array{code: string, libelle: string, description: string, role: string, champs: list<array<string, mixed>>}>
     */
    private function ecrans(): array
    {
        return [
            [
                'code' => 'ECR01',
                'libelle' => 'Demande agence',
                'description' => 'Création de la demande et pièces justificatives',
                'role' => 'pep_agence',
                'champs' => [
                    ['code' => 'NUMERO_PRET', 'libelle' => 'Numéro de prêt', 'type' => 'texte', 'obligatoire' => true, 'options' => ['max_length' => 30]],
                    ['code' => 'TYPE_DEMANDE', 'libelle' => 'Type de demande', 'type' => 'liste', 'obligatoire' => true, 'options' => ['valeurs' => $this->valeurs([
                        ['code' => 'LIB_PEP', 'libelle' => 'Libération PEP'],
                        ['code' => 'LIB_DEPOT', 'libelle' => 'Libération dépôt de garantie'],
                        ['code' => 'MAINLEVEE', 'libelle' => 'Mainlevée de garantie'],
                    ])]],
                    ['code' => 'MOTIF_DEMANDE', 'libelle' => 'Motif de la demande', 'type' => 'liste', 'obligatoire' => true, 'options' => ['valeurs' => $this->valeurs([
                        ['code' => 'ECHEANCE_FINALE', 'libelle' => 'Échéance finale'],
                        ['code' => 'REMB_ANTICIPE', 'libelle' => 'Remboursement anticipé'],
                        ['code' => 'CLOTURE', 'libelle' => 'Clôture du crédit'],
                        ['code' => 'AUTRE', 'libelle' => 'Autre'],
                    ])]],
                    ['code' => 'MONTANT_SOLLICITE', 'libelle' => 'Montant demandé', 'type' => 'numerique', 'options' => ['min' => 0, 'decimales' => 0]],
                    ['code' => 'COMPTE_BENEFICIAIRE', 'libelle' => 'Compte bénéficiaire du reliquat', 'type' => 'texte', 'options' => ['max_length' => 30]],
                    ['code' => 'COMMENTAIRE_CAF', 'libelle' => 'Commentaire', 'type' => 'texte', 'options' => ['max_length' => 1000]],
                    ['code' => 'PIECES_DEMANDE', 'libelle' => 'Demande du client et justificatifs', 'type' => 'fichier', 'obligatoire' => true],
                ],
            ],
            [
                'code' => 'ECR02',
                'libelle' => 'Contrôle Crédit / Risque',
                'description' => 'Crédits, impayés et engagements du client',
                'role' => 'credit_risque',
                'champs' => [
                    ['code' => 'MONTANT_A_SOLDER', 'libelle' => 'Montant total à solder (capital, intérêts, frais)', 'type' => 'numerique', 'obligatoire' => true, 'options' => ['min' => 0, 'decimales' => 0]],
                    ['code' => 'IMPAYES', 'libelle' => 'Impayés en cours', 'type' => 'liste', 'obligatoire' => true, 'options' => ['valeurs' => $this->valeurs(self::OUI_NON)]],
                    ['code' => 'AUTRES_ENGAGEMENTS', 'libelle' => 'Autres crédits ou engagements couverts', 'type' => 'liste', 'obligatoire' => true, 'options' => ['valeurs' => $this->valeurs(self::OUI_NON)]],
                    ['code' => 'OPPOSITION', 'libelle' => 'Opposition ou restriction', 'type' => 'liste', 'obligatoire' => true, 'options' => ['valeurs' => $this->valeurs(self::OUI_NON)]],
                    ['code' => 'OBS_RISQUE', 'libelle' => 'Observations Crédit / Risque', 'type' => 'texte', 'options' => ['max_length' => 1000]],
                ],
            ],
            [
                'code' => 'ECR03',
                'libelle' => 'Contrôle Opérations',
                'description' => 'Soldes mobilisables et affectation des fonds',
                'role' => 'pod_operations',
                'champs' => [
                    ['code' => 'PEP_MOBILISABLE', 'libelle' => 'PEP disponible mobilisable', 'type' => 'numerique', 'obligatoire' => true, 'options' => ['min' => 0, 'decimales' => 0]],
                    ['code' => 'DEPOT_MOBILISABLE', 'libelle' => 'Dépôt de garantie mobilisable', 'type' => 'numerique', 'options' => ['min' => 0, 'decimales' => 0]],
                    ['code' => 'AUTRES_RESSOURCES', 'libelle' => 'Autres ressources mobilisables', 'type' => 'numerique', 'options' => ['min' => 0, 'decimales' => 0]],
                    ['code' => 'FONDS_BLOQUES', 'libelle' => 'Fonds bloqués ou déjà affectés (exclus)', 'type' => 'numerique', 'options' => ['min' => 0, 'decimales' => 0]],
                    ['code' => 'OBS_OPERATIONS', 'libelle' => 'Observations Opérations', 'type' => 'texte', 'options' => ['max_length' => 1000]],
                ],
            ],
            [
                'code' => 'ECR04',
                'libelle' => 'Décision du responsable',
                'description' => 'Validation ou rejet motivé',
                'role' => 'pod_responsable',
                'champs' => [
                    ['code' => 'OBS_DECISION', 'libelle' => 'Observation de décision', 'type' => 'texte', 'options' => ['max_length' => 1000]],
                ],
            ],
            [
                'code' => 'ECR05',
                'libelle' => 'Exécution et attestation',
                'description' => 'Exécution de la libération ou de la mainlevée et attestation',
                'role' => 'pod_operations',
                'champs' => [
                    ['code' => 'REF_FLEXCUBE', 'libelle' => 'Référence de l’opération FLEXCUBE', 'type' => 'texte', 'obligatoire' => true, 'options' => ['max_length' => 50]],
                    ['code' => 'DATE_EXECUTION', 'libelle' => 'Date d’exécution', 'type' => 'date', 'obligatoire' => true],
                    ['code' => 'ATTESTATION', 'libelle' => 'Attestation signée', 'type' => 'fichier', 'obligatoire' => true],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{code: string, libelle: string}>  $valeurs
     * @return list<array{code: string, libelle: string, actif: bool, ordre: int}>
     */
    private function valeurs(array $valeurs): array
    {
        return array_map(
            fn (array $v, int $i) => [...$v, 'actif' => true, 'ordre' => $i],
            $valeurs,
            array_keys($valeurs),
        );
    }
}
