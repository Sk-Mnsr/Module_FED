<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_data_tables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pod_data_table_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_data_table_id')->constrained('pod_data_tables')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('libelle');
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['pod_data_table_id', 'code']);
            $table->index(['pod_data_table_id', 'sort_order']);
        });

        Schema::create('pod_data_table_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_data_table_id')->constrained('pod_data_tables')->cascadeOnDelete();
            $table->json('data');
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pod_data_table_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_data_table_rows');
        Schema::dropIfExists('pod_data_table_columns');
        Schema::dropIfExists('pod_data_tables');
    }
};
