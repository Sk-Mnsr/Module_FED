<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_operations', function (Blueprint $table) {
            $table->string('compte_client', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pod_operations', function (Blueprint $table) {
            $table->string('compte_client', 50)->nullable(false)->default('')->change();
        });
    }
};
