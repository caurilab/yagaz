<?php

namespace App\Policies;

use App\Models\Mesure;
use App\Models\User;

/**
 * Une mesure est liée à un plateau et, quand elle est résolue, à une
 * bouteille. L'accès dérive du site — foyer ou pro — jamais l'inverse : un
 * acteur PRO (dépôt/mandataire/distributeur) n'a aucun accès de site sur un
 * foyer, donc ne voit jamais ses mesures (doc 07, §5 et §10).
 */
class MesurePolicy
{
    public function view(User $user, Mesure $mesure): bool
    {
        $siteBouteille = $mesure->bouteille?->site;

        if ($siteBouteille !== null && $user->aAccesAuSite($siteBouteille)) {
            return true;
        }

        $sitePlateau = $mesure->plateau?->site;

        if ($sitePlateau !== null && $user->aAccesAuSite($sitePlateau)) {
            return true;
        }

        return false;
    }
}
