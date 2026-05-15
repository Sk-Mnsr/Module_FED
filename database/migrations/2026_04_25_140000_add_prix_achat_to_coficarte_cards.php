<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->unsignedInteger('prix_achat')->default(0)->after('prix_vente');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->dropColumn('prix_achat');
        });
    }
};
