<?php

namespace App\Policies;

use App\Models\Commande;
use App\Models\User;

/**
 * Une commande est visible par : son foyer demandeur, l'organisation cible
 * (dépôt qui reçoit, ou mandataire), et l'organisation demandeuse (cas dépôt
 * vers mandataire) — hiérarchie incluse (doc 07, §6 et §10).
 */
class CommandePolicy
{
    public function view(User $user, Commande $commande): bool
    {
        // Le foyer qui a passé la commande.
        if ($commande->demandeur_user_id !== null
            && $commande->demandeur_user_id === $user->id) {
            return true;
        }

        // L'organisation cible (dépôt ou mandataire), hiérarchie comprise.
        if ($commande->cibleOrg !== null
            && $user->peutVoirOrganisation($commande->cibleOrg)) {
            return true;
        }

        // L'organisation demandeuse (dépôt qui commande à son mandataire).
        if ($commande->demandeurOrg !== null
            && $user->peutVoirOrganisation($commande->demandeurOrg)) {
            return true;
        }

        return false;
    }

    /**
     * Faire avancer une commande : l'organisation cible qui la traite.
     */
    public function update(User $user, Commande $commande): bool
    {
        return $commande->cibleOrg !== null
            && $user->peutVoirOrganisation($commande->cibleOrg);
    }
}
