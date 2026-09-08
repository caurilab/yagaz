import { ActivityIndicator, Pressable, StyleSheet, Text, type StyleProp, type ViewStyle } from 'react-native';

import { couleurs, espacements, rayons } from '../../theme/couleurs';

interface Props {
  titre: string;
  onPress: () => void;
  variante?: 'plein' | 'contour' | 'discret';
  enCours?: boolean;
  desactive?: boolean;
  style?: StyleProp<ViewStyle>;
}

/** Bouton à cible tactile généreuse (usage en cuisine, UX §1/§8). */
export function Bouton({ titre, onPress, variante = 'plein', enCours = false, desactive = false, style }: Props) {
  const inactif = desactive || enCours;
  return (
    <Pressable
      accessibilityRole="button"
      onPress={inactif ? undefined : onPress}
      style={({ pressed }) => [
        styles.base,
        variante === 'plein' && styles.plein,
        variante === 'contour' && styles.contour,
        variante === 'discret' && styles.discret,
        inactif && styles.desactive,
        pressed && !inactif && styles.presse,
        style,
      ]}>
      {enCours ? (
        <ActivityIndicator color={variante === 'plein' ? couleurs.blanc : couleurs.rouge} />
      ) : (
        <Text
          style={[
            styles.texte,
            variante === 'plein' && styles.textePlein,
            variante === 'contour' && styles.texteContour,
            variante === 'discret' && styles.texteDiscret,
          ]}>
          {titre}
        </Text>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: 56,
    borderRadius: rayons.lg,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: espacements.lg,
  },
  plein: {
    backgroundColor: couleurs.rouge,
  },
  contour: {
    backgroundColor: couleurs.blanc,
    borderWidth: 2,
    borderColor: couleurs.rouge,
  },
  discret: {
    backgroundColor: 'transparent',
  },
  presse: {
    opacity: 0.85,
  },
  desactive: {
    opacity: 0.5,
  },
  texte: {
    fontSize: 17,
    fontWeight: '700',
  },
  textePlein: {
    color: couleurs.blanc,
  },
  texteContour: {
    color: couleurs.rouge,
  },
  texteDiscret: {
    color: couleurs.texteDoux,
  },
});
