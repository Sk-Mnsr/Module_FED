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
        Schema::create('offres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_offre_id')->constrained('appel_offres')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Fournisseur interne');
            $table->string('nom_fournisseur')->nullable()->comment('Fournisseur externe');
            $table->string('email_fournisseur')->nullable();
            $table->dateTime('date_soumission');
            $table->decimal('note_technique', 8, 2)->nullable();
            $table->decimal('note_financiere', 8, 2)->nullable();
            $table->decimal('note_totale', 8, 2)->nullable();
            $table->integer('classement')->nullable();
            $table->string('statut')->default('soumise'); // soumise, ouverte, evaluee, rejetee, approuvee
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offres');
    }
};
