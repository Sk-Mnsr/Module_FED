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
        Schema::table('fed_fournisseur_offres', function (Blueprint $table) {
            $table->string('rapport_qualite_prix')->nullable()->after('fournisseur');
            $table->string('delais_livraison')->nullable()->after('rapport_qualite_prix');
            $table->text('garanties_offertes')->nullable()->after('delais_livraison');
            $table->text('conformite_specifications')->nullable()->after('garanties_offertes');
            $table->text('conditions_paiement')->nullable()->after('conformite_specifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fed_fournisseur_offres', function (Blueprint $table) {
            $table->dropColumn([
                'rapport_qualite_prix',
                'delais_livraison',
                'garanties_offertes',
                'conformite_specifications',
                'conditions_paiement',
            ]);
        });
    }
};
