<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_cards', function (Blueprint $table) {
            $table->dropForeign(['agence_id']);
            $table->dropColumn('agence_id');
        });
    }
};
