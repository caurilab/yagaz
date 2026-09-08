/**
 * Pack d'icônes de marque Yagaz.
 *
 * Icônes vectorielles maison (react-native-svg), dessinées sur une grille
 * 24x24, trait arrondi et régulier - cohérentes entre elles et avec le motif
 * "flamme" central de la marque. Monochromes : la couleur est posée par le
 * contexte (`couleur`), comme les anciennes icônes plates, mais ici le tracé
 * appartient au produit et parle son vocabulaire (bouteille, balance,
 * thermomètre, écran, livraison, dépôt, jauge...).
 *
 * Usage :
 *   <Icone nom="flamme" taille={22} couleur={couleurs.rouge} />
 *
 * Les illustrations riches de bouteilles (proportions par format, niveau de
 * liquide) restent dans `BouteilleGaz` ; ici `bouteille` est le pictogramme
 * simple d'inventaire/menu.
 */
import type { ReactNode } from 'react';
import Svg, { Circle, G, Line, Path, Rect } from 'react-native-svg';
import { couleurs } from '../../../theme/couleurs';

export type NomIcone =
  | 'flamme'
  | 'accueil'
  | 'commande'
  | 'historique'
  | 'analyse'
  | 'reglages'
  | 'cloche'
  | 'horloge'
  | 'thermometre'
  | 'balance'
  | 'ecran'
  | 'livraison'
  | 'depot'
  | 'localisation'
  | 'recu'
  | 'qr'
  | 'jauge'
  | 'goutte'
  | 'check'
  | 'chevron'
  | 'plus'
  | 'corbeille'
  | 'cuisine'
  | 'bouteille'
  | 'wifi'
  | 'bluetooth'
  | 'eclair'
  | 'fermer'
  | 'crayon'
  | 'calendrier'
  | 'camembert'
  | 'tendance'
  | 'annonce'
  | 'repeat'
  | 'alerte'
  | 'cercle'
  | 'flecheHaut'
  | 'flecheBas';

type Props = {
  nom: NomIcone;
  taille?: number;
  couleur?: string;
};

/**
 * Fabriques de tracé par icône. Le trait (stroke, épaisseur, arrondis) est
 * porté par le `<G>` parent : chaque fabrique ne décrit que la géométrie, et
 * n'ajoute `fill` que sur les pleins volontaires (points, pastilles).
 */
