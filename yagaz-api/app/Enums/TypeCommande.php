<?php

namespace App\Enums;

/**
 * Type d'une commande foyer (v1 « type simple, prix plus tard » - aucune
 * logique de prix associée) : `Echange` = on récupère la bouteille vide et
 * on la remplace par une pleine ; `Achat` = livraison d'une bouteille neuve,
 * sans reprise. Colonne `commandes.type`, nullable, défaut `echange`.
 */
enum TypeCommande: string
{
    case Echange = 'echange';
    case Achat = 'achat';
}
