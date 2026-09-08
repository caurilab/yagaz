<?php

namespace App\Enums;

/**
 * Rôle d'un utilisateur au sein d'une organisation (doc 07, §2 `memberships`).
 */
enum RoleMembership: string
{
    case GerantDepot = 'gerant_depot';
    case Mandataire = 'mandataire';
    case Distributeur = 'distributeur';
    case Livreur = 'livreur';
}
