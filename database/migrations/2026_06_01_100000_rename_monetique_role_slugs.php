<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Libère le slug « monetique » pour l’ex-responsable_monetique
        DB::table('roles')->where('slug', 'monetique')->update(['slug' => 'monetique_ops']);

        DB::table('roles')->where('slug', 'responsable_monetique')->update(['slug' => 'monetique']);
        DB::table('roles')->where('slug', 'chef_agence_ca')->update(['slug' => 'ca']);
        DB::table('roles')->where('slug', 'charge_clientele_cc')->update(['slug' => 'cc']);
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'cc')->update(['slug' => 'charge_clientele_cc']);
        DB::table('roles')->where('slug', 'ca')->update(['slug' => 'chef_agence_ca']);
        DB::table('roles')->where('slug', 'monetique')->update(['slug' => 'responsable_monetique']);
        DB::table('roles')->where('slug', 'monetique_ops')->update(['slug' => 'monetique']);
    }
};
