/**
 * Palette et jetons visuels de l'application Yagaz.
 * Direction : orange chaud (fintech-like) + fond crème, cartes blanches
 * arrondies, grands chiffres. L'information reine côté foyer est
 * l'autonomie en heures de flamme. Les couleurs de marque (ambiance par
 * bouteille) restent un accent posé par-dessus cette base orange.
 *
 * Les noms de clés sont conservés à l'identique pour ne pas casser les
 * imports existants ; seules les valeurs changent.
 */

export const couleurs = {
  rouge: '#F5741E', // orange principal
  degradeDebut: '#FF9D4D',
  degradeFin: '#EA580C', // orange foncé
  rougeClair: '#FFE7D1', // orange clair
  texte: '#1F2430',
  texteDoux: '#6B7280',
  fond: '#FFF8F2', // fond crème
  carte: '#FFFFFF',
  vertOk: '#22C55E',
  bordure: '#F0E6DD',
  blanc: '#FFFFFF',
  ambre: '#F5A623',
  danger: '#EF4444', // alerte / erreur
  grisNeutre: '#9CA3AF',
} as const;

export const espacements = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 48,
} as const;

export const rayons = {
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  rond: 999,
} as const;

export type Couleurs = typeof couleurs;
