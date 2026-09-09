/**
 * Indicateur de chargement de marque : flamme vacillante (`FlammeAnimee`)
 * posée sur le badge du logo Yagaz, centrée, avec un libellé optionnel.
 * Remplace les `ActivityIndicator` plein écran des chargements initiaux.
 */
import { StyleSheet, Text, View } from 'react-native';

import { couleurs, espacements } from '../../theme/couleurs';
import { FlammeAnimee } from './FlammeAnimee';
import { LogoYagaz } from './icones';

type Props = {
  /** Libellé sous l'indicateur, ex. "Chargement...". */
  texte?: string;
  /** Taille du badge de marque, en px. Défaut 64. */
  taille?: number;
};

export function ChargementYagaz({ texte, taille = 64 }: Props) {
  return (
    <View style={styles.conteneur}>
      <View style={[styles.badge, { width: taille, height: taille, borderRadius: taille * 0.28 }]}>
        <LogoYagaz variante="marque" taille={taille} ton="orange" />
        <View style={styles.flamme}>
          <FlammeAnimee taille={taille * 0.42} couleur={couleurs.blanc} />
        </View>
      </View>
      {texte ? <Text style={styles.texte}>{texte}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.md,
  },
  badge: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  flamme: {
    position: 'absolute',
  },
  texte: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
});
