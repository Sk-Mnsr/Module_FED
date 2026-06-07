<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\ModuleAccess;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'nom' => 'SuperAdmin',
                'slug' => 'it',
                'module' => 'config',
                'access_profile' => 'admin',
                'description' => "SuperAdmin (IT) - Administrateur avec tous les droits sur l'application",
                'actif' => true,
            ],
            [
                'nom' => 'Demandeur',
                'slug' => 'demandeur',
                'module' => 'fed',
                'access_profile' => 'other',
                'description' => 'Initie les demandes de dépenses',
                'actif' => true,
            ],
            [
                'nom' => 'N+1',
                'slug' => 'n_plus_1',
                'module' => 'fed',
                'access_profile' => 'other',
                'description' => 'Valide la pertinence de la demande',
                'actif' => true,
            ],
            [
                'nom' => 'Responsable des Achats',
                'slug' => 'responsable_achats',
                'module' => 'fed',
                'access_profile' => 'other',
                'description' => 'Gère les consultations et offres',
                'actif' => true,
            ],
            [
                'nom' => 'Responsable Facilities',
                'slug' => 'responsable_facilities',
                'module' => 'fed',
                'access_profile' => 'other',
                'description' => 'Valide les offres et supervise le processus',
                'actif' => true,
            ],
            [
                'nom' => 'Responsable Stock',
                'slug' => 'responsable_stock',
                'module' => 'stock',
                'access_profile' => 'other',
                'description' => 'Gère les stocks et approvisionnements',
                'actif' => true,
            ],
            [
                'nom' => 'DAF',
                'slug' => 'daf',
                'module' => 'fed',
                'access_profile' => 'other',
                'description' => 'Directeur Administratif et Financier - Validation après Facilities',
                'actif' => true,
            ],
            [
                'nom' => 'DGA',
                'slug' => 'dga',
                'module' => 'fed',
                'access_profile' => 'other',
                'description' => 'Directeur Général Adjoint - Validation finale avant bon de commande',
                'actif' => true,
            ],
            [
                'nom' => 'Contrôle de Gestion',
                'slug' => 'controle_de_gestion',
                'module' => 'ecritures',
                'access_profile' => 'other',
                'description' => 'Vérifie la disponibilité de la ligne budgétaire pour la dépense',
                'actif' => true,
            ],
            [
                'nom' => 'Assistant Comptable',
                'slug' => 'assistant_comptable',
                'module' => 'fed',
                'access_profile' => 'other',
                'description' => 'Gère la saisie et le traitement des écritures comptables',
                'actif' => true,
            ],
            [
                'nom' => 'OPS',
                'slug' => 'ops',
                'module' => 'od',
                'access_profile' => 'other',
                'description' => 'Opérations - Suivi et validation opérationnelle',
                'actif' => true,
            ],
            [
                'nom' => 'Finance',
                'slug' => 'finance',
                'module' => 'od',
                'access_profile' => 'other',
                'description' => 'Accès au module Opérations diverses (pièce comptable, archivage)',
                'actif' => true,
            ],
            [
                'nom' => 'Monétique',
                'slug' => 'monetique_ops',
                'module' => 'monetique',
                'access_profile' => 'monetique',
                'description' => 'Accès au module Monétique (Coficarte, Cartes, Transferts, Ventes)',
                'actif' => true,
            ],
            [
                'nom' => 'Responsable Monétique',
                'slug' => 'monetique',
                'module' => 'monetique',
                'access_profile' => 'monetique',
                'description' => 'Gestion centrale Coficarte : ajout de cartes, modification des prix de vente (siège)',
                'actif' => true,
            ],
            [
                'nom' => "Chef d'agence CA",
                'slug' => 'ca',
                'module' => 'monetique',
                'access_profile' => 'monetique',
                'description' => "Chef d'agence : réception / retour de cartes, gestion du stock d'agence, approvisionnement des chargés de clientèle (CC), suivi des ventes et recharges au niveau de l'agence. La monétique centrale (création de cartes, transferts siège → agence, prix) reste sur le rôle Monétique.",
                'actif' => true,
            ],
            [
                'nom' => 'Chargé Clientèle CC',
                'slug' => 'cc',
                'module' => 'monetique',
                'access_profile' => 'monetique',
                'description' => 'Chargé Clientèle CC - Gestion commerciale Monétique',
                'actif' => true,
            ],
            [
                'nom' => 'Caissier',
                'slug' => 'caissier',
                'module' => 'monetique',
                'access_profile' => 'monetique',
                'description' => 'Caissier - Traitement des opérations Monétique au guichet',
                'actif' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            $module = $roleData['module'] ?? ModuleAccess::primaryModuleForRoleSlug($roleData['slug']);
            $accessProfile = $roleData['access_profile'] ?? ModuleAccess::accessProfileForRoleSlug($roleData['slug']);

            Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'nom' => $roleData['nom'],
                    'module' => $module,
                    'access_profile' => $accessProfile,
                    'description' => $roleData['description'],
                    'actif' => $roleData['actif'],
                ]
            );
        }

        foreach (Role::all() as $role) {
            if ($role->moduleKeys() !== []) {
                continue;
            }

            $keys = ModuleAccess::inferredModuleKeysForSlug($role->slug);
            if ($keys === [] && filled($role->module)) {
                $keys = [$role->module];
            }

            if ($keys !== []) {
                $role->syncModuleKeys($keys);
            }
        }
    }
}
