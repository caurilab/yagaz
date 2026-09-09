import Svg, { Circle, G, Line, Path, Rect } from 'react-native-svg';

import { couleurs } from '../../../theme/couleurs';
import type { CategorieAstuce } from '../../data/astuces';

interface Props {
  categorie: CategorieAstuce;
  taille?: number;
}

/**
 * Illustrations vectorielles par catégorie d'astuce (sécurité / économie /
 * gestion), pensées comme des visuels de remplacement : un fond rond doux
 * (orange clair) et un motif à plat aux tons de marque. Faciles à substituer
 * plus tard par de vraies illustrations - il suffit de remplacer le motif
 * correspondant, l'API (`categorie`, `taille`) reste la même.
 */
export function IllustrationAstuce({ categorie, taille = 128 }: Props) {
  return (
    <Svg width={taille} height={taille} viewBox="0 0 120 120">
      <Circle cx={60} cy={60} r={58} fill={couleurs.rougeClair} />
      {categorie === 'securite' ? <MotifSecurite /> : null}
      {categorie === 'economie' ? <MotifEconomie /> : null}
      {categorie === 'gestion' ? <MotifGestion /> : null}
    </Svg>
  );
}

/** Bouclier + flamme : la sécurité gaz avant tout. */
function MotifSecurite() {
  return (
    <G>
      <Path
        d="M60 24 L90 36 L90 60 Q90 86 60 100 Q30 86 30 60 L30 36 Z"
        fill={couleurs.blanc}
        stroke={couleurs.rouge}
        strokeWidth={3}
        strokeLinejoin="round"
      />
      <Path
        d="M60 46 C67 55 71 61 71 69 A11 11 0 1 1 49 69 C49 63 53 57 60 46 Z"
        fill={couleurs.rouge}
      />
      <Path d="M60 60 C63 64 65 67 65 71 A5 5 0 1 1 55 71 C55 68 57 65 60 60 Z" fill={couleurs.ambre} />
    </G>
  );
}

/** Marmite couverte + flamme douce : cuire malin, économiser le gaz. */
function MotifEconomie() {
  return (
    <G>
      {/* Couvercle */}
      <Line x1={30} y1={54} x2={90} y2={54} stroke={couleurs.rouge} strokeWidth={4} strokeLinecap="round" />
      <Circle cx={60} cy={46} r={5} fill={couleurs.rouge} />
      {/* Corps de la marmite */}
      <Path
        d="M32 56 L88 56 L84 84 Q83 90 77 90 L43 90 Q37 90 36 84 Z"
        fill={couleurs.blanc}
        stroke={couleurs.rouge}
        strokeWidth={3}
        strokeLinejoin="round"
      />
      {/* Poignées */}
      <Path d="M32 62 Q24 62 24 68" fill="none" stroke={couleurs.rouge} strokeWidth={3} strokeLinecap="round" />
      <Path d="M88 62 Q96 62 96 68" fill="none" stroke={couleurs.rouge} strokeWidth={3} strokeLinecap="round" />
      {/* Petite flamme douce sous la marmite */}
      <Path d="M60 92 C64 97 66 100 66 103 A6 6 0 1 1 54 103 C54 100 56 97 60 92 Z" fill={couleurs.ambre} />
    </G>
  );
}

/** Bouteille + jauge : garder une bouteille de secours, recharger à temps. */
function MotifGestion() {
  return (
    <G>
      {/* Jauge (arc) */}
      <Path d="M34 78 A26 26 0 0 1 86 78" fill="none" stroke={couleurs.blanc} strokeWidth={8} strokeLinecap="round" />
      <Path d="M34 78 A26 26 0 0 1 52 54" fill="none" stroke={couleurs.rouge} strokeWidth={8} strokeLinecap="round" />
      {/* Aiguille */}
      <Line x1={60} y1={78} x2={74} y2={62} stroke={couleurs.rouge} strokeWidth={4} strokeLinecap="round" />
      <Circle cx={60} cy={78} r={5} fill={couleurs.rouge} />
      {/* Petite bouteille */}
      <Rect x={50} y={30} width={20} height={8} rx={3} fill={couleurs.rouge} />
      <Path
        d="M52 38 L68 38 L68 44 Q72 48 72 54 L48 54 Q48 48 52 44 Z"
        fill={couleurs.blanc}
        stroke={couleurs.rouge}
        strokeWidth={2.5}
        strokeLinejoin="round"
      />
    </G>
  );
}
