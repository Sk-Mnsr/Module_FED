<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fed_fournisseur_offres', function (Blueprint $table) {
            $table->string('acompte_requis', 3)->nullable()->after('conformite_reglementaire'); // OUI / NON
            $table->decimal('pourcentage_acompte', 5, 2)->nullable()->after('acompte_requis');
            $table->unsignedBigInteger('fournisseur_id')->nullable()->after('fournisseur');
        });
    }

    public function down(): void
    {
        Schema::table('fed_fournisseur_offres', function (Blueprint $table) {
            $table->dropColumn(['acompte_requis', 'pourcentage_acompte', 'fournisseur_id']);
        });
    }
};
