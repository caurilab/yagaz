/**
 * Silhouette vectorielle de bouteille de gaz domestique - `react-native-svg`,
 * léger et réutilisable. Deux silhouettes réalistes selon le format (voir
 * `code`) :
 * - « haute » (B12/B24, défaut) : corps élancé, épaulement, col avec
 *   collerette/poignée de protection (arceau ajouré), valve.
 * - « trapue » (B6/B3) : corps court et arrondi, épaulement bombé, petit
 *   col, valve - sans grand arceau.
 *
 * Le corps est toujours peint dans la couleur de marque (`couleur`), avec un
 * dégradé horizontal (bord clair = lumière, bord foncé = ombre) et un reflet
 * vertical pour un rendu 3D. Le niveau de gaz (`niveauPct`) assombrit la
 * partie vide en haut du corps, sans jamais faire disparaître la couleur de
 * marque (reste joli quand le niveau est inconnu).
 *
 * Un badge circulaire blanc porte le monogramme de la marque (initiale de
 * `marqueNom`) dans un coin - jamais le nom en toutes lettres sur le corps.
 * `useId` évite les collisions d'identifiants SVG entre instances.
 */
import { useId } from 'react';
import { Image, StyleSheet, Text, View, type ImageSourcePropType } from 'react-native';
import Svg, { Circle, ClipPath, Defs, LinearGradient, Path, Rect, Stop, Text as TexteSvg } from 'react-native-svg';

import { couleurMarque } from '../utils/marque';

// --- Emplacement prévu pour de vrais logos de marque -----------------------
// TODO(logos-marques) : quand des logos réels seront fournis, les déposer
// dans `assets/logos/<marque>.png` (fond transparent, carré) puis les
// référencer ci-dessous. Tant que cet objet est vide, le monogramme
// (initiale de `marqueNom`) sert de repli visuel.
//
// const LOGOS_MARQUES: Record<string, ImageSourcePropType> = {
//   Oryx: require('../../assets/logos/oryx.png'),
//   Total: require('../../assets/logos/total.png'),
//   'Petro Ivoire': require('../../assets/logos/petro-ivoire.png'),
//   Corlay: require('../../assets/logos/corlay.png'),
//   Sodigaz: require('../../assets/logos/sodigaz.png'),
// };
const LOGOS_MARQUES: Record<string, ImageSourcePropType> = {};

/** Formats à silhouette trapue/ronde ; tout le reste (dont code absent) est « haute ». */
const FORMATS_TRAPUS = new Set(['B3', 'B6']);

function estTrapu(code?: string | null): boolean {
  return !!code && FORMATS_TRAPUS.has(code.trim().toUpperCase());
}

function initialeMarque(nom?: string | null): string | null {
  const lettre = nom?.trim().charAt(0).toUpperCase();
  return lettre || null;
}

/** Mélange `hex` vers `cible` (blanc/noir) selon `ratio` (0 = hex, 1 = cible). */
function melangerCouleur(hex: string, cible: string, ratio: number): string {
  const h = hex.replace('#', '');
  const c = cible.replace('#', '');
  if (h.length !== 6 || c.length !== 6) return hex;
  const composante = (debut: number) => {
    const v1 = parseInt(h.substring(debut, debut + 2), 16);
    const v2 = parseInt(c.substring(debut, debut + 2), 16);
    return Math.round(v1 + (v2 - v1) * ratio)
      .toString(16)
      .padStart(2, '0');
  };
  return `#${composante(0)}${composante(2)}${composante(4)}`;
}

const eclaircir = (hex: string, ratio: number) => melangerCouleur(hex, '#FFFFFF', ratio);
const assombrir = (hex: string, ratio: number) => melangerCouleur(hex, '#000000', ratio);

/** Silhouette "haute" (B12/B24) : corps + épaulement + col, sans la collerette (dessinée à part). */
const SILHOUETTE_HAUTE = `
  M 42 36
  L 58 36
  L 58 48
  Q 78 56 82 74
  L 82 184
  Q 82 202 64 202
  L 36 202
  Q 18 202 18 184
  L 18 74
  Q 22 56 42 48
  Z
`;
/** Collerette/poignée de protection (arceau ajouré) de la silhouette haute, en évidement (fillRule evenodd). */
const COLLERETTE_HAUTE = `
  M 26 52 L 26 38 Q 26 22 40 20 L 60 20 Q 74 22 74 38 L 74 52 Z
  M 31 26 L 38 26 Q 40 26 40 28 L 40 46 Q 40 48 38 48 L 31 48 Q 29 48 29 46 L 29 28 Q 29 26 31 26 Z
  M 46.5 24 L 53.5 24 Q 55.5 24 55.5 26 L 55.5 47 Q 55.5 49 53.5 49 L 46.5 49 Q 44.5 49 44.5 47 L 44.5 26 Q 44.5 24 46.5 24 Z
  M 62 26 L 69 26 Q 71 26 71 28 L 71 46 Q 71 48 69 48 L 62 48 Q 60 48 60 46 L 60 28 Q 60 26 62 26 Z
`;
const VALVE_HAUTE = { x: 43, y: 8, w: 14, h: 22, rx: 5, cxKnob: 50, cyKnob: 10, rKnob: 5.5 };
const VUE_HAUTE = { largeur: 100, hauteur: 210 };
const BADGE_MARQUE_HAUTE = { cx: 71, cy: 88, r: 10.5 };
const BADGE_CODE_TOP_RATIO_HAUTE = 0.56;

