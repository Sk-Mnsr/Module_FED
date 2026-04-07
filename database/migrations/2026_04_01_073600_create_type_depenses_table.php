<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suppression de l'ancienne table compte_charges (données non utilisées)
        Schema::dropIfExists('compte_charges');

        Schema::create('type_depenses', function (Blueprint $table) {
            $table->id();
            $table->string('nom_depense');
            $table->string('compte_gl')->nullable();
            $table->enum('responsable', ['IT', 'Facilities', 'RH', 'ALL'])->default('ALL');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_depenses');

        Schema::create('compte_charges', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code_agence')->nullable();
            $table->string('code_gl')->nullable();
            $table->timestamps();
        });
    }
};
