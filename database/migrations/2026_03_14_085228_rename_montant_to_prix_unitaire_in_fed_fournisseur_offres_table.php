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
            $table->renameColumn('montant', 'prix_unitaire');
            $table->foreignId('fed_item_id')->nullable()->after('fed_id')->constrained('fed_items')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fed_fournisseur_offres', function (Blueprint $table) {
            $table->dropForeign(['fed_item_id']);
            $table->dropColumn('fed_item_id');
            $table->renameColumn('prix_unitaire', 'montant');
        });
    }
};
