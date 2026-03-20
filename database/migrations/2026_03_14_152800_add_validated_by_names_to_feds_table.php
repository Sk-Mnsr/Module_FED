<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->string('n1_validated_by')->nullable()->after('n1_action_at');
            $table->string('achats_validated_by')->nullable()->after('achats_action_at');
            $table->string('facilities_validated_by')->nullable()->after('facilities_action_at');
            $table->string('daf_validated_by')->nullable()->after('daf_action_at');
            $table->string('dga_validated_by')->nullable()->after('dga_action_at');
            $table->string('cg_validated_by')->nullable()->after('cg_action_at');
        });
    }

    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropColumn([
                'n1_validated_by',
                'achats_validated_by',
                'facilities_validated_by',
                'daf_validated_by',
                'dga_validated_by',
                'cg_validated_by',
            ]);
        });
    }
};
