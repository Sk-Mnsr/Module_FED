<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->string('numero_piece')->nullable()->after('numero_batch');
            $table->string('statut')->default('brouillon')->after('numero_piece');
            $table->timestamp('integrated_at')->nullable()->after('statut');
            $table->date('archive_date')->nullable()->after('integrated_at');
            $table->unsignedSmallInteger('integration_status_code')->nullable()->after('archive_date');
            $table->string('piece_pdf_path')->nullable()->after('integration_status_code');
        });
    }

    public function down(): void
    {
        Schema::table('od_classeurs', function (Blueprint $table) {
            $table->dropColumn([
                'numero_piece',
                'statut',
                'integrated_at',
                'archive_date',
                'integration_status_code',
                'piece_pdf_path',
            ]);
        });
    }
};
