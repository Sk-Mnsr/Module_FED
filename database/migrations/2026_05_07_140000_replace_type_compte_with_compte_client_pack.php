<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->string('compte_client_pack', 32)->nullable()->after('numero_compte_client');
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::table('coficarte_sales')
                ->whereNotNull('numero_compte_client')
                ->where('numero_compte_client', '!=', '')
                ->update(['compte_client_pack' => 'in_pack']);

            DB::table('coficarte_sales')
                ->whereNull('compte_client_pack')
                ->update(['compte_client_pack' => 'hors_pack']);
        } else {
            foreach (DB::table('coficarte_sales')->select('id', 'numero_compte_client')->get() as $row) {
                $num = trim((string) ($row->numero_compte_client ?? ''));
                DB::table('coficarte_sales')->where('id', $row->id)->update([
                    'compte_client_pack' => $num !== '' ? 'in_pack' : 'hors_pack',
                ]);
            }
        }

        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->dropColumn('type_compte');
        });
    }

    public function down(): void
    {
        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->string('type_compte', 64)->nullable()->after('numero_compte_client');
        });

        DB::table('coficarte_sales')->update([
            'type_compte' => null,
        ]);

        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->dropColumn('compte_client_pack');
        });
    }
};
