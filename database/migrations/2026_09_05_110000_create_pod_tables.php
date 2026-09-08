<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_produits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->string('type_operation')->nullable();
            $table->string('devise', 10)->default('XOF');
            $table->string('code_taf', 50)->nullable();
            $table->string('libelle_ecriture_produit')->nullable();
            $table->string('libelle_ecriture_taf')->nullable();
            $table->string('compte_produit', 50)->nullable();
            $table->string('compte_taf', 50)->nullable();
            $table->string('compte_client_mask', 50)->nullable()->default('251XXXXXX');
            $table->string('initiateur', 50)->nullable();
            $table->string('validateur', 50)->nullable();
            /** fixe|tranche|taux|mixte|manuel|gratuit */
            $table->string('mode_frais', 30)->default('fixe');
            $table->decimal('frais_fixe', 18, 2)->nullable();
            $table->decimal('taux_frais', 10, 4)->nullable();
            $table->decimal('frais_min', 18, 2)->nullable();
            $table->decimal('frais_max', 18, 2)->nullable();
            $table->decimal('taux_taf', 10, 4)->nullable();
            $table->text('base_calcul')->nullable();
            $table->text('notes')->nullable();
            /** brouillon|valide|production */
            $table->string('statut', 30)->default('brouillon');
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pod_tranches_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->cascadeOnDelete();
            $table->string('libelle')->nullable();
            $table->decimal('montant_min', 18, 2)->default(0);
            $table->decimal('montant_max', 18, 2)->nullable();
            $table->decimal('frais_fixe', 18, 2)->nullable();
            $table->decimal('taux', 10, 4)->nullable();
            $table->decimal('frais_min', 18, 2)->nullable();
            $table->decimal('frais_max', 18, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pod_produit_id', 'sort_order']);
        });

        Schema::create('pod_lignes_comptables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('sens', 1); // D|C
            $table->string('compte', 50);
            $table->string('libelle_ecriture')->nullable();
            /** client|produit|taf|contrepartie|autre */
            $table->string('nature_compte', 30)->default('autre');
            /** frais_ht|taf|frais_plus_taf|montant_operation|fixe|pourcentage */
            $table->string('type_montant', 30)->default('frais_ht');
            $table->decimal('montant_fixe', 18, 2)->nullable();
            $table->decimal('taux', 10, 4)->nullable();
            $table->boolean('obligatoire')->default(true);
            $table->timestamps();

            $table->index(['pod_produit_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_lignes_comptables');
        Schema::dropIfExists('pod_tranches_frais');
        Schema::dropIfExists('pod_produits');
    }
};
