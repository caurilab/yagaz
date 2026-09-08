import { StyleSheet, Text, View } from 'react-native';

import { espacements, rayons } from '../../theme/couleurs';
import type { StatutCommande, StatutLivraison } from '../api/types';
import {
  couleursStatutCommande,
  couleursStatutLivraison,
  libellesStatutCommande,
  libellesStatutLivraison,
} from '../utils/statuts';

export function BadgeStatutCommande({ statut }: { statut: StatutCommande }) {
  return (
    <View style={[styles.badge, { backgroundColor: couleursStatutCommande[statut] }]}>
      <Text style={styles.texte}>{libellesStatutCommande[statut]}</Text>
    </View>
  );
}

export function BadgeStatutLivraison({ statut }: { statut: StatutLivraison }) {
  return (
    <View style={[styles.badge, { backgroundColor: couleursStatutLivraison[statut] }]}>
      <Text style={styles.texte}>{libellesStatutLivraison[statut]}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    paddingHorizontal: espacements.sm,
    paddingVertical: espacements.xs,
    borderRadius: rayons.rond,
    alignSelf: 'flex-start',
  },
  texte: {
    fontSize: 12,
    fontWeight: '700',
    color: '#FFFFFF',
  },
});
