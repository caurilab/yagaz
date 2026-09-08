/**
 * Langage visuel commun des statuts commande / livraison (contrat 10 §2,
 * §5). Même principe que `niveau.ts` : on traduit en libellé et couleur,
 * sans réinventer la logique métier - la transition est décidée côté API.
 */
import { couleurs } from '../../theme/couleurs';
import type { StatutCommande, StatutLivraison } from '../api/types';

export const libellesStatutCommande: Record<StatutCommande, string> = {
  proposee: 'Proposition reçue',
  confirmee: 'Confirmée',
  preparee: 'Préparée',
  en_livraison: 'En livraison',
  livree: 'Livrée',
  annulee: 'Annulée',
};

export const couleursStatutCommande: Record<StatutCommande, string> = {
  proposee: couleurs.ambre,
  confirmee: couleurs.rouge,
  preparee: couleurs.rouge,
  en_livraison: couleurs.rouge,
  livree: couleurs.vertOk,
  annulee: couleurs.grisNeutre,
};

export const libellesStatutLivraison: Record<StatutLivraison, string> = {
  affectee: 'Affectée',
  en_route: 'En route',
  livree: 'Livrée',
  vide_recupere: 'Vide récupéré',
};

export const couleursStatutLivraison: Record<StatutLivraison, string> = {
  affectee: couleurs.ambre,
  en_route: couleurs.rouge,
  livree: couleurs.vertOk,
  vide_recupere: couleurs.vertOk,
};

/** Transition suivante dans le geste large du livreur (contrat §5). */
export function statutLivraisonSuivant(statut: StatutLivraison): Exclude<StatutLivraison, 'affectee'> | null {
  if (statut === 'affectee') return 'en_route';
  if (statut === 'en_route') return 'livree';
  if (statut === 'livree') return 'vide_recupere';
  return null;
}

export function libelleActionLivraison(statut: StatutLivraison): string | null {
  const suivant = statutLivraisonSuivant(statut);
  if (suivant === 'en_route') return 'Je pars en livraison';
  if (suivant === 'livree') return 'Marquer comme livrée';
  if (suivant === 'vide_recupere') return 'Vide récupéré';
  return null;
}
