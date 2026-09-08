<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

/**
 * Cloisonnement des sites (doc 07, §3 et §10) : l'accès passe TOUJOURS par une
 * ligne `site_acces`. Un foyer ne voit jamais le site d'un autre foyer.
 */
class SitePolicy
{
    /**
     * Voir un site : disposer d'un accès, quel qu'en soit le niveau
     * (y compris observateur — cas du proche surveillé à distance).
     */
    public function view(User $user, Site $site): bool
    {
        return $user->aAccesAuSite($site);
    }

    /**
     * Modifier un site : être propriétaire ou gestionnaire.
     */
    public function update(User $user, Site $site): bool
    {
        return $user->peutGererSite($site);
    }

    /**
     * Supprimer un site : réservé à un gestionnaire/propriétaire.
     */
    public function delete(User $user, Site $site): bool
    {
        return $user->peutGererSite($site);
    }
}
