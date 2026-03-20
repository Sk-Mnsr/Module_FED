<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fed_budget_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fed_id', 'budget_line_id']);
        });

        // Migrer les données existantes
        DB::table('feds')->whereNotNull('budget_line_id')->orderBy('id')->chunk(100, function ($feds) {
            foreach ($feds as $fed) {
                DB::table('fed_budget_line')->insert([
                    'fed_id' => $fed->id,
                    'budget_line_id' => $fed->budget_line_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('feds', function (Blueprint $table) {
            $table->dropForeign(['budget_line_id']);
            $table->dropColumn('budget_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->foreignId('budget_line_id')->nullable()->after('department')->constrained('budget_lines')->nullOnDelete();
        });

        // Restaurer la première ligne budgétaire
        $pivots = DB::table('fed_budget_line')->orderBy('fed_id')->orderBy('id')->get();
        $fedIds = [];
        foreach ($pivots as $p) {
            if (!isset($fedIds[$p->fed_id])) {
                DB::table('feds')->where('id', $p->fed_id)->update(['budget_line_id' => $p->budget_line_id]);
                $fedIds[$p->fed_id] = true;
            }
        }

        Schema::dropIfExists('fed_budget_line');
    }
};
