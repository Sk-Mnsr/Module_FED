<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'responsable_stock'],
            [
                'nom' => 'Responsable Stock',
                'description' => 'Gère le stock et les demandes d\'approvisionnement (Logistique, IT, Parc)',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'responsable_stock')->delete();
    }
};
