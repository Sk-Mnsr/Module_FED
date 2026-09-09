<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $opsId = DB::table('roles')->where('slug', 'monetique_ops')->value('id');
        if ($opsId === null) {
            return;
        }

        $monetiqueId = DB::table('roles')->where('slug', 'monetique')->value('id');
        $now = now();

        if ($monetiqueId !== null && Schema::hasTable('user_role')) {
            $userIds = DB::table('user_role')->where('role_id', $opsId)->pluck('user_id');
            foreach ($userIds as $userId) {
                $already = DB::table('user_role')
                    ->where('user_id', $userId)
                    ->where('role_id', $monetiqueId)
                    ->exists();
                if (! $already) {
                    DB::table('user_role')->insert([
                        'user_id' => $userId,
                        'role_id' => $monetiqueId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            DB::table('user_role')->where('role_id', $opsId)->delete();
        }

        if (Schema::hasTable('role_module')) {
            DB::table('role_module')->where('role_id', $opsId)->delete();
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('role_id', $opsId)->delete();
        }

        DB::table('roles')->where('id', $opsId)->delete();
        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        if (DB::table('roles')->where('slug', 'monetique_ops')->exists()) {
            return;
        }

        $now = now();
        $payload = [
            'nom' => 'Monétique Ops',
            'slug' => 'monetique_ops',
            'module' => 'monetique',
            'access_profile' => 'monetique',
            'description' => 'Accès au module Monétique (Coficarte, Cartes, Transferts, Ventes)',
            'actif' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('roles', 'name')) {
            $payload['name'] = 'monetique_ops';
        }
        if (Schema::hasColumn('roles', 'label')) {
            $payload['label'] = 'Monétique Ops';
        }
        if (Schema::hasColumn('roles', 'is_super_admin')) {
            $payload['is_super_admin'] = false;
        }

        $roleId = DB::table('roles')->insertGetId($payload);

        if (Schema::hasTable('role_module')) {
            DB::table('role_module')->updateOrInsert(
                ['role_id' => $roleId, 'module' => 'monetique'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        ModuleAccess::clearModuleRolesCache();
    }
};
