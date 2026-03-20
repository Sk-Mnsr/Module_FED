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
            $table->text('achats_comment')->nullable()->after('n1_signed_at');
            $table->timestamp('achats_action_at')->nullable()->after('achats_comment');
            $table->text('achats_signature')->nullable()->after('achats_action_at');
            $table->timestamp('achats_signed_at')->nullable()->after('achats_signature');
            $table->text('facilities_comment')->nullable()->after('achats_signed_at');
            $table->timestamp('facilities_action_at')->nullable()->after('facilities_comment');
            $table->text('facilities_signature')->nullable()->after('facilities_action_at');
            $table->timestamp('facilities_signed_at')->nullable()->after('facilities_signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropColumn([
                'achats_comment', 'achats_action_at', 'achats_signature', 'achats_signed_at',
                'facilities_comment', 'facilities_action_at', 'facilities_signature', 'facilities_signed_at',
            ]);
        });
    }
};
