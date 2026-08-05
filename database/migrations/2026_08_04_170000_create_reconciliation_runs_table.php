<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partenaire_id')->constrained('partenaires')->cascadeOnDelete();
            $table->string('partenaire_identifiant', 100);
            $table->string('partenaire_nom');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('mode', 32)->default('two_pointers');
            $table->decimal('taux_reussite', 8, 2)->nullable();
            $table->unsignedInteger('reconcilies')->nullable();
            $table->unsignedInteger('total')->nullable();
            $table->json('taux_json')->nullable();
            $table->json('summary_json')->nullable();
            $table->string('excel_path')->nullable();
            $table->string('excel_filename')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['partenaire_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
