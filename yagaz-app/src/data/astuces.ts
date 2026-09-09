import type { NomIcone } from '../components/icones';

/** Catégorie d'astuce : sécurité, économie de gaz, ou gestion des bouteilles. */
export type CategorieAstuce = 'securite' | 'economie' | 'gestion';

export interface Astuce {
  id: string;
  categorie: CategorieAstuce;
  titre: string;
  message: string;
  icone: NomIcone;
}

/**
 * Astuces gaz affichées dans la modale accessible depuis la carte "Astuce"
 * de l'accueil. Ton tutoiement, messages courts (1 à 3 phrases).
 */
export const ASTUCES: Astuce[] = [
  {
    id: 'securite-tuyau',
    categorie: 'securite',
    titre: 'Vérifie ton tuyau et ton détendeur',
    message:
      "Inspecte régulièrement le tuyau et le détendeur : craquelures, mou, odeur suspecte. Remplace-les au moindre doute, avant qu'ils ne lâchent.",
    icone: 'alerte',
  },
  {
    id: 'securite-odeur',
    categorie: 'securite',
    titre: "En cas d'odeur de gaz, ferme et aère",
    message:
      "Si tu sens une odeur de gaz, ferme immédiatement le robinet de la bouteille, coupe toute flamme et aère la pièce en ouvrant portes et fenêtres.",
    icone: 'alerte',
  },
  {
    id: 'securite-rangement',
    categorie: 'securite',
    titre: "Range la bouteille debout, à l'écart de la chaleur",
    message:
      "Garde toujours ta bouteille debout, dans un endroit ventilé, loin d'une source de chaleur ou du soleil direct.",
    icone: 'alerte',
  },
  {
    id: 'economie-couvercle',
    categorie: 'economie',
    titre: 'Couvre tes marmites pendant la cuisson',
    message:
      "Un couvercle sur la marmite garde la chaleur à l'intérieur : la cuisson est plus rapide et tu consommes moins de gaz.",
    icone: 'eclair',
  },
  {
    id: 'economie-feu-doux',
    categorie: 'economie',
    titre: "Baisse le feu après l'ébullition",
    message:
      "Une fois que l'eau ou la sauce bout, réduis la flamme au minimum : la cuisson continue sans gaspiller de gaz.",
    icone: 'eclair',
  },
  {
    id: 'economie-taille-flamme',
    categorie: 'economie',
    titre: 'Adapte la flamme à la taille de ta casserole',
    message:
      "Une flamme plus large que le fond de la casserole chauffe surtout l'air autour. Ajuste-la à la taille du récipient.",
    icone: 'eclair',
  },
  {
    id: 'gestion-secours',
    categorie: 'gestion',
    titre: 'Garde toujours une bouteille de secours pleine',
    message:
      "Avoir une bouteille de secours pleine t'évite les ruptures en pleine cuisson. Pense à la recharger dès qu'elle sert.",
    icone: 'flamme',
  },
  {
    id: 'gestion-seuil',
    categorie: 'gestion',
    titre: 'Recharge avant de descendre sous 15%',
    message:
      "Ne laisse pas ta bouteille active descendre sous 15% sans prévoir une recharge : tu gardes toujours une marge tranquille.",
    icone: 'jauge',
  },
];

/** Libellés courts pour l'étiquette de catégorie affichée dans la modale. */
export const LIBELLES_CATEGORIE_ASTUCE: Record<CategorieAstuce, string> = {
  securite: 'Sécurité',
  economie: 'Économie',
  gestion: 'Gestion',
};
