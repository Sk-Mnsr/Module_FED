<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $payload = [
            'nom' => 'Produits divers',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'label')) {
            $payload['label'] = 'Produits divers';
        }
        if (Schema::hasColumn('roles', 'description')) {
            $payload['description'] = 'Paramétrage des produits divers (champs, frais, TAF, schémas comptables)';
        }

        DB::table('roles')->where('slug', 'pod')->update($payload);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $payload = [
            'nom' => 'POD',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'label')) {
            $payload['label'] = 'POD';
        }
        if (Schema::hasColumn('roles', 'description')) {
            $payload['description'] = 'Paramétrage des produits d’opérations diverses (frais, TAF, schémas comptables)';
        }

        DB::table('roles')->where('slug', 'pod')->update($payload);
    }
};
