<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->string('numero_lot', 128)->nullable()->after('numero_carte');
            $table->index('numero_lot');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->dropIndex(['numero_lot']);
            $table->dropColumn('numero_lot');
        });
    }
};
