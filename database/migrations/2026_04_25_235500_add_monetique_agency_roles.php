<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'slug' => 'chef_agence_ca',
                'nom' => "Chef d'agence CA",
                'description' => "Chef d'agence CA - Accès aux opérations Monétique en agence",
            ],
            [
                'slug' => 'charge_clientele_cc',
                'nom' => 'Chargé Clientèle CC',
                'description' => 'Chargé Clientèle CC - Gestion commerciale Monétique',
            ],
            [
                'slug' => 'caissier',
                'nom' => 'Caissier',
                'description' => 'Caissier - Traitement des opérations Monétique au guichet',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'nom' => $role['nom'],
                    'description' => $role['description'],
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('slug', [
            'chef_agence_ca',
            'charge_clientele_cc',
            'caissier',
        ])->delete();
    }
};
