<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('archive_date');
        });

        // Rétro-remplissage pour les enregistrements existants.
        DB::table('od_classeurs')->whereNull('archived_at')->update([
            'archived_at' => DB::raw('COALESCE(integrated_at, created_at)'),
        ]);
        DB::table('od_classeurs')->whereNull('archive_date')->update([
            'archive_date' => DB::raw('DATE(COALESCE(integrated_at, created_at))'),
        ]);
    }

    public function down(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
