<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['department_id', 'year']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('label');
            $table->string('type')->nullable();
            $table->foreignId('categorie_depense_id')->nullable()->constrained('categorie_depenses')->nullOnDelete();
            $table->foreignId('sous_categorie_depense_id')->nullable()->constrained('sous_categorie_depenses')->nullOnDelete();
            $table->string('rubrique')->nullable();
            $table->string('sous_rubrique')->nullable();
            $table->decimal('capex', 15, 2)->default(0);
            $table->decimal('opex', 15, 2)->default(0);
            $table->decimal('montant_estime', 15, 2)->default(0);
            $table->decimal('montant_consomme', 15, 2)->default(0);
            $table->decimal('montant_stock', 15, 2)->default(0);
            $table->text('date_souhaitee_execution')->nullable();
            $table->text('justification')->nullable();
            $table->timestamps();
            $table->index(['budget_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
