<?php

namespace App\Enums;

/**
 * Statut de paiement d'une commande (doc 07, §6 `commandes` ; ADR 0010 pour
 * `Initie`/`Echoue`/`Expire`, machine à états
 * `en_attente → initie → regle | echoue | expire`).
 */
enum StatutPaiement: string
{
    case EnAttente = 'en_attente';
    case Initie = 'initie';
    case Regle = 'regle';
    case Echoue = 'echoue';
    case Expire = 'expire';
}
