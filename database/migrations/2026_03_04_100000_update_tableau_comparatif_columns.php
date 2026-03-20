<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fed_fournisseur_offres', function (Blueprint $table) {
            $table->string('conformite_reglementaire', 10)->nullable()->after('conformite_specifications');
        });

        Schema::create('fed_fournisseur_offre_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fed_fournisseur_offre_id')->constrained('fed_fournisseur_offres')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('fed_fournisseur_offres', function (Blueprint $table) {
            $table->dropColumn('conformite_reglementaire');
        });
        Schema::dropIfExists('fed_fournisseur_offre_attachments');
    }
};
