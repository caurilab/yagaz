<?php

namespace App\Enums;

/**
 * Type d'une alerte (doc 07, §8 `alertes`). Les trois cas `Commande*`
 * (Phase 5, contrat API doc 11, §3) adressent les changements de statut
 * d'une commande foyer (préparée, en livraison, livrée). `ReapproPrepare` et
 * `ReapproConfirme` sont le 4e événement de notification (ADR 0009, maillon
 * D) : préparation automatique d'un réappro dépôt→mandataire (notifie le
 * dépôt) puis sa confirmation (notifie le mandataire). Dédiés plutôt que de
 * réutiliser `StockTension` (générique, non adressé à ce jour) : les deux
 * événements portent une commande précise et un destinataire différent.
 */
enum TypeAlerte: string
{
    case SeuilBas = 'seuil_bas';
    case PropositionLivraison = 'proposition_livraison';
    case StockTension = 'stock_tension';
    case CommandePreparee = 'commande_preparee';
    case CommandeEnLivraison = 'commande_en_livraison';
    case CommandeLivree = 'commande_livree';
    case ReapproPrepare = 'reappro_prepare';
    case ReapproConfirme = 'reappro_confirme';
}
