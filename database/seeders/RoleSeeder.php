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
            [
                'nom' => 'Finance',
                'slug' => 'finance',
                'description' => 'Accès au module Opérations diverses (pièce comptable, archivage)',
                'actif' => true,
            ],
            [
                'nom' => 'Monétique',
                'slug' => 'monetique_ops',
                'description' => 'Accès au module Monétique (Coficarte, Cartes, Transferts, Ventes)',
                'actif' => true,
            ],
            [
                'nom' => 'Responsable Monétique',
                'slug' => 'monetique',
                'description' => 'Gestion centrale Coficarte : ajout de cartes, modification des prix de vente (siège)',
                'actif' => true,
            ],
            [
                'nom' => "Chef d'agence CA",
                'slug' => 'ca',
                'description' => "Chef d'agence : réception / retour de cartes, gestion du stock d'agence, approvisionnement des chargés de clientèle (CC), suivi des ventes et recharges au niveau de l'agence. La monétique centrale (création de cartes, transferts siège → agence, prix) reste sur le rôle Monétique.",
                'actif' => true,
            ],
            [
                'nom' => 'Chargé Clientèle CC',
                'slug' => 'cc',
                'description' => 'Chargé Clientèle CC - Gestion commerciale Monétique',
                'actif' => true,
            ],
            [
                'nom' => 'Caissier',
                'slug' => 'caissier',
                'description' => 'Caissier - Traitement des opérations Monétique au guichet',
                'actif' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['nom' => $roleData['nom']],
                [
                    'slug' => $roleData['slug'],
                    'description' => $roleData['description'],
                    'actif' => $roleData['actif'],
                ]
            );
        }
    }
}
