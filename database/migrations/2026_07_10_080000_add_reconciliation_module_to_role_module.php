<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const ROLE_SLUGS = ['it', 'ops', 'finance', 'controle_de_gestion', 'daf'];

    public function up(): void
    {
        if (! Schema::hasTable('role_module')) {
            return;
        }

        $now = now();

        foreach (self::ROLE_SLUGS as $slug) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            if ($roleId === null) {
                continue;
            }

            DB::table('role_module')->updateOrInsert(
                ['role_id' => $roleId, 'module' => 'reconciliation'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_module')) {
            return;
        }

        DB::table('role_module')->where('module', 'reconciliation')->delete();
        ModuleAccess::clearModuleRolesCache();
    }
};
