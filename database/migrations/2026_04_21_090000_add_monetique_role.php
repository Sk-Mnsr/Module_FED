<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'monetique'],
            [
                'nom' => 'Monétique',
                'description' => 'Accès au module Monétique (Coficarte, Cartes, Transferts, Ventes)',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'monetique')->delete();
    }
};
