<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('demande_approvisionnement_items', function (Blueprint $blueprint) {
            $blueprint->string('designation')->after('id')->nullable();
            $blueprint->unsignedBigInteger('article_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('demande_approvisionnement_items', function (Blueprint $blueprint) {
            $blueprint->dropColumn('designation');
            $blueprint->unsignedBigInteger('article_id')->nullable(false)->change();
        });
    }
};
