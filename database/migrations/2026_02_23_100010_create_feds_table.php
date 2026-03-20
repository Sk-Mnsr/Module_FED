<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feds', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->string('demandeur')->nullable();
            $table->string('department')->nullable();
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->string('fonction')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('beneficiaire')->nullable();
            $table->text('motive')->nullable();
            $table->decimal('estimated_total', 15, 2)->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending_validation');
            $table->timestamp('submitted_at')->nullable();
            $table->text('n1_comment')->nullable();
            $table->timestamp('n1_action_at')->nullable();
            $table->timestamps();
            $table->index(['requester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feds');
    }
};
