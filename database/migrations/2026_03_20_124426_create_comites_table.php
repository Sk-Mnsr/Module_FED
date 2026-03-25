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
        Schema::create('comites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_offre_id')->constrained('appel_offres')->cascadeOnDelete();
            $table->string('nom');
            $table->string('statut')->default('actif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comites');
    }
};
