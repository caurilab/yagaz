<?php

namespace App\Policies;

use App\Models\Alerte;
use App\Models\User;

/**
 * Une alerte cible une bouteille (foyer) ou une organisation (tension de
 * stock pro), jamais les deux : la visibilité suit celle de sa cible —
 * doc 07, §8 et §10. Depuis la Phase 5 (doc 11, §3), une alerte peut aussi
 * être adressée nommément à un utilisateur (`destinataire_user_id`) : c'est
 * alors une notification, visible d'abord par son destinataire.
 */
class AlertePolicy
{
    public function view(User $user, Alerte $alerte): bool
    {
        if ($alerte->destinataire_user_id !== null && $alerte->destinataire_user_id === $user->id) {
            return true;
        }

        if ($alerte->bouteille_id !== null) {
            $site = $alerte->bouteille?->site;

            return $site !== null && $user->aAccesAuSite($site);
        }

        if ($alerte->organisation_id !== null) {
            return $alerte->organisation !== null && $user->peutVoirOrganisation($alerte->organisation);
        }

        return false;
    }

    /**
     * Changer le statut d'une alerte (vue/résolue) : même périmètre que sa
     * visibilité — pas de niveau d'accès supplémentaire requis (contrat API,
     * `PATCH /api/alertes/{id}`).
     */
    public function update(User $user, Alerte $alerte): bool
    {
        return $this->view($user, $alerte);
    }
}
