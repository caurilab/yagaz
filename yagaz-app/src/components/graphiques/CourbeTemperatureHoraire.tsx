/**
 * Courbe horaire de température moyenne (drill-down température, doc 13 §3)
 * - `react-native-svg` uniquement, aire + ligne, léger et élégant (orange).
 */
import { useId } from 'react';
import { StyleSheet, View, useWindowDimensions } from 'react-native';
import Svg, { Circle, Defs, LinearGradient, Path, Stop } from 'react-native-svg';

import { couleurs, espacements } from '../../../theme/couleurs';
import type { PointTemperatureHoraire } from '../../api/types';

const HAUTEUR = 120;
const MARGE_VERTICALE = 10;

export function CourbeTemperatureHoraire({ points }: { points: PointTemperatureHoraire[] }) {
  const idBase = useId();
  const idAire = `courbe-temperature-aire-${idBase}`;
  const { width: largeurFenetre } = useWindowDimensions();
  const largeur = Math.max(240, largeurFenetre - espacements.lg * 2 - espacements.lg * 2);

  // On ne garde que les heures ayant une moyenne réelle : `temp_moyenne_c` est
  // `null` pour un créneau sans relevé, et une coordonnée NaN transmise à
  // react-native-svg crashe l'app en natif (iOS). Filtrer ici garantit un path
  // toujours valide.
  const valides = points.filter(
    (p): p is { heure: number; temp_moyenne_c: number } =>
      typeof p.temp_moyenne_c === 'number' && Number.isFinite(p.temp_moyenne_c)
  );

  if (valides.length < 2) return null;

  const valeurs = valides.map((p) => p.temp_moyenne_c);
  const min = Math.min(...valeurs);
  const max = Math.max(...valeurs, min + 1);
  const pas = largeur / (valides.length - 1 || 1);

  const yDe = (v: number) =>
    HAUTEUR - MARGE_VERTICALE - ((v - min) / (max - min)) * (HAUTEUR - MARGE_VERTICALE * 2);

  const ligne = valides.map((p, i) => `${i === 0 ? 'M' : 'L'} ${i * pas} ${yDe(p.temp_moyenne_c)}`).join(' ');
  const aire = `${ligne} L ${(valides.length - 1) * pas} ${HAUTEUR} L 0 ${HAUTEUR} Z`;

  return (
    <View style={styles.conteneur}>
      <Svg width={largeur} height={HAUTEUR}>
        <Defs>
          <LinearGradient id={idAire} x1="0" y1="0" x2="0" y2="1">
            <Stop offset="0" stopColor={couleurs.rouge} stopOpacity={0.28} />
            <Stop offset="1" stopColor={couleurs.rouge} stopOpacity={0} />
          </LinearGradient>
        </Defs>
        <Path d={aire} fill={`url(#${idAire})`} />
        <Path d={ligne} fill="none" stroke={couleurs.rouge} strokeWidth={2.5} strokeLinejoin="round" strokeLinecap="round" />
        {valides.map((p, i) =>
          i % 4 === 0 ? <Circle key={p.heure} cx={i * pas} cy={yDe(p.temp_moyenne_c)} r={2.5} fill={couleurs.rouge} /> : null
        )}
      </Svg>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    height: HAUTEUR,
  },
});
