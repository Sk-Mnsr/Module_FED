<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Collection;

class RoleAccessProfile
{
    public static function forRoleSlug(?string $slug): string
    {
        return ModuleAccess::accessProfileForRoleSlug($slug);
    }

    public static function forRole(?Role $role): string
    {
        if ($role !== null && filled($role->access_profile)) {
            return $role->access_profile;
        }

        return self::forRoleSlug($role?->slug);
    }

    /**
     * @param  iterable<int, Role>|Collection<int, Role>  $roles
     */
    public static function forRoles(iterable $roles): string
    {
        foreach ($roles as $role) {
            if (self::forRole($role) === 'admin') {
                return 'admin';
            }
        }

        foreach ($roles as $role) {
            if (self::forRole($role) === 'monetique') {
                return 'monetique';
            }
        }

        return 'other';
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
            return 'other';
        }

        return self::forRoles(Role::whereIn('id', $roleIds)->get());
    }
}
