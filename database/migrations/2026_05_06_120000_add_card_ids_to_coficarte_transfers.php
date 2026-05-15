<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->json('card_ids')->nullable()->after('fin_plage');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->dropColumn('card_ids');
        });
    }
};
