<?php

namespace App\Support;

use App\Models\PodProduitEcran;
use App\Models\User;

class PodEcranAccess
{
    public static function userCanAccess(?User $user, PodProduitEcran $ecran): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $ecran->actif) {
            return false;
        }

        if (ModuleAccess::isAdminUser($user) || ModuleAccess::userHasAnyRole($user, ['it'])) {
            return true;
        }

        $roles = $ecran->roleSlugsAutorises();
        // Sans profils = accessible à tout utilisateur du module.
        if ($roles === []) {
            return true;
        }

        return ModuleAccess::userHasAnyRole($user, $roles);
    }
}
