<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('matricule')->unique()->nullable();
            $table->string('prenom')->nullable();
            $table->string('nom')->nullable();
            $table->string('fonction')->nullable();
            $table->string('departement')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('telephone')->nullable();
            $table->string('site')->nullable();
            $table->string('type_contrat')->nullable();
            $table->string('statut')->nullable();
            $table->foreignId('n_plus_1_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('n_plus_2_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
