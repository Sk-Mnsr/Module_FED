<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appel_offre_fournisseur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_offre_id')->constrained('appel_offres')->cascadeOnDelete();
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appel_offre_fournisseur');
    }
};
