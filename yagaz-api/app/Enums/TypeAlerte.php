<?php

namespace App\Enums;

/**
 * Type d'une alerte (doc 07, §8 `alertes`). Les trois derniers cas
 * (Phase 5, contrat API doc 11, §3) adressent les changements de statut
 * d'une commande foyer (préparée, en livraison, livrée).
 */
enum TypeAlerte: string
{
    case SeuilBas = 'seuil_bas';
    case PropositionLivraison = 'proposition_livraison';
    case StockTension = 'stock_tension';
    case CommandePreparee = 'commande_preparee';
    case CommandeEnLivraison = 'commande_en_livraison';
    case CommandeLivree = 'commande_livree';
}
