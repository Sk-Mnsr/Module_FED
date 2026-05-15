<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->string('gpt_id', 128)->nullable()->after('fiche_enrolement_path');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->dropColumn('gpt_id');
        });
    }
};
