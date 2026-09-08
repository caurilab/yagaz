<?php

namespace App\Enums;

/**
 * Statut d'exécution d'une livraison (doc 07, §6 `livraisons`).
 */
enum StatutLivraison: string
{
    case Affectee = 'affectee';
    case EnRoute = 'en_route';
    case Livree = 'livree';
    case VideRecupere = 'vide_recupere';
}
