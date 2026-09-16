<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->timestamp('controle_anomalie_ack_at')->nullable()->after('controle_anomalie_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->dropColumn('controle_anomalie_ack_at');
        });
    }
};
