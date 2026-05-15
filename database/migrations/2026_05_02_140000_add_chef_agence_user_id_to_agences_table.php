<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->foreignId('chef_agence_user_id')->nullable()->after('code')->constrained('users')->nullOnDelete();
        });

        Schema::table('agences', function (Blueprint $table) {
            $table->unique('chef_agence_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropUnique(['chef_agence_user_id']);
        });

        Schema::table('agences', function (Blueprint $table) {
            $table->dropForeign(['chef_agence_user_id']);
            $table->dropColumn('chef_agence_user_id');
        });
    }
};
