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
        Schema::create('fed_fournisseur_offres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fed_id')->constrained('feds')->cascadeOnDelete();
            $table->string('fournisseur');
            $table->decimal('montant', 15, 2)->nullable();
            $table->string('delai')->nullable();
            $table->text('remarques')->nullable();
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fed_fournisseur_offres');
    }
};
