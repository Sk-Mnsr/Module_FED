<?php

namespace App\Support;

use App\Models\OdClasseur;
use App\Models\User;

/**
 * Contrôle final des pièces archivées (justificatifs + pièce comptable).
 * Rôle dédié `controleur` — distinct du Contrôle de gestion (FED).
 */
final class OdControle
{
    public const ROLE = 'controleur';

    public static function isControleur(?User $user): bool
    {
        return $user !== null && $user->hasRole(self::ROLE);
    }

    public static function isControleurOnly(?User $user): bool
    {
        if (! self::isControleur($user)) {
            return false;
        }

        if (ModuleAccess::isAdminUser($user)) {
            return false;
        }

        if ($user->hasRole(OdChecker::ROLE_OPS) || $user->hasRole(OdChecker::ROLE_FINANCE)) {
            return false;
        }

        return true;
    }

    public static function canActOnArchive(User $user, OdClasseur $classeur): bool
    {
        if (! $classeur->isIntegre()) {
            return false;
        }

        // Déjà contrôlée (OK ou avec anomalie / correction demandée) : plus d’action.
        if ($classeur->isControle() || $classeur->hasControleAnomalie()) {
            return false;
        }

        return self::isControleur($user) || ModuleAccess::isAdminUser($user);
    }

    public static function canControl(User $user, OdClasseur $classeur): bool
    {
        return self::canActOnArchive($user, $classeur);
    }

    public static function canSignalAnomalie(User $user, OdClasseur $classeur): bool
    {
        return self::canActOnArchive($user, $classeur);
    }

    public static function canViewAllArchives(User $user): bool
    {
        return self::isControleur($user) || ModuleAccess::isAdminUser($user);
    }
}
