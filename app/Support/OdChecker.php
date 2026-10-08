<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pool de validateurs (checker) pour le workflow maker / checker OD.
 * Ops désigne un autre agent ops ; finance désigne un autre agent finance.
 */
final class OdChecker
{
    public const ROLE_OPS = 'ops';

    public const ROLE_FINANCE = 'finance';

    /**
     * Rôle métier OD du maker (ops, finance, ou un rôle créé pour le module).
     * Null pour le contrôleur et les profils sans rôle de saisie.
     */
    public static function poleSlug(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('roles');

        $slugs = $user->roles
            ->filter(fn ($role) => $role->module === 'od'
                && ($role->actif ?? true)
                && ! in_array($role->slug, ['controleur', 'it', 'admin'], true))
            ->pluck('slug')
            ->values();

        if ($slugs->contains(self::ROLE_OPS)) {
            return self::ROLE_OPS;
        }

        if ($slugs->contains(self::ROLE_FINANCE)) {
            return self::ROLE_FINANCE;
        }

        return $slugs->first();
    }

    public static function roleSlugForUser(User $user): string
    {
        return self::poleSlug($user) ?? self::ROLE_OPS;
    }

    public static function departmentLabelForUser(User $user): string
    {
        return OdArchivage::departmentLabel(OdArchivage::departmentKey($user));
    }

    /**
     * Agents éligibles comme checker pour un maker (même pôle, hors le maker).
     *
     * @return Collection<int, User>
     */
    public static function eligibleFor(User $maker): Collection
    {
        $role = self::roleSlugForUser($maker);

        return User::query()
            ->where('id', '!=', $maker->id)
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', $role))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public static function isEligibleChecker(User $maker, User $checker): bool
    {
        if ($maker->id === $checker->id) {
            return false;
        }

        $role = self::roleSlugForUser($maker);

        return $checker->hasRole($role);
    }
}
