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
        Schema::create('demandes_approvisionnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('en_attente'); // en_attente, approuvee, rejetee, livree
            $table->text('motif')->nullable();
            $table->timestamp('date_demande')->nullable();
            $table->timestamp('date_traitement')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('demande_approvisionnement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->integer('quantite_demandee');
            $table->integer('quantite_livree')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_approvisionnement_items');
        Schema::dropIfExists('demandes_approvisionnement');
    }
};
