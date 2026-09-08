/**
 * Barres simples de la série de consommation (analyse §2) - léger,
 * `react-native-svg` uniquement, pas de lib de graphiques.
 */
import { StyleSheet, View, useWindowDimensions } from 'react-native';
import Svg, { Rect } from 'react-native-svg';

import { couleurs, espacements } from '../../../theme/couleurs';
import type { PointSerieConsommation } from '../../api/types';

const HAUTEUR = 96;
const ESPACE_BARRE = 3;

export function BarresConsommation({ serie }: { serie: PointSerieConsommation[] }) {
  const { width: largeurFenetre } = useWindowDimensions();
  const largeur = Math.max(200, largeurFenetre - espacements.lg * 2 - espacements.lg * 2);

  if (serie.length === 0) {
    return null;
  }

  const max = Math.max(...serie.map((point) => point.valeur), 0.01);
  const largeurBarre = Math.max(2, largeur / serie.length - ESPACE_BARRE);

  return (
    <View style={styles.conteneur}>
      <Svg width={largeur} height={HAUTEUR}>
        {serie.map((point, index) => {
          const hauteurBarre = Math.max(2, (point.valeur / max) * (HAUTEUR - 4));
          const x = index * (largeurBarre + ESPACE_BARRE);
          return (
            <Rect
              key={point.date}
              x={x}
              y={HAUTEUR - hauteurBarre}
              width={largeurBarre}
              height={hauteurBarre}
              rx={2}
              fill={couleurs.rouge}
            />
          );
        })}
      </Svg>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    height: HAUTEUR,
  },
});
