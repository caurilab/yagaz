<?php

namespace App\Enums;

/**
 * Statut d'une tournée de livraison (doc 07, §6 `tournees`).
 */
enum StatutTournee: string
{
    case Proposee = 'proposee';
    case Validee = 'validee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
}
