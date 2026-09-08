import { router } from 'expo-router';
import { Pressable, Text } from 'react-native';

import { couleurs } from '../../theme/couleurs';

/** Bouton de retour partagé entre les navigations foyer/dépôt/livreur. */
export function BoutonRetour() {
  return (
    <Pressable onPress={() => router.back()} hitSlop={16} style={{ paddingHorizontal: 12, paddingVertical: 8 }}>
      <Text style={{ fontSize: 17, color: couleurs.rouge, fontWeight: '600' }}>Retour</Text>
    </Pressable>
  );
}
