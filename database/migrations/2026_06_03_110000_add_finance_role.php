<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'finance'],
            [
                'nom' => 'Finance',
                'description' => 'Accès au module Opérations diverses (pièce comptable, archivage)',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'finance')->delete();
    }
};
