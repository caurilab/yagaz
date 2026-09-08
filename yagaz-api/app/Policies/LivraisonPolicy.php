<?php

namespace App\Policies;

use App\Models\Livraison;
use App\Models\User;

/**
 * Une livraison est visible par le livreur affecté, par l'organisation cible
 * de la commande (hiérarchie incluse) et par le foyer demandeur — doc 07,
 * §6 et §10.
 */
class LivraisonPolicy
{
    public function view(User $user, Livraison $livraison): bool
    {
        if ($livraison->livreur_user_id !== null && $livraison->livreur_user_id === $user->id) {
            return true;
        }

        $commande = $livraison->commande;

        if ($commande === null) {
            return false;
        }

        if ($commande->cibleOrg !== null && $user->peutVoirOrganisation($commande->cibleOrg)) {
            return true;
        }

        return $commande->demandeur_user_id !== null && $commande->demandeur_user_id === $user->id;
    }

    /**
     * Faire avancer le statut d'une livraison (contrat API doc 10, §5,
     * `PATCH /livraisons/{id}/statut`) : réservé au livreur affecté — « un
     * livreur ne voit QUE ses missions ».
     */
    public function changerStatut(User $user, Livraison $livraison): bool
    {
        return $livraison->livreur_user_id !== null && $livraison->livreur_user_id === $user->id;
    }
}
