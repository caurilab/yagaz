<?php

namespace App\Enums;

/**
 * Type d'une alerte (doc 07, §8 `alertes`).
 */
enum TypeAlerte: string
{
    case SeuilBas = 'seuil_bas';
    case PropositionLivraison = 'proposition_livraison';
    case StockTension = 'stock_tension';
}
