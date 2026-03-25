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
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->string('annee_comptable')->nullable()->change();
            $table->string('mois_comptable')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->integer('annee_comptable')->nullable()->change();
            $table->integer('mois_comptable')->nullable()->change();
        });
    }
};
