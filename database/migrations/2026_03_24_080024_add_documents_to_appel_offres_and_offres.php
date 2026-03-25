<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appel_offres', function (Blueprint $table) {
            $table->string('dao_path')->nullable()->after('type_publication');
            $table->string('cahier_charges_path')->nullable()->after('dao_path');
        });

        Schema::table('offres', function (Blueprint $table) {
            $table->string('rccm_path')->nullable()->after('note_totale');
            $table->string('ninea_path')->nullable()->after('rccm_path');
            $table->string('fiche_technique_path')->nullable()->after('ninea_path');
            $table->string('references_path')->nullable()->after('fiche_technique_path');
            $table->string('contact_nom')->nullable()->after('references_path');
            $table->string('contact_telephone')->nullable()->after('contact_nom');
        });
    }

    public function down(): void
    {
        Schema::table('appel_offres', function (Blueprint $table) {
            $table->dropColumn(['dao_path', 'cahier_charges_path']);
        });

        Schema::table('offres', function (Blueprint $table) {
            $table->dropColumn([
                'rccm_path', 'ninea_path', 'fiche_technique_path', 'references_path', 'contact_nom', 'contact_telephone'
            ]);
        });
    }
};
