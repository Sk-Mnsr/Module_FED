<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->timestamp('controle_anomalie_at')->nullable()->after('controle_by_user_id');
            $table->text('controle_anomalie_motif')->nullable()->after('controle_anomalie_at');
            $table->foreignId('controle_anomalie_by_user_id')
                ->nullable()
                ->after('controle_anomalie_motif')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('controle_anomalie_by_user_id');
            $table->dropColumn(['controle_anomalie_at', 'controle_anomalie_motif']);
        });
    }
};
