<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_module')) {
            return;
        }

        $now = now();

        $roleId = DB::table('roles')->where('slug', 'reconciliation')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'nom' => 'Réconciliation',
                'slug' => 'reconciliation',
                'name' => 'reconciliation',
                'label' => 'Réconciliation',
                'module' => 'reconciliation',
                'access_profile' => 'other',
                'description' => 'Autorise l’accès au module Réconciliation Flexcube (sans rôle métier supplémentaire).',
                'actif' => true,
                'is_super_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('roles')->where('id', $roleId)->update([
                'nom' => 'Réconciliation',
                'module' => 'reconciliation',
                'access_profile' => 'other',
                'description' => 'Autorise l’accès au module Réconciliation Flexcube (sans rôle métier supplémentaire).',
                'actif' => true,
                'updated_at' => $now,
            ]);
        }

        // Conserver l’accès pour les utilisateurs déjà couverts via OPS / Finance / etc.
        if (Schema::hasTable('user_role')) {
            $legacyRoleIds = DB::table('roles')
                ->whereIn('slug', ['ops', 'finance', 'controle_de_gestion', 'daf'])
                ->pluck('id');

            $userIds = DB::table('user_role')
                ->whereIn('role_id', $legacyRoleIds)
                ->pluck('user_id')
                ->unique();

            foreach ($userIds as $userId) {
                $exists = DB::table('user_role')
                    ->where('user_id', $userId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (! $exists) {
                    DB::table('user_role')->insert([
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        DB::table('role_module')->where('module', 'reconciliation')->delete();

        DB::table('role_module')->insert([
            'role_id' => $roleId,
            'module' => 'reconciliation',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_module')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'reconciliation')->value('id');

        if ($roleId !== null) {
            DB::table('role_module')->where('role_id', $roleId)->where('module', 'reconciliation')->delete();

            if (Schema::hasTable('user_role')) {
                DB::table('user_role')->where('role_id', $roleId)->delete();
            }

            DB::table('roles')->where('id', $roleId)->delete();
        }

        $now = now();
        foreach (['it', 'ops', 'finance', 'controle_de_gestion', 'daf'] as $slug) {
            $id = DB::table('roles')->where('slug', $slug)->value('id');
            if ($id === null) {
                continue;
            }

            DB::table('role_module')->updateOrInsert(
                ['role_id' => $id, 'module' => 'reconciliation'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        ModuleAccess::clearModuleRolesCache();
    }
};
