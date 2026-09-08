<?php

namespace App\Enums;

/**
 * Origine d'une commande (doc 07, §6 `commandes`).
 */
enum OrigineCommande: string
{
    case Foyer = 'foyer';
    case Depot = 'depot';
}
