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
        Schema::table('appel_offres', function (Blueprint $table) {
            $table->string('cle_ouverture_hash')->nullable()->after('statut');
            $table->boolean('is_plis_ouverts')->default(false)->after('cle_ouverture_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appel_offres', function (Blueprint $table) {
            $table->dropColumn(['cle_ouverture_hash', 'is_plis_ouverts']);
        });
    }
};
