<?php

namespace App\Policies;

use App\Models\Bouteille;
use App\Models\User;

/**
 * L'accès à une bouteille dérive de l'accès à son site (doc 07, §10).
 */
class BouteillePolicy
{
    /**
     * Voir une bouteille : avoir accès au site où elle se trouve.
     */
    public function view(User $user, Bouteille $bouteille): bool
    {
        return $user->aAccesAuSite($bouteille->site);
    }

    /**
     * Modifier une bouteille (tare, rôle actif/secours, seuil) : gérer le site.
     */
    public function update(User $user, Bouteille $bouteille): bool
    {
        return $user->peutGererSite($bouteille->site);
    }

    /**
     * Supprimer une bouteille : gérer le site.
     */
    public function delete(User $user, Bouteille $bouteille): bool
    {
        return $user->peutGererSite($bouteille->site);
    }
}
