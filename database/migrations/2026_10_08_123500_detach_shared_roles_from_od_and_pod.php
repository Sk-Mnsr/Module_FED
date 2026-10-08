<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereNotIn('slug', ['it', 'admin'])
            ->pluck('module', 'id');

        foreach ($roleIds as $roleId => $primaryModule) {
            DB::table('role_module')
                ->where('role_id', $roleId)
                ->whereIn('module', ['od', 'pod'])
                ->when(
                    filled($primaryModule),
                    fn ($query) => $query->where('module', '!=', $primaryModule),
                )
                ->delete();
        }

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        $links = [
            'od' => ['daf'],
            'pod' => ['ops', 'finance', 'controle_de_gestion', 'daf'],
        ];

        $now = now();

        foreach ($links as $module => $slugs) {
            foreach ($slugs as $slug) {
                $roleId = DB::table('roles')->where('slug', $slug)->value('id');
                if ($roleId === null) {
                    continue;
                }

                DB::table('role_module')->updateOrInsert(
                    ['role_id' => $roleId, 'module' => $module],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        ModuleAccess::clearModuleRolesCache();
    }
};
