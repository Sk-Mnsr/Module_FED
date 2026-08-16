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

        DB::table('roles')->where('slug', 'budget')->update([
            'description' => 'Autorise l’accès au module Budget. Les droits (consultation, ajouter, modifier…) se cochent ensuite.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')->where('slug', 'budget')->update([
            'description' => 'Autorise l’accès au module Budget. Les droits (consultation, ajouter, modifier…) se cochent ensuite.',
            'updated_at' => now(),
        ]);
    }
};
