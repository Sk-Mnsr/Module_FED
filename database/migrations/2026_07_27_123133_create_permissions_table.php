<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables RBAC Maravel manquantes après l'install partielle
     * (seuls roles / role_user avaient été publiés).
     */
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            return;
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('subject');
            $table->string('label')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['action', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
