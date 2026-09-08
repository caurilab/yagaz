<?php

namespace App\Policies;

use App\Models\Organisation;
use App\Models\User;

/**
 * Cloisonnement des organisations (doc 07, §10). L'accès descend la hiérarchie :
 * un mandataire voit ses dépôts, un distributeur voit ses mandataires et leurs
 * dépôts. La gestion, elle, reste sur l'organisation dont on est membre direct.
 */
class OrganisationPolicy
{
    /**
     * Voir une organisation : en être membre, ou membre d'un de ses ancêtres.
     */
    public function view(User $user, Organisation $organisation): bool
    {
        return $user->peutVoirOrganisation($organisation);
    }

    /**
     * Modifier une organisation : membre direct, rôle non-livreur.
     */
    public function update(User $user, Organisation $organisation): bool
    {
        return $user->peutGererOrganisation($organisation);
    }
}
