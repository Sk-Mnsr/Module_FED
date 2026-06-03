<?php

namespace App\Support;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CoficarteAgenceAccess
{
    public static function canViewAll(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole(['it', 'monetique', 'monetique_ops']);
    }

    /**
     * Actions sensibles Coficarte (création de cartes, prix) : responsable monétique ou administrateur IT.
     */
    public static function canResponsableMonetique(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('monetique');
    }

    /**
     * Saisie terrain vente / recharge : CC ou monétique centrale (pas le chef d’agence seul).
     */
    private static function canInitiateCoficarteCommercialOperation(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasAnyRole(['monetique', 'monetique_ops'])) {
            return true;
        }

        return $user->hasRole('cc');
    }

    public static function canInitiateCoficarteVente(?User $user): bool
    {
        return self::canInitiateCoficarteCommercialOperation($user);
    }

    public static function canInitiateCoficarteRecharge(?User $user): bool
    {
        return self::canInitiateCoficarteCommercialOperation($user);
    }

    public static function applyCardScope(Builder $query, ?User $user): void
    {
        if (! $user || self::canViewAll($user)) {
            return;
        }

        if ($user->agence_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('agence_id', $user->agence_id);

        if ($user->hasRole('cc') && ! $user->hasRole('ca')) {
            $query->where('assigned_to_user_id', $user->id);
        }
    }

    /**
     * Liste / historique des recharges : agence d’enregistrement ou carte liée dans le périmètre recharge.
     */
    public static function applyRechargeListingScope(Builder $query, ?User $user): void
    {
        if (! $user || self::canViewAll($user)) {
            return;
        }

        if ($user->agence_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $aid = (int) $user->agence_id;

        $query->where(function (Builder $w) use ($aid, $user) {
            $w->where('agence_enregistrement_id', $aid)
                ->orWhereHas('card', function (Builder $c) use ($user) {
                    self::applyRechargeCardLookupScope($c, $user);
                });
        });
    }

    /**
     * Recherche de carte pour une recharge : même périmètre agence que {@see applyCardScope},
     * mais sans restriction d’affectation CC (toute carte vendue de l’agence peut être saisie au numéro).
     * Les rôles « vue globale » (monétique, IT, etc.) restent sans filtre géographique.
     */
    public static function applyRechargeCardLookupScope(Builder $query, ?User $user): void
    {
        if (! $user || self::canViewAll($user)) {
            return;
        }

        if ($user->agence_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('agence_id', $user->agence_id);
    }

    /**
     * @return array{0: ?int, 1: ?string} [agence_id, possesseur label]
     */
    public static function resolveAgenceForNewCards(User $user, ?int $requestedAgenceId): array
    {
        if (self::canViewAll($user)) {
            $id = $requestedAgenceId;

            return [$id, self::possesseurLabel($id)];
        }

        if ($user->agence_id === null) {
            return [null, 'Au siège'];
        }

        return [$user->agence_id, self::possesseurLabel($user->agence_id)];
    }

    public static function possesseurLabel(?int $agenceId): string
    {
        if ($agenceId === null) {
            return 'Au siège';
        }

        $agence = Agence::query()->find($agenceId);

        return $agence?->nom ?? 'Au siège';
    }

    public static function applyTransferScope(Builder $query, ?User $user): void
    {
        if (! $user || self::canViewAll($user)) {
            return;
        }

        if ($user->agence_id === null) {
            $query->where('receveur_user_id', $user->id);

            return;
        }

        $query->where(function (Builder $outer) use ($user) {
            $outer->whereHas('user', fn (Builder $q) => $q->where('agence_id', $user->agence_id))
                ->orWhere('receveur_user_id', $user->id);
        });
    }
}
