<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->string('numero_bon_commande')->nullable()->after('offre_choisie_id');
            $table->date('date_bon_commande')->nullable()->after('numero_bon_commande');
            $table->text('adresse_livraison')->nullable()->after('date_bon_commande');
        });
    }

    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropColumn(['numero_bon_commande', 'date_bon_commande', 'adresse_livraison']);
        });
    }
};
