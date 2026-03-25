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
        Schema::create('appel_offres', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('objet');
            $table->text('description')->nullable();
            $table->date('date_lancement')->nullable();
            $table->datetime('date_limite_soumission')->nullable();
            $table->enum('statut', ['brouillon', 'publie', 'cloture', 'en_evaluation', 'attribue'])->default('brouillon');
            $table->enum('type_publication', ['interne', 'externe'])->default('interne');
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appel_offres');
    }
};
