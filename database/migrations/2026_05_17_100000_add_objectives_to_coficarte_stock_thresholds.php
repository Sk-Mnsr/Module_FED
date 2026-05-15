<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_stock_thresholds', function (Blueprint $table) {
            $table->unsignedInteger('objectif_nb_ventes_mois')->default(0)->after('min_cards');
            $table->unsignedBigInteger('objectif_montant_recharges_mois')->default(0)->after('objectif_nb_ventes_mois');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_stock_thresholds', function (Blueprint $table) {
            $table->dropColumn(['objectif_nb_ventes_mois', 'objectif_montant_recharges_mois']);
        });
    }
};
