<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_produit_champs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('libelle');
            /** texte|liste|numerique|date */
            $table->string('type', 30)->default('texte');
            $table->boolean('obligatoire')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('gris')->default(false);
            $table->string('valeur_defaut')->nullable();
            /** Options typées (max_length, min/max, decimales, valeurs liste…) */
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['pod_produit_id', 'code']);
            $table->index(['pod_produit_id', 'sort_order']);
        });

        Schema::table('pod_operations', function (Blueprint $table) {
            $table->json('champs_saisis')->nullable()->after('calcul_detail');
        });
    }

    public function down(): void
    {
        Schema::table('pod_operations', function (Blueprint $table) {
            $table->dropColumn('champs_saisis');
        });

        Schema::dropIfExists('pod_produit_champs');
    }
};
