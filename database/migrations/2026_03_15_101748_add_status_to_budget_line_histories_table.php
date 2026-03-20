<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_line_histories', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('note');
            // 'pending', 'approved', 'rejected'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_line_histories', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
