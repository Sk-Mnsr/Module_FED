<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter responsable à la table articles
        Schema::table('articles', function (Blueprint $table) {
            $table->enum('responsable', ['IT', 'Facilities', 'RH', 'ALL'])->default('ALL')->after('code');
        });

        // Supprimer responsable de la table type_depenses
        Schema::table('type_depenses', function (Blueprint $table) {
            $table->dropColumn('responsable');
        });
    }

    public function down(): void
    {
        // Remettre responsable dans type_depenses
        Schema::table('type_depenses', function (Blueprint $table) {
            $table->enum('responsable', ['IT', 'Facilities', 'RH', 'ALL'])->default('ALL');
        });

        // Supprimer responsable de articles
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('responsable');
        });
    }
};
