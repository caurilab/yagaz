/**
 * Logo Yagaz : marque (flamme dans un badge arrondi, dégradé orange) +
 * mot-symbole "Yagaz". La flamme est le même tracé que l'icône `flamme` du
 * pack, ici pleine et en dégradé - le fil rouge visuel de l'app.
 *
 * Variantes :
 *  - `complet` (défaut) : badge + mot, pour en-têtes et écrans d'auth.
 *  - `marque`           : badge seul (avatar, favicon, coin de carte).
 *  - `mot`              : mot-symbole seul.
 *
 * Ton :
 *  - `orange` (défaut) : badge dégradé orange, flamme blanche, mot sombre.
 *  - `blanc`           : sur fond orange/sombre - badge translucide, flamme
 *                        et mot blancs.
 */
import { StyleSheet, Text, View } from 'react-native';
import Svg, { Defs, LinearGradient, Path, Rect, Stop } from 'react-native-svg';
import { couleurs } from '../../../theme/couleurs';

type Variante = 'complet' | 'marque' | 'mot';
type Ton = 'orange' | 'blanc';

type Props = {
  variante?: Variante;
  /** Hauteur (px) du badge de marque ; le mot est dimensionné en proportion. */
  taille?: number;
  ton?: Ton;
};

const CHEMIN_FLAMME =
  'M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z';

function Marque({ taille, ton }: { taille: number; ton: Ton }) {
  const rayon = taille * 0.28;
  const flammeBlanche = '#FFFFFF';

  return (
    <Svg width={taille} height={taille} viewBox="0 0 48 48">
      <Defs>
        <LinearGradient id="yagazBadge" x1="0" y1="0" x2="0" y2="1">
          <Stop offset="0" stopColor={couleurs.degradeDebut} />
          <Stop offset="1" stopColor={couleurs.degradeFin} />
        </LinearGradient>
      </Defs>
      {ton === 'orange' ? (
        <Rect x={0} y={0} width={48} height={48} rx={rayon} fill="url(#yagazBadge)" />
      ) : (
        <Rect
          x={1.2}
          y={1.2}
          width={45.6}
          height={45.6}
          rx={rayon}
          fill="rgba(255,255,255,0.16)"
          stroke="rgba(255,255,255,0.9)"
          strokeWidth={2}
        />
      )}
      {/* Flamme centrée : tracé 24x24 (centre visuel ~12,11.5) mis à l'échelle
          1.4 puis recalé au centre du badge 48x48. */}
      <Path d={CHEMIN_FLAMME} fill={flammeBlanche} transform="translate(7.2 7.9) scale(1.4)" />
    </Svg>
  );
}

/** Logo Yagaz. Rendu marque + mot par défaut. */
export function LogoYagaz({ variante = 'complet', taille = 40, ton = 'orange' }: Props) {
  const couleurMot = ton === 'blanc' ? '#FFFFFF' : couleurs.texte;

  if (variante === 'marque') {
    return <Marque taille={taille} ton={ton} />;
  }

  const mot = (
    <Text style={[styles.mot, { fontSize: taille * 0.6, color: couleurMot }]} allowFontScaling={false}>
      Yagaz
    </Text>
  );

  if (variante === 'mot') {
    return mot;
  }

  return (
    <View style={styles.ligne}>
      <Marque taille={taille} ton={ton} />
      {mot}
    </View>
  );
}

const styles = StyleSheet.create({
  ligne: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  mot: {
    fontWeight: '800',
    letterSpacing: -0.5,
    includeFontPadding: false,
  },
});
