<?php

namespace App\Policies;

use App\Models\MouvementStock;
use App\Models\User;

/**
 * Un mouvement de stock est un journal : sa visibilité suit celle de
 * l'organisation propriétaire du stock (hiérarchie incluse) — doc 07, §7 et §10.
 */
class MouvementStockPolicy
{
    public function view(User $user, MouvementStock $mouvement): bool
    {
        $organisation = $mouvement->stock?->organisation;

        return $organisation !== null && $user->peutVoirOrganisation($organisation);
    }
}
