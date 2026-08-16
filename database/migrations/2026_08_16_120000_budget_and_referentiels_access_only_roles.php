<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{nom: string, module: string, description: string, legacy_slugs: list<string>}>
     */
    private const ACCESS_ROLES = [
        'budget' => [
            'nom' => 'Budget',
            'module' => 'budget',
            'description' => 'Autorise l’accès au module Budget. Les droits (consultation, ajouter, modifier…) se cochent ensuite.',
            'legacy_slugs' => ['n_plus_1', 'controle_de_gestion', 'daf', 'demandeur'],
        ],
        'referentiels' => [
            'nom' => 'Référentiels',
            'module' => 'config',
            'description' => 'Autorise l’accès au module Référentiels (typologies, catégories, banques, fournisseurs, types de dépense).',
            'legacy_slugs' => ['responsable_achats'],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_module')) {
            return;
        }

        $now = now();

        foreach (self::ACCESS_ROLES as $slug => $meta) {
            $roleId = $this->ensureRole($slug, $meta, $now);
            $this->grantToLegacyUsers($roleId, $meta['legacy_slugs'], $now);

            DB::table('role_module')->where('module', $meta['module'])->delete();

            DB::table('role_module')->insert([
                'role_id' => $roleId,
                'module' => $meta['module'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_module')) {
            return;
        }

        $now = now();

        foreach (self::ACCESS_ROLES as $slug => $meta) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            if ($roleId !== null) {
                DB::table('role_module')->where('role_id', $roleId)->where('module', $meta['module'])->delete();
                if (Schema::hasTable('user_role')) {
                    DB::table('user_role')->where('role_id', $roleId)->delete();
                }
                DB::table('roles')->where('id', $roleId)->delete();
            }

            $restore = $meta['module'] === 'budget'
                ? ['it', 'n_plus_1', 'controle_de_gestion', 'daf', 'demandeur']
                : ['it', 'admin'];

            foreach ($restore as $legacySlug) {
                $id = DB::table('roles')->where('slug', $legacySlug)->value('id');
                if ($id === null) {
                    continue;
                }
                DB::table('role_module')->updateOrInsert(
                    ['role_id' => $id, 'module' => $meta['module']],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        ModuleAccess::clearModuleRolesCache();
    }

    /**
     * @param  array{nom: string, module: string, description: string, legacy_slugs: list<string>}  $meta
     */
    private function ensureRole(string $slug, array $meta, $now): int
    {
        $roleId = DB::table('roles')->where('slug', $slug)->value('id');

        $payload = [
            'nom' => $meta['nom'],
            'module' => $meta['module'],
            'access_profile' => 'other',
            'description' => $meta['description'],
            'actif' => true,
            'updated_at' => $now,
        ];

        if ($roleId === null) {
            $insert = array_merge($payload, [
                'slug' => $slug,
                'name' => $slug,
                'label' => $meta['nom'],
                'is_super_admin' => false,
                'created_at' => $now,
            ]);

            return (int) DB::table('roles')->insertGetId($insert);
        }

        DB::table('roles')->where('id', $roleId)->update($payload);

        return (int) $roleId;
    }

    /**
     * @param  list<string>  $legacySlugs
     */
    private function grantToLegacyUsers(int $roleId, array $legacySlugs, $now): void
    {
        if ($legacySlugs === [] || ! Schema::hasTable('user_role')) {
            return;
        }

        $legacyRoleIds = DB::table('roles')->whereIn('slug', $legacySlugs)->pluck('id');
        if ($legacyRoleIds->isEmpty()) {
            return;
        }

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
};
