<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->foreignId('offre_choisie_id')->nullable()->after('facilities_signed_at')
                ->constrained('fed_fournisseur_offres')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropForeign(['offre_choisie_id']);
        });
    }
};
