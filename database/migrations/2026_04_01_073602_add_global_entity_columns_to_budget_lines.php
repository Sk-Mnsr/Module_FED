<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->boolean('is_global')->default(true)->after('id');
            $table->foreignId('global_line_id')->nullable()->constrained('budget_lines')->nullOnDelete()->after('is_global');
            $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete()->after('global_line_id');
            $table->enum('responsable', ['IT', 'Facilities', 'RH'])->nullable()->after('agence_id');
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropForeign(['global_line_id']);
            $table->dropForeign(['agence_id']);
            $table->dropColumn(['is_global', 'global_line_id', 'agence_id', 'responsable']);
        });
    }
};
