<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->text('adresse_client')->nullable()->after('telephone_client');
            $table->unsignedBigInteger('montant_premiere_recharge')->nullable()->after('adresse_client');
            $table->string('fiche_enrolement_path')->nullable()->after('montant_premiere_recharge');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->dropColumn(['adresse_client', 'montant_premiere_recharge', 'fiche_enrolement_path']);
        });
    }
};
