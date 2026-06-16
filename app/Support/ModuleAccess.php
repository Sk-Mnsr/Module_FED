<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ModuleAccess
{
    private const CACHE_KEY = 'module_access.role_slugs_by_module';

    /** @var array<string, string> */
    private const LEGACY_ROLE_ALIASES = [
        'chef_agence_ca' => 'ca',
        'charge_clientele_cc' => 'cc',
        'responsable_monetique' => 'monetique',
    ];

    /** @var array<string, string> */
    private const PRIMARY_MODULE_OVERRIDES = [
        'it' => 'config',
        'admin' => 'config',
        'controle_de_gestion' => 'ecritures',
        'responsable_stock' => 'stock',
        'ops' => 'od',
        'finance' => 'od',
        'monetique' => 'monetique',
        'monetique_ops' => 'monetique',
        'ca' => 'monetique',
        'cc' => 'monetique',
        'caissier' => 'monetique',
    ];

    /** @var array<string, string> */
    private const MODULE_LABELS = [
        'fed' => 'FED',
        'budget' => 'Budget',
        'stock' => 'Gestion de stock',
        'ecritures' => 'Écritures comptables',
        'monetique' => 'Monétique',
        'od' => 'Opérations diverses',
        'config' => 'Configuration',
    ];

    public static function clearModuleRolesCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function normalizeRoleSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        return self::LEGACY_ROLE_ALIASES[$slug] ?? $slug;
    }

    /**
     * @return list<string>
     */
    public static function normalizedRoleSlugs(User $user): array
    {
        $user->loadMissing('roles');

        return $user->roles
            ->pluck('slug')
            ->map(fn (string $slug) => self::normalizeRoleSlug($slug))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function isAdminUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->profile === 'admin') {
            return true;
        }

        return count(array_intersect(self::normalizedRoleSlugs($user), ['it', 'admin'])) > 0;
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    public static function userHasAnyRole(User $user, array $roleSlugs): bool
    {
        if (self::isAdminUser($user)) {
            return true;
        }

        $userSlugs = self::normalizedRoleSlugs($user);
        $wanted = array_map(
            fn (string $slug) => self::normalizeRoleSlug($slug),
            $roleSlugs,
        );

        return count(array_intersect($userSlugs, $wanted)) > 0;
    }

    public static function userCanAccess(?User $user, string $module): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::isAdminUser($user)) {
            return true;
        }

        if (! isset(self::MODULE_LABELS[$module])) {
            return false;
        }

        return self::userHasAnyRole($user, self::moduleRoleSlugs($module));
    }

    /**
     * @return list<string>
     */
    public static function accessibleModuleKeys(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::MODULE_LABELS),
            fn (string $key) => self::userCanAccess($user, $key),
        ));
    }

    /**
     * @return array<string, array{label: string, roles: list<string>}>
     */
    public static function modules(): array
    {
        $byModule = self::roleSlugsByModule();

        return array_map(
            fn (string $key, string $label) => [
                'label' => $label,
                'roles' => $byModule[$key] ?? [],
            ],
            array_keys(self::MODULE_LABELS),
            self::MODULE_LABELS,
        );
    }

    /**
     * @return list<string>
     */
    public static function moduleRoleSlugs(string $module): array
    {
        $byModule = self::roleSlugsByModule();

        return $byModule[$module] ?? [];
    }

    public static function primaryModuleForRoleSlug(?string $slug): ?string
    {
        $slug = self::normalizeRoleSlug($slug);

        if ($slug === null) {
            return null;
        }

        if (isset(self::PRIMARY_MODULE_OVERRIDES[$slug])) {
            return self::PRIMARY_MODULE_OVERRIDES[$slug];
        }

        $fromDb = Role::query()->where('slug', $slug)->value('module');
        if (filled($fromDb)) {
            return $fromDb;
        }

        foreach (self::roleSlugsByModule() as $module => $slugs) {
            if (in_array($slug, $slugs, true)) {
                return $module;
            }
        }

        return null;
    }

    public static function accessProfileForRoleSlug(?string $slug): string
    {
        $slug = self::normalizeRoleSlug($slug);

        if ($slug === null) {
            return 'other';
        }

        $fromDb = Role::query()->where('slug', $slug)->value('access_profile');
        if (filled($fromDb)) {
            return $fromDb;
        }

        if (in_array($slug, ['it', 'admin'], true)) {
            return 'admin';
        }

        if (in_array($slug, ['monetique', 'monetique_ops', 'ca', 'cc', 'caissier'], true)) {
            return 'monetique';
        }

        return 'other';
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function moduleOptions(): array
    {
        return array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label],
            array_keys(self::MODULE_LABELS),
            self::MODULE_LABELS,
        );
    }

    /**
     * @return list<array{key: string, label: string, roles: list<string>}>
     */
    public static function moduleMatrix(): array
    {
        $byModule = self::roleSlugsByModule();

        return array_map(
            fn (string $key, string $label) => [
                'key' => $key,
                'label' => $label,
                'roles' => $byModule[$key] ?? [],
            ],
            array_keys(self::MODULE_LABELS),
            self::MODULE_LABELS,
        );
    }

    /**
     * Modules où le slug apparaît dans la matrice courante (DB ou legacy).
     *
     * @return list<string>
     */
    public static function inferredModuleKeysForSlug(string $slug): array
    {
        $slug = self::normalizeRoleSlug($slug);
        if ($slug === null) {
            return [];
        }

        $keys = [];
        foreach (self::roleSlugsByModule() as $module => $slugs) {
            if (in_array($slug, $slugs, true)) {
                $keys[] = $module;
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function defaultModuleKeysForRole(Role $role): array
    {
        $keys = $role->moduleKeys();

        if ($keys !== []) {
            return $keys;
        }

        if (filled($role->module)) {
            return [$role->module];
        }

        return [];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function roleSlugsByModule(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $matrix = array_fill_keys(array_keys(self::MODULE_LABELS), []);

            if (! self::roleModuleTableExists()) {
                return self::legacyRoleSlugsByModule();
            }

            $rows = DB::table('role_module')
                ->join('roles', 'roles.id', '=', 'role_module.role_id')
                ->where('roles.actif', true)
                ->select(['role_module.module', 'roles.slug'])
                ->orderBy('roles.slug')
                ->get();

            foreach ($rows as $row) {
                if (! isset($matrix[$row->module])) {
                    continue;
                }

                $slug = self::normalizeRoleSlug($row->slug);
                if ($slug !== null) {
                    $matrix[$row->module][] = $slug;
                }
            }

            foreach ($matrix as $module => $slugs) {
                $matrix[$module] = array_values(array_unique($slugs));
            }

            return $matrix;
        });
    }

    private static function roleModuleTableExists(): bool
    {
        return Schema::hasTable('role_module');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function legacyRoleSlugsByModule(): array
    {
        $legacy = [
            'fed' => [
                'it', 'demandeur', 'n_plus_1', 'responsable_achats', 'responsable_facilities',
                'responsable_stock', 'controle_de_gestion', 'daf', 'dga', 'assistant_comptable',
            ],
            'budget' => ['it', 'n_plus_1', 'controle_de_gestion', 'daf', 'demandeur'],
            'stock' => ['it', 'responsable_achats', 'responsable_stock'],
            'ecritures' => ['it', 'controle_de_gestion', 'daf'],
            'monetique' => ['it', 'monetique', 'monetique_ops', 'ca', 'cc', 'caissier'],
            'od' => ['it', 'ops', 'finance', 'controle_de_gestion', 'daf'],
            'config' => ['it', 'admin'],
        ];

        return array_map(
            fn (array $slugs) => array_values(array_unique(array_filter(array_map(
                fn (string $slug) => self::normalizeRoleSlug($slug),
                $slugs,
            )))),
            $legacy,
        );
    }
}
