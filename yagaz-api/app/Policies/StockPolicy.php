<?php

namespace App\Policies;

use App\Models\Stock;
use App\Models\User;

/**
 * Le stock appartient à une organisation. Un mandataire voit le stock de ses
 * dépôts (vue consolidée) ; seul un membre direct du dépôt peut l'ajuster.
 */
class StockPolicy
{
    /**
     * Voir un stock : voir l'organisation qui le porte (hiérarchie incluse).
     */
    public function view(User $user, Stock $stock): bool
    {
        return $user->peutVoirOrganisation($stock->organisation);
    }

    /**
     * Ajuster un stock : être membre direct de l'organisation (le dépôt
     * tient son propre stock ; le mandataire le voit mais ne le saisit pas).
     */
    public function update(User $user, Stock $stock): bool
    {
        return $user->peutGererOrganisation($stock->organisation);
    }
}