const TRACES: Record<NomIcone, (c: string) => ReactNode> = {
  flamme: () => (
    <Path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z" />
  ),
  accueil: () => (
    <>
      <Path d="M3 11l9-7 9 7" />
      <Path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" />
    </>
  ),
  commande: (c) => (
    <>
      <Path d="M4 4h2l2.2 12.3a1.5 1.5 0 0 0 1.5 1.2h7.9a1.5 1.5 0 0 0 1.5-1.2L21 8H6.5" />
      <Circle cx={10} cy={20.5} r={1.4} fill={c} stroke="none" />
      <Circle cx={18} cy={20.5} r={1.4} fill={c} stroke="none" />
    </>
  ),
  historique: () => (
    <>
      <Path d="M3.5 4v4.5H8" />
      <Path d="M3.8 12a8.5 8.5 0 1 0 2.6-6.2L3.5 8.5" />
      <Path d="M12 8v4.5l3 1.8" />
    </>
  ),
  analyse: () => (
    <>
      <Rect x={4} y={13} width={3.6} height={7} rx={1.2} />
      <Rect x={10.2} y={8.5} width={3.6} height={11.5} rx={1.2} />
      <Rect x={16.4} y={4.5} width={3.6} height={15.5} rx={1.2} />
    </>
  ),
  reglages: () => (
    <>
      <Circle cx={12} cy={12} r={3} />
      <Path d="M12.2 2.2h-.4a2 2 0 0 0-2 2v.2a2 2 0 0 1-1 1.7l-.4.3a2 2 0 0 1-2 0l-.2-.1a2 2 0 0 0-2.7.7l-.2.4a2 2 0 0 0 .7 2.7l.2.1a2 2 0 0 1 1 1.7v.5a2 2 0 0 1-1 1.7l-.2.1a2 2 0 0 0-.7 2.7l.2.4a2 2 0 0 0 2.7.7l.2-.1a2 2 0 0 1 2 0l.4.3a2 2 0 0 1 1 1.7v.2a2 2 0 0 0 2 2h.4a2 2 0 0 0 2-2v-.2a2 2 0 0 1 1-1.7l.4-.3a2 2 0 0 1 2 0l.2.1a2 2 0 0 0 2.7-.7l.2-.4a2 2 0 0 0-.7-2.7l-.2-.1a2 2 0 0 1-1-1.7v-.5a2 2 0 0 1 1-1.7l.2-.1a2 2 0 0 0 .7-2.7l-.2-.4a2 2 0 0 0-2.7-.7l-.2.1a2 2 0 0 1-2 0l-.4-.3a2 2 0 0 1-1-1.7v-.2a2 2 0 0 0-2-2z" />
    </>
  ),
  cloche: () => (
    <>
      <Path d="M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z" />
      <Path d="M10.2 20a2 2 0 0 0 3.6 0" />
    </>
  ),
  horloge: () => (
    <>
      <Circle cx={12} cy={12} r={8.5} />
      <Path d="M12 7v5l3.2 1.9" />
    </>
  ),
  thermometre: (c) => (
    <>
      <Path d="M14 14.8V5a2.5 2.5 0 0 0-5 0v9.8a4.5 4.5 0 1 0 5 0z" />
      <Circle cx={11.5} cy={16.5} r={1.7} fill={c} stroke="none" />
    </>
  ),
  balance: () => (
    <>
      <Rect x={3.5} y={3.5} width={17} height={17} rx={4} />
      <Path d="M8.2 15.5a4 4 0 0 1 7.6 0" />
      <Path d="M12 15.2l2.6-2.9" />
    </>
  ),
  ecran: () => (
    <>
      <Rect x={3} y={4.5} width={18} height={12.5} rx={2.2} />
      <Line x1={8.5} y1={20.5} x2={15.5} y2={20.5} />
      <Line x1={12} y1={17} x2={12} y2={20.5} />
      <Path d="M12 8.2c1.2 1.1.5 2 .9 2.6.3.5 1 .3 1.1 1a1.9 1.9 0 0 1-3.8.2c0-.6.2-1 .5-1.4.1.5.4.7.7.8-.3-1 .1-2.4.6-3.2z" />
    </>
  ),
  livraison: () => (
    <>
      <Rect x={3} y={7} width={11} height={8} rx={1.4} />
      <Path d="M14 10h3.4l2.6 3v2H14z" />
      <Circle cx={7} cy={17.5} r={2} />
      <Circle cx={17} cy={17.5} r={2} />
      <Line x1={9} y1={17.5} x2={15} y2={17.5} />
    </>
  ),
  depot: () => (
    <>
      <Path d="M3 20.5V9.2a2 2 0 0 1 1.3-1.9l7-2.8a2 2 0 0 1 1.4 0l7 2.8A2 2 0 0 1 21 9.2v11.3" />
      <Line x1={2.5} y1={20.5} x2={21.5} y2={20.5} />
      <Line x1={7} y1={17} x2={17} y2={17} />
      <Line x1={7} y1={13.5} x2={17} y2={13.5} />
    </>
  ),
  localisation: (c) => (
    <>
      <Path d="M19.5 10.5c0 5.5-7.5 11-7.5 11s-7.5-5.5-7.5-11a7.5 7.5 0 0 1 15 0z" />
      <Circle cx={12} cy={10.3} r={2.6} fill={c} stroke="none" />
    </>
  ),
  recu: () => (
    <>
      <Path d="M6 3h12v18l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3L6 21z" />
      <Line x1={9} y1={8} x2={15} y2={8} />
      <Line x1={9} y1={12} x2={15} y2={12} />
      <Line x1={9} y1={16} x2={13} y2={16} />
    </>
  ),
  qr: () => (
    <>
      <Path d="M4 8.5V6a2 2 0 0 1 2-2h2.5" />
      <Path d="M15.5 4H18a2 2 0 0 1 2 2v2.5" />
      <Path d="M20 15.5V18a2 2 0 0 1-2 2h-2.5" />
      <Path d="M8.5 20H6a2 2 0 0 1-2-2v-2.5" />
      <Rect x={8} y={8} width={3.2} height={3.2} rx={0.7} />
      <Rect x={12.8} y={12.8} width={3.2} height={3.2} rx={0.7} />
    </>
  ),
  jauge: (c) => (
    <>
      <Path d="M4 16.5a8 8 0 0 1 16 0" />
      <Path d="M12 16.5l4.2-3.4" />
      <Circle cx={12} cy={16.5} r={1.4} fill={c} stroke="none" />
    </>
  ),
  goutte: () => <Path d="M12 3s6.5 6.8 6.5 11.3a6.5 6.5 0 0 1-13 0C5.5 9.8 12 3 12 3z" />,
  check: () => (
    <>
      <Circle cx={12} cy={12} r={8.5} />
      <Path d="M8.3 12.3l2.6 2.6 4.8-5.2" />
    </>
  ),
  chevron: () => <Path d="M9 5.5l6.5 6.5L9 18.5" />,
  plus: () => (
    <>
      <Line x1={12} y1={5} x2={12} y2={19} />
      <Line x1={5} y1={12} x2={19} y2={12} />
    </>
  ),
  corbeille: () => (
    <>
      <Line x1={4} y1={7} x2={20} y2={7} />
      <Path d="M9 7V5.2a1.2 1.2 0 0 1 1.2-1.2h3.6A1.2 1.2 0 0 1 15 5.2V7" />
      <Path d="M6.2 7l.9 13a1.2 1.2 0 0 0 1.2 1.1h7.4a1.2 1.2 0 0 0 1.2-1.1l.9-13" />
      <Line x1={10} y1={11} x2={10} y2={17.5} />
      <Line x1={14} y1={11} x2={14} y2={17.5} />
    </>
  ),
  cuisine: () => (
    <>
      <Path d="M4 11h16v2.5a5.5 5.5 0 0 1-5.5 5.5h-5A5.5 5.5 0 0 1 4 13.5z" />
      <Path d="M4 11 2.7 8.8" />
      <Path d="M20 11l1.3-2.2" />
      <Path d="M9.5 5c-.6.8-.6 1.5 0 2.3" />
      <Path d="M14.5 5c-.6.8-.6 1.5 0 2.3" />
    </>
  ),
  bouteille: () => (
    <>
      <Rect x={10.3} y={2.5} width={3.4} height={3} rx={0.6} />
      <Line x1={9} y1={6.5} x2={15} y2={6.5} />
      <Path d="M8 10.5a4 4 0 0 1 8 0v7.5a2.5 2.5 0 0 1-2.5 2.5h-3A2.5 2.5 0 0 1 8 18z" />
    </>
  ),
  wifi: (c) => (
    <>
      <Path d="M4.5 11.5a11 11 0 0 1 15 0" />
      <Path d="M7.5 14.8a6.5 6.5 0 0 1 9 0" />
      <Circle cx={12} cy={18.4} r={1.4} fill={c} stroke="none" />
    </>
  ),
  bluetooth: () => <Path d="M7 8l10 8-5 4V4l5 4-10 8" />,
  eclair: () => <Path d="M13 2.5 4.5 13.5H11l-1 8 8.5-11H12l1-8z" />,
  fermer: () => (
    <>
      <Line x1={6} y1={6} x2={18} y2={18} />
      <Line x1={18} y1={6} x2={6} y2={18} />
    </>
  ),
  crayon: () => (
    <>
      <Path d="M4 20l.9-3.6a2 2 0 0 1 .5-.9L15.4 5.5a2 2 0 0 1 2.8 0l.3.3a2 2 0 0 1 0 2.8L8.5 19.1a2 2 0 0 1-.9.5L4 20z" />
      <Line x1={13.5} y1={7.4} x2={16.6} y2={10.5} />
    </>
  ),
  calendrier: () => (
    <>
      <Rect x={3.5} y={5} width={17} height={15.5} rx={2.5} />
      <Line x1={3.5} y1={9.5} x2={20.5} y2={9.5} />
      <Line x1={8} y1={3} x2={8} y2={6.5} />
      <Line x1={16} y1={3} x2={16} y2={6.5} />
    </>
  ),
  camembert: () => (
    <>
      <Circle cx={12} cy={12} r={8.5} />
      <Line x1={12} y1={12} x2={12} y2={3.5} />
      <Line x1={12} y1={12} x2={20.5} y2={12} />
    </>
  ),
  tendance: () => (
    <>
      <Path d="M3 16.5l5.5-5.5 3.5 3.5L21 6.5" />
      <Path d="M15.5 6.5H21v5.5" />
    </>
  ),
  annonce: () => (
    <>
      <Path d="M3.5 10.5 17 6v12L3.5 13.5z" />
      <Path d="M17 8.5a3 3 0 0 1 0 7" />
      <Path d="M7 12.5v4.2a1.8 1.8 0 0 0 3.5.6" />
    </>
  ),
  repeat: () => (
    <>
      <Path d="M16.5 2.5 20.5 6l-4 3.5" />
      <Path d="M3.5 11v-.5a4 4 0 0 1 4-4h13" />
      <Path d="M7.5 21.5 3.5 18l4-3.5" />
      <Path d="M20.5 13v.5a4 4 0 0 1-4 4h-13" />
    </>
  ),
  alerte: (c) => (
    <>
      <Path d="M10.3 4.2 2.8 17.5a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0z" />
      <Line x1={12} y1={9.5} x2={12} y2={13.5} />
      <Circle cx={12} cy={17} r={1.1} fill={c} stroke="none" />
    </>
  ),
  cercle: () => <Circle cx={12} cy={12} r={4.5} />,
  flecheHaut: () => (
    <>
      <Line x1={12} y1={19} x2={12} y2={5.5} />
      <Path d="M6.5 11 12 5.5l5.5 5.5" />
    </>
  ),
  flecheBas: () => (
    <>
      <Line x1={12} y1={5} x2={12} y2={18.5} />
      <Path d="M6.5 13 12 18.5l5.5-5.5" />
    </>
  ),
};

/** Icône de marque monochrome. `couleur` par défaut : texte principal. */
export function Icone({ nom, taille = 24, couleur = couleurs.texte }: Props) {
  return (
    <Svg width={taille} height={taille} viewBox="0 0 24 24" fill="none">
      <G stroke={couleur} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" fill="none">
        {TRACES[nom](couleur)}
      </G>
    </Svg>
  );
}
