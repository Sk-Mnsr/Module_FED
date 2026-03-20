<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->text('daf_comment')->nullable()->after('facilities_signed_at');
            $table->timestamp('daf_action_at')->nullable()->after('daf_comment');
            $table->text('daf_signature')->nullable()->after('daf_action_at');
            $table->timestamp('daf_signed_at')->nullable()->after('daf_signature');
            $table->text('dga_comment')->nullable()->after('daf_signed_at');
            $table->timestamp('dga_action_at')->nullable()->after('dga_comment');
            $table->text('dga_signature')->nullable()->after('dga_action_at');
            $table->timestamp('dga_signed_at')->nullable()->after('dga_signature');
        });
    }

    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropColumn([
                'daf_comment', 'daf_action_at', 'daf_signature', 'daf_signed_at',
                'dga_comment', 'dga_action_at', 'dga_signature', 'dga_signed_at',
            ]);
        });
    }
};
