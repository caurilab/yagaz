<?php

namespace App\Policies;

use App\Models\Plateau;
use App\Models\User;

/**
 * Un plateau est visible par les utilisateurs ayant accès au site où il est
 * installé. Un plateau non posé (site null) n'est visible d'aucun foyer.
 * (L'authentification de l'appareil lui-même passe par MQTT, pas par ici.)
 */
class PlateauPolicy
{
    public function view(User $user, Plateau $plateau): bool
    {
        return $plateau->site !== null && $user->aAccesAuSite($plateau->site);
    }

    public function update(User $user, Plateau $plateau): bool
    {
        return $plateau->site !== null && $user->peutGererSite($plateau->site);
    }
}
