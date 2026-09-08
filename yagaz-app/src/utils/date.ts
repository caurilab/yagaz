/**
 * Formatage de dates relatives/courtes pour la timeline historique (doc 13
 * §1) et les encarts température - français, sans logique métier.
 */

const MINUTE_MS = 60_000;
const HEURE_MS = 60 * MINUTE_MS;
const JOUR_MS = 24 * HEURE_MS;

/** "il y a 12 min" / "il y a 3 h" / "il y a 2 j" / date courte au-delà d'une semaine. */
export function formaterDateRelative(iso: string): string {
  const ecart = Date.now() - new Date(iso).getTime();
  if (ecart < MINUTE_MS) return "à l'instant";
  if (ecart < HEURE_MS) return `il y a ${Math.floor(ecart / MINUTE_MS)} min`;
  if (ecart < JOUR_MS) return `il y a ${Math.floor(ecart / HEURE_MS)} h`;
  if (ecart < 7 * JOUR_MS) return `il y a ${Math.floor(ecart / JOUR_MS)} j`;
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Date courte "08 sept." pour les libellés d'axe/légende. */
export function formaterDateCourte(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

/** Montant FCFA formaté sans décimales, séparateurs français. */
export function formaterMontantFcfa(montant: number): string {
  return `${Math.round(montant).toLocaleString('fr-FR')} FCFA`;
}
