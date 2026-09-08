<?php

namespace App\Policies;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use App\Models\Organisation;
use App\Models\User;

/**
 * Cloisonnement des organisations (doc 07, §10). L'accès descend la hiérarchie :
 * un mandataire voit ses dépôts, un distributeur voit ses mandataires et leurs
 * dépôts. La gestion, elle, reste sur l'organisation dont on est membre direct.
 */
class OrganisationPolicy
{
    /**
     * Voir une organisation : en être membre, ou membre d'un de ses ancêtres.
     */
    public function view(User $user, Organisation $organisation): bool
    {
        return $user->peutVoirOrganisation($organisation);
    }

    /**
     * Modifier une organisation : membre direct, rôle non-livreur.
     */
    public function update(User $user, Organisation $organisation): bool
    {
        return $user->peutGererOrganisation($organisation);
    }

    /**
     * Gérer un dépôt (stock, file de commandes, propositions — doc 10, §4) :
     * réservé au membre `gerant_depot` direct, sur une organisation de type
     * dépôt. Périmètre borné à l'org (pas de hiérarchie descendante ici : un
     * mandataire ne gère pas le stock de ses dépôts, il le consulte).
     */
    public function gererDepot(User $user, Organisation $organisation): bool
    {
        return $organisation->type === TypeOrganisation::Depot
            && $user->estMembreDe($organisation, RoleMembership::GerantDepot);
    }

    /**
     * Gérer un mandataire (vue consolidée des dépôts, réappros, tournées —
     * doc 11, §1) : réservé au membre `mandataire` direct, sur une
     * organisation de type mandataire. Périmètre borné à l'org (pas de
     * hiérarchie descendante : les dépôts enfants sont consultés à travers
     * cette organisation, pas gérés directement par ce biais).
     */
    public function gererMandataire(User $user, Organisation $organisation): bool
    {
        return $organisation->type === TypeOrganisation::Mandataire
            && $user->estMembreDe($organisation, RoleMembership::Mandataire);
    }

    /**
     * Gérer un distributeur (demande régionale agrégée — doc 11, §2) :
     * réservé au membre `distributeur` direct, sur une organisation de type
     * distributeur.
     */
    public function gererDistributeur(User $user, Organisation $organisation): bool
    {
        return $organisation->type === TypeOrganisation::Distributeur
            && $user->estMembreDe($organisation, RoleMembership::Distributeur);
    }
}
