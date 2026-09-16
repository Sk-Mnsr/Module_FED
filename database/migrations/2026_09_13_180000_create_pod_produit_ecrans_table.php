<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_produit_ecrans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_produit_id')->constrained('pod_produits')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('libelle');
            $table->text('description')->nullable();
            /** list de clés profil : agence|back_office|administration|finance|responsable|… */
            $table->json('profils')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['pod_produit_id', 'code']);
            $table->index(['pod_produit_id', 'sort_order']);
        });

        Schema::table('pod_produit_champs', function (Blueprint $table) {
            $table->foreignId('pod_produit_ecran_id')
                ->nullable()
                ->after('pod_produit_id')
                ->constrained('pod_produit_ecrans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pod_produit_champs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pod_produit_ecran_id');
        });

        Schema::dropIfExists('pod_produit_ecrans');
    }
};
