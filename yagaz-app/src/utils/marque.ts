/**
 * Ambiance visuelle par marque (`format.couleur` / `Marque.couleur`, contrat
 * §Marques et §Formats) : identité de marque sur l'accueil, la liste et le
 * détail bouteille, sans jamais sacrifier la lisibilité - gris neutre de
 * repli si la couleur est absente ou invalide.
 */
import type { Format, Marque } from '../api/types';

/** Repli imposé par le contrat quand une couleur de marque est absente. */
export const COULEUR_MARQUE_REPLI = '#6B7280';

function hexValide(valeur?: string | null): valeur is string {
  return !!valeur && /^#[0-9a-fA-F]{6}$/.test(valeur);
}

/** Couleur de marque sûre à utiliser telle quelle (texte, pastille, bordure). */
export function couleurMarque(couleur?: string | null): string {
  return hexValide(couleur) ? couleur : COULEUR_MARQUE_REPLI;
}

/** Couleur d'un format : d'abord la marque du référentiel, puis le format lui-même. */
export function couleurPourFormat(format: Format | null | undefined, marques: Marque[]): string {
  if (!format) return COULEUR_MARQUE_REPLI;
  const marque = marques.find((m) => m.nom === format.marque);
  return couleurMarque(marque?.couleur ?? format.couleur);
}

/** Même couleur, éclaircie via alpha - pour fonds/dégradés sobres. */
export function teinteMarque(couleur: string | null | undefined, alpha: number): string {
  const hex = couleurMarque(couleur).replace('#', '');
  const r = parseInt(hex.substring(0, 2), 16);
  const g = parseInt(hex.substring(2, 4), 16);
  const b = parseInt(hex.substring(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export interface MarqueGroupee {
  nom: string;
  couleur: string;
  formats: Format[];
}

/** Regroupe des formats par nom de marque, chacun avec sa couleur canonique. */
export function grouperFormatsParMarque(formats: Format[], marques: Marque[]): MarqueGroupee[] {
  const parNom = new Map<string, Format[]>();
  for (const format of formats) {
    const liste = parNom.get(format.marque) ?? [];
    liste.push(format);
    parNom.set(format.marque, liste);
  }
  return Array.from(parNom.entries()).map(([nom, liste]) => ({
    nom,
    couleur: couleurPourFormat(liste[0], marques),
    formats: liste,
  }));
}
