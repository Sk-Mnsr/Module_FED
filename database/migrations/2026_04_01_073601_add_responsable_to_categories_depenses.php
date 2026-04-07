<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorie_depenses', function (Blueprint $table) {
            $table->enum('responsable', ['IT', 'Facilities', 'RH', 'ALL'])->nullable()->after('code');
        });

        Schema::table('sous_categorie_depenses', function (Blueprint $table) {
            $table->enum('responsable', ['IT', 'Facilities', 'RH', 'ALL'])->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('categorie_depenses', function (Blueprint $table) {
            $table->dropColumn('responsable');
        });

        Schema::table('sous_categorie_depenses', function (Blueprint $table) {
            $table->dropColumn('responsable');
        });
    }
};
