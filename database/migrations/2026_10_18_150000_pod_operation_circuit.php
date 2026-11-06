<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_operations', function (Blueprint $table) {
            $table->unsignedInteger('etape_index')->default(0)->after('statut');
            $table->string('etape_code', 40)->nullable()->after('etape_index');
            $table->string('etape_libelle')->nullable()->after('etape_code');
            $table->text('motif_decision')->nullable()->after('motif_annulation');
        });

        Schema::create('pod_operation_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_operation_id')->constrained('pod_operations')->cascadeOnDelete();
            $table->unsignedInteger('etape_index')->default(0);
            $table->string('ecran_code', 40)->nullable();
            $table->string('ecran_libelle')->nullable();
            /** soumise|validee|suspendue|reprise|rejetee|annulee */
            $table->string('action', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index(['pod_operation_id', 'created_at']);
        });

        Schema::create('pod_operation_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pod_operation_id')->constrained('pod_operations')->cascadeOnDelete();
            $table->string('champ_code', 80)->nullable();
            $table->unsignedInteger('etape_index')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index(['pod_operation_id', 'champ_code']);
        });

        DB::table('pod_operations')
            ->where('statut', 'brouillon')
            ->update(['statut' => 'en_cours', 'etape_index' => 1, 'etape_libelle' => 'Validation']);
    }

    public function down(): void
    {
        DB::table('pod_operations')
            ->where('statut', 'en_cours')
            ->update(['statut' => 'brouillon']);

        Schema::dropIfExists('pod_operation_pieces');
        Schema::dropIfExists('pod_operation_etapes');

        Schema::table('pod_operations', function (Blueprint $table) {
            $table->dropColumn(['etape_index', 'etape_code', 'etape_libelle', 'motif_decision']);
        });
    }
};
