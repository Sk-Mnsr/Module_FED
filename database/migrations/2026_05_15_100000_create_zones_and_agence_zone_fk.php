<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('zone_user', function (Blueprint $table) {
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['zone_id', 'user_id']);
        });

        Schema::table('agences', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('chef_agence_user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn('zone_id');
        });

        Schema::dropIfExists('zone_user');
        Schema::dropIfExists('zones');
    }
};