/** Silhouette "trapue/ronde" (B6/B3) : corps court et bombé, petit col, sans collerette. */
const SILHOUETTE_TRAPUE = `
  M 44 10
  L 56 10
  L 56 20
  Q 82 28 86 50
  L 86 108
  Q 86 130 62 130
  L 38 130
  Q 14 130 14 108
  L 14 50
  Q 18 28 44 20
  Z
`;
const VALVE_TRAPUE = { x: 44, y: 0, w: 12, h: 14, rx: 4.5, cxKnob: 50, cyKnob: 1.5, rKnob: 4.5 };
const VUE_TRAPUE = { largeur: 100, hauteur: 140 };
const BADGE_MARQUE_TRAPUE = { cx: 73, cy: 56, r: 9.5 };
const BADGE_CODE_TOP_RATIO_TRAPUE = 0.6;

interface Props {
  /** Couleur de marque (hex). Repli gris neutre si absente ou invalide. */
  couleur?: string | null;
  /** Code du format (ex. "B12"), affiché en badge sur la bouteille et utilisé pour choisir la silhouette. */
  code?: string;
  /** Nom de la marque (ex. "Oryx"), dont l'initiale forme le monogramme du coin. */
  marqueNom?: string | null;
  /** Niveau de remplissage 0-100. Sans valeur : corps plein et coloré, sans assombrissement. */
  niveauPct?: number;
  /** Hauteur en px (largeur déduite du ratio de la silhouette). */
  taille?: number;
  /** Petit reflet lumineux sur le corps (défaut : oui). */
  reflet?: boolean;
}

