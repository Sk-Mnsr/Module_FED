<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->foreignId('integrated_by_user_id')->nullable()->after('integrated_at')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_checker_user_id')->nullable()->after('integrated_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by_user_id')->nullable()->after('assigned_checker_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('integrated_by_user_id');
            $table->dropConstrainedForeignId('assigned_checker_user_id');
            $table->dropConstrainedForeignId('validated_by_user_id');
            $table->dropColumn('validated_at');
        });
    }
};
