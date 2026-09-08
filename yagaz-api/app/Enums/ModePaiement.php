<?php

namespace App\Enums;

/**
 * Mode de paiement d'une commande (doc 07, §6 `commandes` ; ADR 0004).
 */
enum ModePaiement: string
{
    case ALaLivraison = 'a_la_livraison';
}
