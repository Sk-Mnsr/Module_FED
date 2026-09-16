<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_produit_constantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('libelle');
            /** compte|numerique|montant|texte|code */
            $table->string('type', 30)->default('texte');
            /** schema|script|defaut|champ */
            $table->string('mode', 30)->default('defaut');
            /** Valeur fixe, code champ, code script, ou référence schéma */
            $table->string('valeur_reference')->nullable();
            $table->boolean('obligatoire')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['pod_produit_id', 'code']);
            $table->index(['pod_produit_id', 'sort_order']);
        });

        Schema::create('pod_produit_scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('libelle');
            /** Clé whitelist moteur (ex. frais_taux_demande) */
            $table->string('moteur', 80);
            $table->json('parametres')->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['pod_produit_id', 'code']);
        });

        Schema::create('pod_produit_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('resume')->nullable();
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->timestamps();

            $table->index(['pod_produit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_produit_audits');
        Schema::dropIfExists('pod_produit_scripts');
        Schema::dropIfExists('pod_produit_constantes');
    }
};
