<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('od_classeurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nom_classeur');
            $table->date('date_valeur');
            $table->string('numero_batch');
            $table->timestamps();
        });

        Schema::create('od_classeur_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('od_classeur_id')->constrained('od_classeurs')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('original_name');
            $table->string('storage_path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('od_classeur_pieces');
        Schema::dropIfExists('od_classeurs');
    }
};
