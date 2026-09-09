/**
 * Flamme animée - signale une cuisson en cours (ADR 0011, doc 13 §3/§4).
 * Vacillement continu (scale + opacité + très léger décalage vertical) via
 * `Animated.loop`, pilotée uniquement par l'API `Animated` intégrée de
 * react-native (pas de reanimated). Boucle unique par instance, arrêtée au
 * démontage.
 */
import { useEffect, useRef } from 'react';
import { Animated, Easing } from 'react-native';

import { couleurs } from '../../theme/couleurs';
import { Icone } from './icones';

export function FlammeAnimee({
  taille = 16,
  couleur = couleurs.danger,
}: {
  taille?: number;
  couleur?: string;
}) {
  const vacillement = useRef(new Animated.Value(0)).current;
  const animation = useRef<Animated.CompositeAnimation | null>(null);

  useEffect(() => {
    animation.current = Animated.loop(
      Animated.sequence([
        Animated.timing(vacillement, {
          toValue: 1,
          duration: 800,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.timing(vacillement, {
          toValue: 0,
          duration: 800,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
      ])
    );
    animation.current.start();

    return () => {
      animation.current?.stop();
    };
  }, [vacillement]);

  const echelle = vacillement.interpolate({ inputRange: [0, 1], outputRange: [1, 1.12] });
  const opacite = vacillement.interpolate({ inputRange: [0, 1], outputRange: [0.85, 1] });
  const translationY = vacillement.interpolate({ inputRange: [0, 1], outputRange: [0, -1.5] });

  return (
    <Animated.View
      style={{
        opacity: opacite,
        transform: [{ scale: echelle }, { translateY: translationY }],
      }}>
      <Icone nom="flamme" taille={taille} couleur={couleur} />
    </Animated.View>
  );
}