export function BouteilleGaz({ couleur, code, marqueNom, niveauPct, taille = 140, reflet = true }: Props) {
  const idBase = useId();
  const idClip = `bouteille-clip-${idBase}`;
  const idGradient = `bouteille-gradient-${idBase}`;

  const trapue = estTrapu(code);
  const silhouette = trapue ? SILHOUETTE_TRAPUE : SILHOUETTE_HAUTE;
  const valve = trapue ? VALVE_TRAPUE : VALVE_HAUTE;
  const vue = trapue ? VUE_TRAPUE : VUE_HAUTE;
  const badgeMarque = trapue ? BADGE_MARQUE_TRAPUE : BADGE_MARQUE_HAUTE;
  const ratioBadgeCode = trapue ? BADGE_CODE_TOP_RATIO_TRAPUE : BADGE_CODE_TOP_RATIO_HAUTE;

  const couleurPleine = couleurMarque(couleur);
  const couleurClaire = eclaircir(couleurPleine, 0.42);
  const couleurOmbre = assombrir(couleurPleine, 0.28);
  const couleurCollerette = assombrir(couleurPleine, 0.18);

  const pct = niveauPct != null ? Math.max(0, Math.min(100, niveauPct)) : null;
  // Partie "vide" en haut du corps, assombrie proportionnellement (0 si niveau inconnu ou plein).
  const hauteurVide = pct != null ? ((100 - pct) / 100) * (vue.hauteur - valve.y - valve.h) : 0;
  const yVide = valve.y + valve.h;

  const hauteur = taille;
  const largeur = taille * (vue.largeur / vue.hauteur);

  const initiale = initialeMarque(marqueNom);
  const logo = marqueNom ? LOGOS_MARQUES[marqueNom] : undefined;

  return (
    <View style={{ width: largeur, height: hauteur, alignItems: 'center', justifyContent: 'center' }}>
      <Svg width={largeur} height={hauteur} viewBox={`0 0 ${vue.largeur} ${vue.hauteur}`}>
        <Defs>
          <ClipPath id={idClip}>
            <Path d={silhouette} />
          </ClipPath>
          <LinearGradient id={idGradient} x1="0" y1="0" x2="1" y2="0">
            <Stop offset="0" stopColor={couleurOmbre} stopOpacity={1} />
            <Stop offset="0.28" stopColor={couleurClaire} stopOpacity={1} />
            <Stop offset="0.6" stopColor={couleurPleine} stopOpacity={1} />
            <Stop offset="1" stopColor={couleurOmbre} stopOpacity={1} />
          </LinearGradient>
        </Defs>

        {/* Corps peint dans la couleur de marque, en dégradé (volume/lumière) */}
        <Path d={silhouette} fill={`url(#${idGradient})`} />

        {/* Reflet vertical léger, côté lumière */}
        {reflet ? (
          <Rect
            x={vue.largeur * 0.22}
            y={vue.hauteur * 0.4}
            width={vue.largeur * 0.07}
            height={vue.hauteur * 0.42}
            rx={vue.largeur * 0.035}
            fill="#FFFFFF"
            opacity={0.32}
            clipPath={`url(#${idClip})`}
          />
        ) : null}

        {/* Niveau de gaz : assombrit la partie vide en haut du corps, garde la couleur en dessous */}
        {pct != null && hauteurVide > 0 ? (
          <>
            <Rect
              x={0}
              y={yVide}
              width={vue.largeur}
              height={hauteurVide}
              fill="#000000"
              opacity={0.24}
              clipPath={`url(#${idClip})`}
            />
            <Rect
              x={0}
              y={yVide + hauteurVide}
              width={vue.largeur}
              height={1.4}
              fill="#FFFFFF"
              opacity={0.4}
              clipPath={`url(#${idClip})`}
            />
          </>
        ) : null}

        {/* Contour net */}
        <Path d={silhouette} fill="none" stroke={couleurOmbre} strokeWidth={1.5} strokeOpacity={0.5} />

        {/* Collerette/poignée de protection ajourée (silhouette haute uniquement) */}
        {!trapue ? (
          <Path
            d={COLLERETTE_HAUTE}
            fill={couleurCollerette}
            fillRule="evenodd"
            stroke={assombrir(couleurPleine, 0.4)}
            strokeWidth={0.8}
          />
        ) : null}

        {/* Valve : corps gris, capuchon rouge */}
        <Rect
          x={valve.x}
          y={valve.y}
          width={valve.w}
          height={valve.h}
          rx={valve.rx}
          fill="#6B7280"
          stroke="#4B5563"
          strokeWidth={0.6}
        />
        <Path
          d={`M ${valve.cxKnob - valve.rKnob} ${valve.cyKnob + valve.rKnob}
              Q ${valve.cxKnob - valve.rKnob} ${valve.cyKnob - valve.rKnob} ${valve.cxKnob} ${valve.cyKnob - valve.rKnob}
              Q ${valve.cxKnob + valve.rKnob} ${valve.cyKnob - valve.rKnob} ${valve.cxKnob + valve.rKnob} ${valve.cyKnob + valve.rKnob}
              Z`}
          fill="#C0392B"
        />

        {/* Monogramme de marque (repli tant qu'aucun vrai logo n'est fourni via LOGOS_MARQUES) */}
        {initiale && !logo ? (
          <>
            <Circle cx={badgeMarque.cx} cy={badgeMarque.cy} r={badgeMarque.r} fill="#FFFFFF" opacity={0.96} />
            <TexteSvg
              x={badgeMarque.cx}
              y={badgeMarque.cy + badgeMarque.r * 0.36}
              fontSize={badgeMarque.r * 1.15}
              fontWeight="800"
              fill={couleurPleine}
              textAnchor="middle"
            >
              {initiale}
            </TexteSvg>
          </>
        ) : null}
      </Svg>

      {/* Vrai logo de marque (dès que `LOGOS_MARQUES` est renseigné) - même coin que le monogramme */}
      {logo ? (
        <Image
          source={logo}
          style={[
            styles.logoMarque,
            {
              width: (badgeMarque.r * 2 * largeur) / vue.largeur,
              height: (badgeMarque.r * 2 * largeur) / vue.largeur,
              left: ((badgeMarque.cx - badgeMarque.r) * largeur) / vue.largeur,
              top: ((badgeMarque.cy - badgeMarque.r) * hauteur) / vue.hauteur,
            },
          ]}
          resizeMode="contain"
        />
      ) : null}

      {code ? (
        <View style={[styles.badge, { top: hauteur * ratioBadgeCode }]}>
          <Text style={[styles.texteBadge, { fontSize: Math.max(10, Math.min(15, taille * 0.09)) }]} numberOfLines={1}>
            {code}
          </Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    position: 'absolute',
    backgroundColor: 'rgba(255,255,255,0.92)',
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 2,
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 3,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
  texteBadge: {
    fontWeight: '800',
    color: '#1F2430',
    letterSpacing: 0.2,
  },
  logoMarque: {
    position: 'absolute',
    borderRadius: 999,
    backgroundColor: '#FFFFFF',
  },
});
