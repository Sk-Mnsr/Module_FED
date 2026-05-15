<?php

use App\Support\CoficarteEncaissementCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->string('encaissement_code', 32)->nullable()->unique()->after('payment_status');
            $table->string('bordereau_caisse_path')->nullable()->after('encaissement_code');
        });

        Schema::table('coficarte_recharges', function (Blueprint $table) {
            $table->string('encaissement_code', 32)->nullable()->unique()->after('payment_status');
            $table->string('bordereau_caisse_path')->nullable()->after('encaissement_code');
        });

        DB::table('coficarte_sales')
            ->where('payment_status', 'en_attente')
            ->whereNull('encaissement_code')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                DB::table('coficarte_sales')
                    ->where('id', $row->id)
                    ->update(['encaissement_code' => CoficarteEncaissementCode::generateForVente()]);
            });

        DB::table('coficarte_recharges')
            ->where('payment_status', 'en_attente')
            ->whereNull('encaissement_code')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                DB::table('coficarte_recharges')
                    ->where('id', $row->id)
                    ->update(['encaissement_code' => CoficarteEncaissementCode::generateForRecharge()]);
            });
    }

    public function down(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->dropColumn(['encaissement_code', 'bordereau_caisse_path']);
        });

        Schema::table('coficarte_recharges', function (Blueprint $table) {
            $table->dropColumn(['encaissement_code', 'bordereau_caisse_path']);
        });
    }
};
