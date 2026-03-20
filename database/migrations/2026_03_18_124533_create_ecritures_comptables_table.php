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
        Schema::create('ecritures_comptables', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable();
            $table->string('no_batch')->nullable();
            $table->string('no_compte')->nullable();
            $table->string('sens')->nullable();
            $table->decimal('montant', 15, 2)->nullable();
            $table->string('code_operation')->nullable();
            $table->date('date_de_valeur')->nullable();
            $table->string('code_agence')->nullable();
            $table->string('libelle_ecriture')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('annee_comptable')->nullable();
            $table->integer('mois_comptable')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecritures_comptables');
    }
};
