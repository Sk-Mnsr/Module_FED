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

    public static function roleSlugForUser(User $user): string
    {
        // Ops prioritaire si les deux rôles sont présents (agent opérations).
        if ($user->hasRole(self::ROLE_OPS)) {
            return self::ROLE_OPS;
        }

        if ($user->hasRole(self::ROLE_FINANCE)) {
            return self::ROLE_FINANCE;
        }

        // IT / autres accès OD : pôle opérations par défaut.
        return self::ROLE_OPS;
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
