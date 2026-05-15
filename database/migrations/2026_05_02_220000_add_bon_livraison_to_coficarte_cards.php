<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->string('reference_bon_livraison', 128)->nullable()->after('facture_path');
            $table->string('bon_livraison_path')->nullable()->after('reference_bon_livraison');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->dropColumn(['reference_bon_livraison', 'bon_livraison_path']);
        });
    }
};
