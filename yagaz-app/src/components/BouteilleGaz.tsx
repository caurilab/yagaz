/**
 * Silhouette vectorielle de bouteille de gaz domestique - `react-native-svg`,
 * léger et réutilisable. Deux familles de tracé selon le format (voir
 * `code`) :
 * - « haute » (B24/B12) : corps élancé, épaulement, col avec
 *   collerette/poignée de protection (arceau ajouré), valve.
 * - « trapue/ronde » (B6/B3) : corps court et arrondi, épaulement bombé,
 *   petit col, valve - sans grand arceau.
 *
 * À l'intérieur de chaque famille, un `SPEC_FORMAT` par code (B24/B12/B6/B3,
 * cf. photos `_gaz/`) fait varier hauteur ET largeur relatives de façon nette
 * (B24 la plus grande/haute, B12 haute mais plus petite, B6 trapue moyenne,
 * B3 trapue petite) - proportions fidèles quel que soit le `taille` demandé
 * par l'appelant (B24 = référence 1.0, les autres s'échelonnent en dessous).
 *
 * Le corps est peint dans la couleur de marque (`couleur`), en dégradé
 * (bord clair = lumière, bord foncé = ombre) avec un reflet vertical pour un
 * rendu 3D, et légèrement translucide (`fillOpacity`) pour bien laisser
 * deviner le niveau de liquide à travers la paroi. Le niveau (`niveauPct`)
 * assombrit la partie vide en haut du corps et teinte légèrement la partie
 * pleine en bas (contraste plein/vide), sans jamais faire disparaître la
 * couleur de marque (reste joli quand le niveau est inconnu).
 *
 * Un badge circulaire blanc porte le monogramme de la marque (initiale de
 * `marqueNom`) dans un coin - jamais le nom en toutes lettres sur le corps.
 * `useId` évite les collisions d'identifiants SVG entre instances.
 */
import { useId } from 'react';
import { Image, StyleSheet, Text, View, type ImageSourcePropType } from 'react-native';
import Svg, { Circle, ClipPath, Defs, G, LinearGradient, Path, Rect, Stop, Text as TexteSvg } from 'react-native-svg';

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

type Famille = 'haute' | 'trapue';

interface SpecFormat {
  famille: Famille;
  /** Échelle de hauteur relative entre formats - B24 sert de référence (1.0). */
  echelleHauteur: number;
  /** Élargissement/rétrécissement horizontal du tracé (1 = tracé de base de la famille). */
  facteurLargeur: number;
}

/**
 * Un format par code (contrat §Formats) : la taille prime sur tout le reste.
 * B24 (plus grande bombonne) sert de référence hauteur=1 ; B12 plus petite
 * mais toujours élancée ; B6 trapue moyenne ; B3 trapue petite - d'après les
 * photos réelles (`_gaz/`).
 */
const SPECS_FORMAT: Record<string, SpecFormat> = {
  // Formats réels CI (voir _gaz/) : B35 la plus grande -> B6 la plus petite.
  B35: { famille: 'haute', echelleHauteur: 1.0, facteurLargeur: 1.15 },
  B32: { famille: 'haute', echelleHauteur: 0.92, facteurLargeur: 1.1 },
  B12: { famille: 'haute', echelleHauteur: 0.72, facteurLargeur: 0.95 },
  B6: { famille: 'trapue', echelleHauteur: 0.5, facteurLargeur: 1.05 },
};
/** Repli si le code est absent/inconnu : comme avant, silhouette haute non réduite. */
const SPEC_PAR_DEFAUT: SpecFormat = { famille: 'haute', echelleHauteur: 1, facteurLargeur: 1 };

