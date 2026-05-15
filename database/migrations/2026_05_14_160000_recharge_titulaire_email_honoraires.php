<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_recharges', function (Blueprint $table) {
            $table->string('titulaire_carte', 255)->nullable()->after('montant');
            $table->string('email_titulaire', 191)->nullable()->after('titulaire_carte');
            $table->unsignedBigInteger('honoraire_chargement')->default(0)->after('email_titulaire');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_recharges', function (Blueprint $table) {
            $table->dropColumn(['titulaire_carte', 'email_titulaire', 'honoraire_chargement']);
        });
    }
};
