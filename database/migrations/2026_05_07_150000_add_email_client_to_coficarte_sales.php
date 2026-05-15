<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->string('email_client', 191)->nullable()->after('telephone_client');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->dropColumn('email_client');
        });
    }
};
