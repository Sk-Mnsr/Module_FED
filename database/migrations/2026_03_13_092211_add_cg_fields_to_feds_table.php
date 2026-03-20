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
        Schema::table('feds', function (Blueprint $table) {
            $table->enum('cg_budget_status', ['in_budget', 'out_of_budget'])->nullable()->after('facilities_action_at');
            $table->text('cg_comment')->nullable()->after('cg_budget_status');
            $table->timestamp('cg_action_at')->nullable()->after('cg_comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropColumn(['cg_budget_status', 'cg_comment', 'cg_action_at']);
        });
    }
};
