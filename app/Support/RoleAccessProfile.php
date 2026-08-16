<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Collection;

/**
 * Profil stocké sur users.profile : uniquement admin | other.
 *
 * L’accès métier (OD, Monétique, FED…) vient des rôles + matrice modules,
 * pas de users.profile = monetique.
 */
class RoleAccessProfile
{
    public const ADMIN = 'admin';

    public const OTHER = 'other';

    public static function forRoleSlug(?string $slug): string
    {
        return ModuleAccess::accessProfileForRoleSlug($slug) === self::ADMIN
            ? self::ADMIN
            : self::OTHER;
    }

    public static function forRole(?Role $role): string
    {
        if ($role !== null && $role->access_profile === self::ADMIN) {
            return self::ADMIN;
        }

        if ($role !== null && filled($role->slug)) {
            return self::forRoleSlug($role->slug);
        }

        return self::OTHER;
    }

    /**
     * @param  iterable<int, Role>|Collection<int, Role>  $roles
     */
    public static function forRoles(iterable $roles): string
    {
        foreach ($roles as $role) {
            if (self::forRole($role) === self::ADMIN) {
                return self::ADMIN;
            }
        }

        return self::OTHER;
    }

    public static function forRoleId(int $roleId): string
    {
        return self::forRole(Role::find($roleId));
    }

    /**
     * @param  list<int>  $roleIds
     */
    public static function forRoleIds(array $roleIds): string
    {
        if ($roleIds === []) {
            return self::OTHER;
        }

        return self::forRoles(Role::whereIn('id', $roleIds)->get());
    }
}
