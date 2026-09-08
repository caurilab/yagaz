/**
 * CTA discret "Connectez votre ..." (gating multi-équipements, ADR 0012) :
 * affiché à la place d'une donnée (niveau de gaz, température) tant que le
 * site n'a pas la capacité correspondante (`a_balance`/`a_temperature`
 * fausse - `SiteResource`). Renvoie vers Réglages > Matériels pour ajouter
 * l'équipement manquant.
 */
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text } from 'react-native';

import { couleurs, espacements, rayons } from '../../theme/couleurs';
import { Icone } from './icones';

export function EncartConnecterMateriel({ texte }: { texte: string }) {
  return (
    <Pressable
      style={({ pressed }) => [styles.ligne, pressed && styles.lignePressee]}
      onPress={() => router.push('/materiels')}
      accessibilityRole="button"
      accessibilityLabel={`${texte} - voir les matériels`}>
      <Text style={styles.texte}>{texte}</Text>
      <Icone nom="chevron" taille={14} couleur={couleurs.texteDoux} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  ligne: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    alignSelf: 'center',
    marginTop: espacements.sm,
    paddingVertical: espacements.xs,
    paddingHorizontal: espacements.md,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.fond,
  },
  lignePressee: {
    opacity: 0.7,
  },
  texte: {
    fontSize: 12,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
});
