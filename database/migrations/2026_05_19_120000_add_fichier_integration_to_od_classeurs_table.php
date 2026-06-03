<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->string('fichier_integration_path')->nullable()->after('numero_batch');
            $table->string('fichier_integration_original_name')->nullable()->after('fichier_integration_path');
        });
    }

    public function down(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->dropColumn(['fichier_integration_path', 'fichier_integration_original_name']);
        });
    }
};
