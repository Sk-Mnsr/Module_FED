<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fed_id')->constrained('feds')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('total_price', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('fed_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fed_id')->constrained('feds')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fed_attachments');
        Schema::dropIfExists('fed_items');
    }
};
