<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('sous_categorie_id')->nullable()->constrained('sous_categories')->nullOnDelete()->after('responsable');
            $table->foreignId('type_depense_id')->nullable()->constrained('type_depenses')->nullOnDelete()->after('sous_categorie_id');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['sous_categorie_id']);
            $table->dropForeign(['type_depense_id']);
            $table->dropColumn(['sous_categorie_id', 'type_depense_id']);
        });
    }
};
