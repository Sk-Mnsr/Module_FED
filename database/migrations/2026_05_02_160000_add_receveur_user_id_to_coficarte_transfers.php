<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->foreignId('receveur_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->dropForeign(['receveur_user_id']);
            $table->dropColumn('receveur_user_id');
        });
    }
};
