import { StyleSheet, Text, View } from 'react-native';

import { couleurs, espacements, rayons } from '../../theme/couleurs';

interface Props {
  texte: string;
  variante?: 'info' | 'alerte';
}

/** Bandeau "dernière valeur connue" / état de synchronisation (UX §1 et §7). */
export function BandeauSync({ texte, variante = 'info' }: Props) {
  return (
    <View style={[styles.conteneur, variante === 'alerte' && styles.conteneurAlerte]}>
      <Text style={[styles.texte, variante === 'alerte' && styles.texteAlerte]}>{texte}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    backgroundColor: couleurs.bordure,
    borderRadius: rayons.md,
    paddingVertical: espacements.sm,
    paddingHorizontal: espacements.md,
    marginBottom: espacements.md,
  },
  conteneurAlerte: {
    backgroundColor: couleurs.rougeClair,
  },
  texte: {
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.texteDoux,
    textAlign: 'center',
  },
  texteAlerte: {
    color: couleurs.danger,
  },
});
