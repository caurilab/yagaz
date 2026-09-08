/**
 * Langage visuel commun des statuts commande / livraison (contrat 10 §2,
 * §5). Même principe que `niveau.ts` : on traduit en libellé et couleur,
 * sans réinventer la logique métier - la transition est décidée côté API.
 */
import { couleurs } from '../../theme/couleurs';
import type { StatutCommande, StatutLigneTournee, StatutLivraison, StatutTournee } from '../api/types';

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

// --- Tournées mandataire (doc 11 §1, UX §4) ---

export const libellesStatutTournee: Record<StatutTournee, string> = {
  proposee: 'Proposée',
  validee: 'Validée',
  en_cours: 'En cours',
  terminee: 'Terminée',
};

export const couleursStatutTournee: Record<StatutTournee, string> = {
  proposee: couleurs.ambre,
  validee: couleurs.rouge,
  en_cours: couleurs.rouge,
  terminee: couleurs.vertOk,
};

export const libellesStatutLigneTournee: Record<StatutLigneTournee, string> = {
  a_faire: 'À faire',
  arrive: 'Arrivé',
  depose: 'Déposé',
  vides_recuperes: 'Vides récupérés',
};

export const couleursStatutLigneTournee: Record<StatutLigneTournee, string> = {
  a_faire: couleurs.grisNeutre,
  arrive: couleurs.ambre,
  depose: couleurs.rouge,
  vides_recuperes: couleurs.vertOk,
};

/** Ordre de progression d'un arrêt (UX §4 : arrivé / déposé / vides récupérés). */
const ORDRE_LIGNE_TOURNEE: StatutLigneTournee[] = ['a_faire', 'arrive', 'depose', 'vides_recuperes'];

export function statutLigneTourneeSuivant(statut: StatutLigneTournee): StatutLigneTournee | null {
  const index = ORDRE_LIGNE_TOURNEE.indexOf(statut);
  return index >= 0 && index < ORDRE_LIGNE_TOURNEE.length - 1 ? ORDRE_LIGNE_TOURNEE[index + 1] : null;
}

export function libelleActionLigneTournee(statut: StatutLigneTournee): string | null {
  const suivant = statutLigneTourneeSuivant(statut);
  if (suivant === 'arrive') return 'Je suis arrivé';
  if (suivant === 'depose') return 'Pleines déposées';
  if (suivant === 'vides_recuperes') return 'Vides récupérés';
  return null;
}

/**
 * Statut d'un arrêt (plusieurs lignes/formats pour un même dépôt) : le
 * moins avancé de ses lignes, pour faire avancer tout l'arrêt d'un même
 * geste (UX §4 - un seul bouton par arrêt, pas par format).
 */
export function statutArretTournee(lignesStatuts: StatutLigneTournee[]): StatutLigneTournee {
  let indexMin = ORDRE_LIGNE_TOURNEE.length - 1;
  for (const statut of lignesStatuts) {
    indexMin = Math.min(indexMin, ORDRE_LIGNE_TOURNEE.indexOf(statut));
  }
  return ORDRE_LIGNE_TOURNEE[Math.max(indexMin, 0)];
}
