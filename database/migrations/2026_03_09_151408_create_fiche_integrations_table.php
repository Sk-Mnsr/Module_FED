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
        Schema::create('fiche_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('numero');
            $table->string('no_batch');
            $table->string('no_compte');
            $table->string('sens');
            $table->decimal('montant', 15, 2);
            $table->integer('code_operation');
            $table->date('date_de_valeur');
            $table->integer('code_agence')->default(500);
            $table->text('libele_ecriture')->nullable();
            $table->string('user_id');
            $table->string('annee_comptable');
            $table->string('mois_comptable');

            $table->decimal('montantAPayer', 15, 2);
            $table->decimal('account', 15, 2);
            $table->decimal('relicat', 15, 2);
            $table->decimal('restantAPayer', 15, 2);
            $table->string('statut')->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiche_integrations');
    }
};
