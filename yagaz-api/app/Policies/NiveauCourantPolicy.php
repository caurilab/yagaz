<?php

namespace App\Policies;

use App\Models\NiveauCourant;
use App\Models\User;

/**
 * Le niveau courant est un cache par bouteille : l'accès suit celui de la
 * bouteille, donc du site (doc 07, §5 et §10).
 */
class NiveauCourantPolicy
{
    public function view(User $user, NiveauCourant $niveau): bool
    {
        $site = $niveau->bouteille?->site;

        return $site !== null && $user->aAccesAuSite($site);
    }
}
