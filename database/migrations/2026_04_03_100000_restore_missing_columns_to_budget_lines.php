<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_lines', 'type')) {
                $table->string('type')->nullable()->after('label');
            }
            if (!Schema::hasColumn('budget_lines', 'categorie_depense_id')) {
                $table->foreignId('categorie_depense_id')->nullable()->constrained('categorie_depenses')->nullOnDelete()->after('type');
            }
            if (!Schema::hasColumn('budget_lines', 'sous_categorie_depense_id')) {
                $table->foreignId('sous_categorie_depense_id')->nullable()->constrained('sous_categorie_depenses')->nullOnDelete()->after('categorie_depense_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn(['type', 'categorie_depense_id', 'sous_categorie_depense_id']);
        });
    }
};
