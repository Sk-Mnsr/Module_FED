<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('typologie_depenses', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('categorie_depenses', function (Blueprint $table) {
            $table->id();
            $table->string('categorie');
            $table->string('code')->unique();
            $table->timestamps();
            $table->unique(['categorie']);
        });

        Schema::create('rubrique_depenses', function (Blueprint $table) {
            $table->id();
            $table->string('libelle')->unique();
            $table->timestamps();
        });

        Schema::create('sous_categorie_depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_depense_id')->constrained('categorie_depenses')->cascadeOnDelete();
            $table->string('sous_categorie');
            $table->string('code', 10);
            $table->timestamps();
            $table->unique(['categorie_depense_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sous_categorie_depenses');
        Schema::dropIfExists('typologie_depenses');
        Schema::dropIfExists('categorie_depenses');
        Schema::dropIfExists('rubrique_depenses');
    }
};
