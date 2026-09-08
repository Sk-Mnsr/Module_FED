<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_runs', function (Blueprint $table) {
            $table->json('source_files')->nullable()->after('excel_filename');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_runs', function (Blueprint $table) {
            $table->dropColumn('source_files');
        });
    }
};
