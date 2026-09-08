/**
 * Langage visuel commun de l'état de niveau (contrat §États visuels, UX §7).
 * Le client n'invente jamais de seuils : il ne fait que traduire l'`etat`
 * déjà calculé par l'API en libellé et couleur.
 */
import { couleurs } from '../../theme/couleurs';
import type { EtatNiveau } from '../api/types';

export const libellesEtat: Record<EtatNiveau, string> = {
  plein: 'Plein',
  correct: 'Correct',
  bas: 'Bas',
  presque_vide: 'Presque vide',
  inconnu: 'Inconnu',
};

export const couleursEtat: Record<EtatNiveau, string> = {
  plein: couleurs.vertOk,
  correct: couleurs.vertOk,
  bas: couleurs.ambre,
  presque_vide: couleurs.rouge,
  inconnu: couleurs.grisNeutre,
};

/** Formate l'autonomie en heures pour l'affichage (information reine, UX §2). */
export function formaterAutonomie(heures: number): string {
  if (heures <= 0) return '0 h';
  if (heures < 1) return '< 1 h';
  return `${Math.round(heures)} h`;
}
