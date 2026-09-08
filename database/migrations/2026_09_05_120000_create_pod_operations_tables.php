<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference', 100)->nullable();
            $table->string('compte_client', 50);
            $table->string('code_agence', 20)->nullable();
            $table->string('nom_client')->nullable();
            $table->date('date_valeur')->nullable();
            $table->decimal('montant_demande', 18, 2)->nullable();
            $table->decimal('frais_ht', 18, 2)->default(0);
            $table->decimal('taux_taf', 10, 4)->nullable();
            $table->decimal('montant_taf', 18, 2)->default(0);
            $table->decimal('total_client', 18, 2)->default(0);
            $table->string('devise', 10)->default('XOF');
            $table->text('libelle')->nullable();
            $table->json('calcul_detail')->nullable();
            /** brouillon|valide|annule */
            $table->string('statut', 30)->default('brouillon');
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index('pod_produit_id');
        });

        Schema::create('pod_operation_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_operation_id')->constrained('pod_operations')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('sens', 1);
            $table->string('compte', 50);
            $table->string('libelle_ecriture')->nullable();
            $table->string('nature_compte', 30)->nullable();
            $table->string('type_montant', 30)->nullable();
            $table->decimal('montant', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_operation_lignes');
        Schema::dropIfExists('pod_operations');
    }
};
