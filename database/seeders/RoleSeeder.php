<?php

namespace Database\Seeders;

use App\Models\Role;
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
                'description' => "SuperAdmin (IT) - Administrateur avec tous les droits sur l'application",
                'actif' => true,
            ],
            [
                'nom' => 'Demandeur',
                'slug' => 'demandeur',
                'description' => 'Initie les demandes de dépenses',
                'actif' => true,
            ],
            [
                'nom' => 'N+1',
                'slug' => 'n_plus_1',
                'description' => 'Valide la pertinence de la demande',
                'actif' => true,
            ],
            [
                'nom' => 'Responsable des Achats',
                'slug' => 'responsable_achats',
                'description' => 'Gère les consultations et offres',
                'actif' => true,
            ],
            [
                'nom' => 'Responsable Facilities',
                'slug' => 'responsable_facilities',
                'description' => 'Valide les offres et supervise le processus',
                'actif' => true,
            ],
            [
                'nom' => 'DAF',
                'slug' => 'daf',
                'description' => 'Directeur Administratif et Financier - Validation après Facilities',
                'actif' => true,
            ],
            [
                'nom' => 'DGA',
                'slug' => 'dga',
                'description' => 'Directeur Général Adjoint - Validation finale avant bon de commande',
                'actif' => true,
            ],
            [
                'nom' => 'Contrôle de Gestion',
                'slug' => 'controle_de_gestion',
                'description' => 'Vérifie la disponibilité de la ligne budgétaire pour la dépense',
                'actif' => true,
            ],
            [
                'nom' => 'Assistant Comptable',
                'slug' => 'assistant_comptable',
                'description' => 'Gère la saisie et le traitement des écritures comptables',
                'actif' => true,
            ],
            [
                'nom' => 'OPS',
                'slug' => 'ops',
                'description' => 'Opérations - Suivi et validation opérationnelle',
                'actif' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['nom' => $roleData['nom']],
                [
                    'slug' => $roleData['slug'],
                    'slug' => $roleData['slug'],
                    'description' => $roleData['description'],
                    'actif' => $roleData['actif'],
                ]
            );
        }
    }
}

