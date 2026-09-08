<?php

namespace App\Enums;

/**
 * Statut de paiement d'une commande (doc 07, §6 `commandes`).
 */
enum StatutPaiement: string
{
    case EnAttente = 'en_attente';
    case Regle = 'regle';
}
