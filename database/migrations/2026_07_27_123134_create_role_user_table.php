<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le pivot historique s'appelle `user_role`. On le réutilise (config RBAC)
     * et on ne crée `role_user` que s'il n'existe aucune des deux tables.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_role') || Schema::hasTable('role_user')) {
            return;
        }

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ne pas supprimer user_role (table métier historique).
        if (Schema::hasTable('role_user') && ! Schema::hasTable('user_role')) {
            Schema::dropIfExists('role_user');
        }
    }
};
