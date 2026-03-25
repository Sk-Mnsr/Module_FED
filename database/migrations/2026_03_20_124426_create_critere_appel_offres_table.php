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
        Schema::create('critere_appel_offres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_offre_id')->constrained('appel_offres')->cascadeOnDelete();
            $table->string('nom');
            $table->integer('ponderation')->default(1);
            $table->string('type')->default('technique');
            $table->decimal('note_maximale', 8, 2)->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('critere_appel_offres');
    }
};
