<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->text('n1_avis')->nullable()->after('n1_comment');
        });
    }

    public function down(): void
    {
        Schema::table('feds', function (Blueprint $table) {
            $table->dropColumn('n1_avis');
        });
    }
};
