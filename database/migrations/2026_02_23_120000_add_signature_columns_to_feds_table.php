<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->text('requester_signature')->nullable()->after('submitted_at');
            $table->timestamp('requester_signed_at')->nullable()->after('requester_signature');
            $table->text('n1_signature')->nullable()->after('n1_action_at');
            $table->timestamp('n1_signed_at')->nullable()->after('n1_signature');
        });
    }

    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropColumn(['requester_signature', 'requester_signed_at', 'n1_signature', 'n1_signed_at']);
        });
    }
};
