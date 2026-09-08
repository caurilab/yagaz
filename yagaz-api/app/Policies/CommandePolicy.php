<?php

namespace App\Policies;

use App\Enums\RoleMembership;
use App\Models\Commande;
use App\Models\User;

/**
 * Une commande est visible par : son foyer demandeur, tout utilisateur ayant
 * accès au site de livraison (cas d'une proposition dépôt→foyer, sans
 * demandeur précis), l'organisation cible (dépôt qui reçoit, ou mandataire),
 * et l'organisation demandeuse (cas dépôt vers mandataire) — hiérarchie
 * incluse (doc 07, §6 et §10 ; doc 10, §2 et §3).
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

        // Quiconque a accès au site de livraison (dont le foyer destinataire
        // d'une proposition dépôt→foyer, qui n'a pas de demandeur précis).
        if ($commande->site !== null && $user->aAccesAuSite($commande->site)) {
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
     * Faire avancer une commande : l'organisation cible qui la traite,
     * en gestion directe (pas via la hiérarchie ascendante).
     */
    public function update(User $user, Commande $commande): bool
    {
        return $commande->cibleOrg !== null && $user->peutGererOrganisation($commande->cibleOrg);
    }

    /**
     * Répondre à une proposition (contrat API doc 10, §3,
     * `POST /commandes/{uuid}/reponse`) : réservé au foyer destinataire, ici
     * tout utilisateur ayant accès au site de livraison.
     */
    public function repondre(User $user, Commande $commande): bool
    {
        return $commande->site !== null && $user->aAccesAuSite($commande->site);
    }

    /**
     * Traiter une commande côté dépôt — préparer, affecter un livreur (doc
     * 10, §4) : réservé au membre `gerant_depot` direct du dépôt cible
     * (périmètre plus strict que `update`, qui accepte tout rôle non-livreur).
     */
    public function gererDepot(User $user, Commande $commande): bool
    {
        return $commande->cibleOrg !== null && $user->estMembreDe($commande->cibleOrg, RoleMembership::GerantDepot);
    }
}
