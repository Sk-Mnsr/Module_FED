<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'department_id')) {
                $table->dropForeign(['department_id']);
            }
            if (Schema::hasColumn('profiles', 'n_plus_1_id')) {
                $table->dropForeign(['n_plus_1_id']);
            }
            if (Schema::hasColumn('profiles', 'n_plus_2_id')) {
                $table->dropForeign(['n_plus_2_id']);
            }
        });

        Schema::dropIfExists('profiles');
    }

    public function down(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('matricule')->unique()->nullable();
            $table->string('prenom')->nullable();
            $table->string('nom')->nullable();
            $table->string('fonction')->nullable();
            $table->string('departement')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('telephone')->nullable();
            $table->string('site')->nullable();
            $table->string('type_contrat')->nullable();
            $table->string('statut')->nullable();
            $table->foreignId('n_plus_1_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('n_plus_2_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('departments')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('departement')->constrained('departments')->nullOnDelete();
            });
        }
    }
};
