<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->timestamp('controle_at')->nullable()->after('archived_at');
            $table->foreignId('controle_by_user_id')
                ->nullable()
                ->after('controle_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('controle_by_user_id');
            $table->dropColumn('controle_at');
        });
    }
};
