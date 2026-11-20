<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_produits', function (Blueprint $table) {
            /** Code du champ dont la valeur choisit les tranches (critère). */
            $table->string('bareme_champ', 80)->nullable()->after('mode_frais');
            /** Code du champ dont la valeur donne le taux de taxe (0, 10, 18…). */
            $table->string('taxe_champ', 80)->nullable()->after('taux_taf');
        });

        Schema::table('pod_tranches_frais', function (Blueprint $table) {
            $table->string('critere', 80)->nullable()->after('libelle');
        });
    }

    public function down(): void
    {
        Schema::table('pod_tranches_frais', function (Blueprint $table) {
            $table->dropColumn('critere');
        });

        Schema::table('pod_produits', function (Blueprint $table) {
            $table->dropColumn(['bareme_champ', 'taxe_champ']);
        });
    }
};
