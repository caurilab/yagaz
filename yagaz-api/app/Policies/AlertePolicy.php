<?php

namespace App\Policies;

use App\Models\Alerte;
use App\Models\User;

/**
 * Une alerte cible une bouteille (foyer) ou une organisation (tension de
 * stock pro), jamais les deux : la visibilité suit celle de sa cible —
 * doc 07, §8 et §10.
 */
class AlertePolicy
{
    public function view(User $user, Alerte $alerte): bool
    {
        if ($alerte->bouteille_id !== null) {
            $site = $alerte->bouteille?->site;

            return $site !== null && $user->aAccesAuSite($site);
        }

        if ($alerte->organisation_id !== null) {
            return $alerte->organisation !== null && $user->peutVoirOrganisation($alerte->organisation);
        }

        return false;
    }
}