function specDuFormat(code?: string | null): SpecFormat {
  const c = code?.trim().toUpperCase();
  return (c && SPECS_FORMAT[c]) || SPEC_PAR_DEFAUT;
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

/**
 * Silhouette "trapue/ronde" (B6) : corps court et bombé (tonneau), col court.
 * Trait le plus reconnaissable des B6 réelles (cf. `_gaz/`) : une large
 * collerette évasée (jupe de protection) au-dessus de l'épaule, dessinée à part
 * (COLLERETTE_TRAPUE), et un pied/embase en bas (dessiné dans le rendu).
 */
const SILHOUETTE_TRAPUE = `
  M 42 26
  L 58 26
  Q 82 32 85 58
  L 85 100
  Q 85 126 50 126
  Q 15 126 15 100
  L 15 58
  Q 18 32 42 26
  Z
`;
/** Collerette évasée (jupe de protection) de la B6 : large en haut, resserrée sur l'épaule. */
const COLLERETTE_TRAPUE = `
  M 30 12
  L 70 12
  L 60 30
  L 40 30
  Z
`;
const VALVE_TRAPUE = { x: 45, y: 2, w: 10, h: 16, rx: 4, cxKnob: 50, cyKnob: 6, rKnob: 4 };
const VUE_TRAPUE = { largeur: 100, hauteur: 138 };
const BADGE_MARQUE_TRAPUE = { cx: 73, cy: 60, r: 9.5 };
const BADGE_CODE_TOP_RATIO_TRAPUE = 0.6;

interface Props {
  /** Couleur de marque (hex). Repli gris neutre si absente ou invalide. */
  couleur?: string | null;
  /** Code du format (ex. "B12"), affiché en badge sur la bouteille et utilisé pour choisir la silhouette/taille. */
  code?: string | null;
  /** Nom de la marque (ex. "Oryx"), dont l'initiale forme le monogramme du coin. */
  marqueNom?: string | null;
  /** Niveau de remplissage 0-100. Sans valeur : corps plein et coloré, sans assombrissement. */
  niveauPct?: number;
  /** Hauteur de référence en px (B24 l'utilise pleinement ; les autres formats s'échelonnent en dessous). */
  taille?: number;
  /** Petit reflet lumineux sur le corps (défaut : oui). */
  reflet?: boolean;
}

export function BouteilleGaz({ couleur, code, marqueNom, niveauPct, taille = 140, reflet = true }: Props) {
  const idBase = useId();
  const idClip = `bouteille-clip-${idBase}`;
  const idGradient = `bouteille-gradient-${idBase}`;

  const spec = specDuFormat(code);
  const trapue = spec.famille === 'trapue';
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
  const yFinVide = yVide + hauteurVide;

  // Hauteur/largeur finales : `taille` est une hauteur de référence (B24 = 1.0),
  // les autres formats s'échelonnent en dessous pour rester reconnaissables
  // côte à côte (ex. sélecteurs de format) quel que soit le `taille` demandé.
  const hauteur = taille * spec.echelleHauteur;
  const largeurVue = vue.largeur * spec.facteurLargeur;
  const largeur = hauteur * (largeurVue / vue.hauteur);

  const initiale = initialeMarque(marqueNom);
  const logo = marqueNom ? LOGOS_MARQUES[marqueNom] : undefined;

  return (
    <View style={{ width: largeur, height: hauteur, alignItems: 'center', justifyContent: 'center' }}>
      <Svg width={largeur} height={hauteur} viewBox={`0 0 ${largeurVue} ${vue.hauteur}`}>
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

        {/* Tout le tracé est élargi/rétréci horizontalement selon le format (`facteurLargeur`),
            sans retoucher les coordonnées du tracé de base. */}
        <G transform={`scale(${spec.facteurLargeur} 1)`}>
          {/* Corps peint dans la couleur de marque, en dégradé (volume/lumière), légèrement
              translucide pour bien laisser deviner le niveau de liquide à l'intérieur. */}
          <Path d={silhouette} fill={`url(#${idGradient})`} fillOpacity={0.82} />

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

          {/* Niveau de gaz : assombrit nettement la partie vide en haut, teinte légèrement
              la partie pleine en bas - bon contraste plein/vide à travers le corps translucide. */}
          {pct != null && hauteurVide > 0 ? (
            <>
              <Rect
                x={0}
                y={yVide}
                width={vue.largeur}
                height={hauteurVide}
                fill="#000000"
                opacity={0.32}
                clipPath={`url(#${idClip})`}
              />
              <Rect x={0} y={yFinVide} width={vue.largeur} height={1.4} fill="#FFFFFF" opacity={0.45} clipPath={`url(#${idClip})`} />
            </>
          ) : null}
          {pct != null && pct > 0 ? (
            <Rect
              x={0}
              y={yFinVide}
              width={vue.largeur}
              height={Math.max(0, vue.hauteur - yFinVide)}
              fill={couleurOmbre}
              opacity={0.14}
              clipPath={`url(#${idClip})`}
            />
          ) : null}

          {/* Contour net */}
          <Path d={silhouette} fill="none" stroke={couleurOmbre} strokeWidth={1.5} strokeOpacity={0.5} />

          {/* Embase/pied de la B6 (bande plus foncée au bas du corps, cf. photos) */}
          {trapue ? (
            <Rect
              x={0}
              y={vue.hauteur - 12}
              width={vue.largeur}
              height={12}
              fill={couleurOmbre}
              opacity={0.22}
              clipPath={`url(#${idClip})`}
            />
          ) : null}

          {/* Collerette de protection : jupe évasée (B6) ou arceau ajouré (formats hauts) */}
          {trapue ? (
            <Path
              d={COLLERETTE_TRAPUE}
              fill={couleurCollerette}
              stroke={assombrir(couleurPleine, 0.4)}
              strokeWidth={0.8}
              strokeLinejoin="round"
            />
          ) : (
            <Path
              d={COLLERETTE_HAUTE}
              fill={couleurCollerette}
              fillRule="evenodd"
              stroke={assombrir(couleurPleine, 0.4)}
              strokeWidth={0.8}
            />
          )}

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
        </G>
      </Svg>

      {/* Vrai logo de marque (dès que `LOGOS_MARQUES` est renseigné) - même coin que le monogramme */}
      {logo ? (
        <Image
          source={logo}
          style={[
            styles.logoMarque,
            {
              width: (badgeMarque.r * 2 * largeur) / largeurVue,
              height: (badgeMarque.r * 2 * largeur) / largeurVue,
              left: ((badgeMarque.cx * spec.facteurLargeur - badgeMarque.r) * largeur) / largeurVue,
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
