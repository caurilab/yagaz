<?php

namespace App\Policies;

use App\Models\Equipement;
use App\Models\User;

/**
 * Cloisonnement des équipements (ADR 0012) : un équipement affecté à un site
 * n'est visible/modifiable que par un utilisateur ayant accès à ce site ;
 * tant qu'il n'est affecté à aucun site, seul son créateur y accède. Toute
 * autre situation reste hors périmètre (404, `AutoriseCloisonnement`).
 */
class EquipementPolicy
{
    public function view(User $user, Equipement $equipement): bool
    {
        return $this->accede($user, $equipement);
    }

    public function update(User $user, Equipement $equipement): bool
    {
        return $this->accede($user, $equipement);
    }

    public function delete(User $user, Equipement $equipement): bool
    {
        return $this->accede($user, $equipement);
    }

    private function accede(User $user, Equipement $equipement): bool
    {
        if ($equipement->site !== null) {
            return $user->aAccesAuSite($equipement->site);
        }

        return $equipement->cree_par === $user->id;
    }
}
