/**
 * Tension visuelle du stock dépôt (UX §3 "la tension éventuelle est
 * signalée visuellement"). Le contrat 10 ne définit qu'un seuil pour les
 * pleines (`seuil_plein_bas`) ; le signal "vides accumulés" est une
 * heuristique d'affichage côté client (pas de seuil serveur pour l'instant),
 * documentée ici pour rester isolée et facile à ajuster.
 */
import type { StockFormat } from '../api/types';

export interface TensionStock {
  stockBas: boolean;
  videsAccumules: boolean;
}

export function tensionStock(stock: StockFormat): TensionStock {
  return {
    stockBas: stock.pleines <= stock.seuil_plein_bas,
    videsAccumules: stock.vides > 0 && stock.vides >= Math.max(stock.pleines, stock.seuil_plein_bas),
  };
}
