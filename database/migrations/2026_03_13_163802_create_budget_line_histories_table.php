<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_line_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->foreignId('to_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->string('action'); // 'change_line' | 'transfer_amount'
            $table->decimal('montant_transfere', 15, 2)->nullable();
            $table->decimal('from_montant_before', 15, 2)->nullable();
            $table->decimal('from_montant_after', 15, 2)->nullable();
            $table->decimal('to_montant_before', 15, 2)->nullable();
            $table->decimal('to_montant_after', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_histories');
    }
};

