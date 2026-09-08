<?php

namespace App\Enums;

/**
 * Type d'une organisation (doc 07, §2 `organisations`).
 */
enum TypeOrganisation: string
{
    case Depot = 'depot';
    case Mandataire = 'mandataire';
    case Distributeur = 'distributeur';
}
