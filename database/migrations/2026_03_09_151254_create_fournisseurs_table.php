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
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('type')->nullable(); // Sera peuplé par seeder plus tard
            $table->string('categorie')->nullable(); // Sera peuplé par seeder plus tard
            $table->text('description')->nullable();
            
            // Contacts
            $table->string('contact_nom')->nullable();
            $table->string('contact_telephone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('site_web')->nullable();
            $table->text('adresse_physique')->nullable();
            
            // Informations Comptables
            $table->string('compte_transit_paiement', 12)->nullable();
            $table->string('compte_avance_acompte', 12)->nullable();
            $table->string('compte_client_interne', 12)->nullable();
            $table->foreignId('banque_id')->nullable()->constrained('banques')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};
