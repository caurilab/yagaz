/**
 * Palette et jetons visuels de l'application Yagaz.
 * Direction : rouge corail + blanc, cartes arrondies, grands chiffres.
 * L'information reine côté foyer est l'autonomie en heures de flamme.
 */

export const couleurs = {
  rouge: '#EF4B4B',
  degradeDebut: '#F76B6B',
  degradeFin: '#EF4B4B',
  rougeClair: '#FDECEC',
  texte: '#1F2430',
  texteDoux: '#6B7280',
  fond: '#F7F7F9',
  carte: '#FFFFFF',
  vertOk: '#34C77B',
  bordure: '#ECECF0',
  blanc: '#FFFFFF',
  ambre: '#F5A623',
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
