<?php

namespace App\Policies;

use App\Models\Tournee;
use App\Models\User;

/**
 * Une tournée est visible par le livreur affecté et par l'organisation qui
 * l'a organisée (hiérarchie incluse) ; seule cette dernière, en gestion
 * directe, peut la modifier — doc 07, §6 et §10.
 */
class TourneePolicy
{
    public function view(User $user, Tournee $tournee): bool
    {
        if ($tournee->livreur_user_id !== null && $tournee->livreur_user_id === $user->id) {
            return true;
        }

        return $tournee->organisation !== null && $user->peutVoirOrganisation($tournee->organisation);
    }

    public function update(User $user, Tournee $tournee): bool
    {
        return $tournee->organisation !== null && $user->peutGererOrganisation($tournee->organisation);
    }
}
