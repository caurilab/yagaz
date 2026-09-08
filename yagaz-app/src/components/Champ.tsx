import { StyleSheet, Text, TextInput, View, type KeyboardTypeOptions } from 'react-native';

import { couleurs, espacements, rayons } from '../../theme/couleurs';

interface Props {
  etiquette: string;
  valeur: string;
  onChangeText: (texte: string) => void;
  masque?: boolean;
  motDePasse?: boolean;
  clavier?: KeyboardTypeOptions;
  aide?: string;
  erreur?: string;
  placeholder?: string;
}

export function Champ({
  etiquette,
  valeur,
  onChangeText,
  motDePasse = false,
  clavier = 'default',
  aide,
  erreur,
  placeholder,
}: Props) {
  return (
    <View style={styles.conteneur}>
      <Text style={styles.etiquette}>{etiquette}</Text>
      <TextInput
        value={valeur}
        onChangeText={onChangeText}
        secureTextEntry={motDePasse}
        keyboardType={clavier}
        placeholder={placeholder}
        placeholderTextColor={couleurs.texteDoux}
        autoCapitalize="none"
        style={[styles.champ, erreur && styles.champErreur]}
      />
      {erreur ? <Text style={styles.texteErreur}>{erreur}</Text> : aide ? <Text style={styles.aide}>{aide}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    marginBottom: espacements.md,
  },
  etiquette: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texte,
    marginBottom: espacements.xs,
  },
  champ: {
    minHeight: 56,
    borderRadius: rayons.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    backgroundColor: couleurs.carte,
    paddingHorizontal: espacements.md,
    fontSize: 17,
    color: couleurs.texte,
  },
  champErreur: {
    borderColor: couleurs.rouge,
  },
  aide: {
    marginTop: espacements.xs,
    fontSize: 13,
    color: couleurs.texteDoux,
  },
  texteErreur: {
    marginTop: espacements.xs,
    fontSize: 13,
    color: couleurs.rouge,
  },
});
