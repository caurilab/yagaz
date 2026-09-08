<?php

namespace App\Enums;

/**
 * Mode de paiement d'une commande (doc 07, §6 `commandes` ; ADR 0004 ;
 * ADR 0010 pour `MobileMoney`).
 */
enum ModePaiement: string
{
    case ALaLivraison = 'a_la_livraison';
    case MobileMoney = 'mobile_money';
}
