<?php

namespace App\Enums;

/**
 * Statut du cycle de vie d'une commande (doc 07, §6 `commandes`).
 */
enum StatutCommande: string
{
    case Proposee = 'proposee';
    case Confirmee = 'confirmee';
    case Preparee = 'preparee';
    case EnLivraison = 'en_livraison';
    case Livree = 'livree';
    case Annulee = 'annulee';
}
