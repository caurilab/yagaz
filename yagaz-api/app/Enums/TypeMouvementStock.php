<?php

namespace App\Enums;

/**
 * Type de mouvement de stock (doc 07, §7 `mouvements_stock`).
 */
enum TypeMouvementStock: string
{
    case Vente = 'vente';
    case RetourVide = 'retour_vide';
    case Reappro = 'reappro';
    case Ajustement = 'ajustement';
}
