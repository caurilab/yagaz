/**
 * Barre de progression réutilisable : piste arrondie avec remplissage en
 * dégradé, animée en largeur au montage et à chaque changement de `pct` via
 * l'API `Animated` intégrée de react-native (pas de reanimated).
 */
import { useEffect, useRef } from 'react';
import { Animated, Easing, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';

import { couleurs, rayons } from '../../theme/couleurs';

type Props = {
  /** Pourcentage de remplissage, borné à [0, 100]. */
  pct: number;
  /** Hauteur de la piste, en px. Défaut 8. */
  hauteur?: number;
  /** Couleur de départ du dégradé de remplissage. Défaut `degradeDebut`. */
  couleurDebut?: string;
  /** Couleur de fin du dégradé de remplissage. Défaut `rouge`. */
  couleurFin?: string;
  /** Couleur de fond de la piste. Défaut `couleurs.fond`. */
  couleurFond?: string;
  /** Anime le remplissage (montage + changements de `pct`). Défaut true. */
  anime?: boolean;
};

export function BarreProgression({
  pct,
  hauteur = 8,
  couleurDebut = couleurs.degradeDebut,
  couleurFin = couleurs.rouge,
  couleurFond = couleurs.fond,
  anime = true,
}: Props) {
  const pctBorne = Math.max(0, Math.min(100, pct));
  const largeur = useRef(new Animated.Value(anime ? 0 : pctBorne)).current;

  useEffect(() => {
    if (!anime) {
      largeur.setValue(pctBorne);
      return;
    }
    const animation = Animated.timing(largeur, {
      toValue: pctBorne,
      duration: 600,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: false,
    });
    animation.start();
    return () => animation.stop();
  }, [anime, pctBorne, largeur]);

  return (
    <View style={[styles.piste, { height: hauteur, backgroundColor: couleurFond, borderRadius: hauteur / 2 }]}>
      <Animated.View
        style={{
          height: '100%',
          width: largeur.interpolate({ inputRange: [0, 100], outputRange: ['0%', '100%'] }),
        }}>
        <LinearGradient
          colors={[couleurDebut, couleurFin]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={StyleSheet.absoluteFill}
        />
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  piste: {
    overflow: 'hidden',
    borderRadius: rayons.rond,
  },
});
