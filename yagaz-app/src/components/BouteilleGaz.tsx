/**
 * Silhouette vectorielle de bouteille de gaz domestique (corps arrondi,
 * épaulement, col/valve) - `react-native-svg`, léger et réutilisable.
 * Le corps se remplit proportionnellement à `niveauPct` (dégradé couleur de
 * marque), le reste en gris très clair. `code` (ex. "B12") s'affiche en
 * badge net, superposé sur la bouteille.
 */
import { useId } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import Svg, { ClipPath, Defs, LinearGradient, Path, Rect, Stop } from 'react-native-svg';

import { couleurMarque } from '../utils/marque';

const LARGEUR_VUE = 100;
const HAUTEUR_VUE = 170;
const RATIO = LARGEUR_VUE / HAUTEUR_VUE;

const COULEUR_VIDE = '#EEF1F5';
const COULEUR_TRAIT_VIDE = '#D7DCE3';

/** Silhouette : valve arrondie, col effilé, épaulement, corps cylindrique arrondi. */
const SILHOUETTE = `
  M 40 4
  L 60 4
  Q 66 4 66 10
  L 66 18
  Q 66 24 72 28
  Q 90 40 90 58
  L 90 146
  Q 90 164 72 164
  L 28 164
  Q 10 164 10 146
  L 10 58
  Q 10 40 28 28
  Q 34 24 34 18
  L 34 10
  Q 34 4 40 4
  Z
`;

interface Props {
  /** Couleur de marque (hex). Repli gris neutre si absente ou invalide. */
  couleur?: string | null;
  /** Code du format (ex. "B12"), affiché en badge sur la bouteille. */
  code?: string;
  /** Niveau de remplissage 0-100. Sans valeur : silhouette vide (contour seul). */
  niveauPct?: number;
  /** Hauteur en px (largeur déduite du ratio de la silhouette). */
  taille?: number;
  /** Petit reflet lumineux sur le corps (défaut : oui). */
  reflet?: boolean;
}

export function BouteilleGaz({ couleur, code, niveauPct, taille = 140, reflet = true }: Props) {
  const idBase = useId();
  const idClip = `bouteille-clip-${idBase}`;
  const idGradient = `bouteille-gradient-${idBase}`;

  const couleurPleine = couleurMarque(couleur);
  const pct = niveauPct != null ? Math.max(0, Math.min(100, niveauPct)) : null;
  const hauteurRemplie = pct != null ? (pct / 100) * HAUTEUR_VUE : 0;
  const yRemplissage = HAUTEUR_VUE - hauteurRemplie;

  const hauteur = taille;
  const largeur = taille * RATIO;

  return (
    <View style={{ width: largeur, height: hauteur, alignItems: 'center', justifyContent: 'center' }}>
      <Svg width={largeur} height={hauteur} viewBox={`0 0 ${LARGEUR_VUE} ${HAUTEUR_VUE}`}>
        <Defs>
          <ClipPath id={idClip}>
            <Path d={SILHOUETTE} />
          </ClipPath>
          <LinearGradient id={idGradient} x1="0" y1="1" x2="0" y2="0">
            <Stop offset="0" stopColor={couleurPleine} stopOpacity={1} />
            <Stop offset="1" stopColor={couleurPleine} stopOpacity={0.78} />
          </LinearGradient>
        </Defs>

        {/* Fond de la silhouette (partie "vide") */}
        <Path d={SILHOUETTE} fill={COULEUR_VIDE} />

        {/* Remplissage proportionnel au niveau, découpé par la silhouette */}
        {pct != null && pct > 0 ? (
          <>
            <Rect
              x={0}
              y={yRemplissage}
              width={LARGEUR_VUE}
              height={hauteurRemplie}
              fill={`url(#${idGradient})`}
              clipPath={`url(#${idClip})`}
            />
            {pct < 100 ? (
              <Rect
                x={0}
                y={yRemplissage}
                width={LARGEUR_VUE}
                height={2}
                fill="#FFFFFF"
                opacity={0.35}
                clipPath={`url(#${idClip})`}
              />
            ) : null}
          </>
        ) : null}

        {/* Reflet léger */}
        {reflet ? (
          <Rect
            x={20}
            y={64}
            width={7}
            height={72}
            rx={3.5}
            fill="#FFFFFF"
            opacity={0.28}
            clipPath={`url(#${idClip})`}
          />
        ) : null}

        {/* Contour net */}
        <Path
          d={SILHOUETTE}
          fill="none"
          stroke={pct != null && pct > 0 ? couleurPleine : COULEUR_TRAIT_VIDE}
          strokeWidth={2.5}
          strokeOpacity={pct != null && pct > 0 ? 0.9 : 1}
        />
      </Svg>

      {code ? (
        <View style={[styles.badge, { top: hauteur * 0.46 }]}>
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
});
