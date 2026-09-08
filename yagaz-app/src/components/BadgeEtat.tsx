import { StyleSheet, Text, View } from 'react-native';

import { espacements, rayons } from '../../theme/couleurs';
import type { EtatNiveau } from '../api/types';
import { couleursEtat, libellesEtat } from '../utils/niveau';

export function BadgeEtat({ etat, compact = false }: { etat: EtatNiveau; compact?: boolean }) {
  return (
    <View style={[styles.badge, compact && styles.badgeCompact, { backgroundColor: couleursEtat[etat] }]}>
      <Text style={[styles.texte, compact && styles.texteCompact]}>{libellesEtat[etat]}</Text>
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
  badgeCompact: {
    paddingHorizontal: espacements.sm,
    paddingVertical: 2,
  },
  texte: {
    fontSize: 12,
    fontWeight: '700',
    color: '#FFFFFF',
  },
  texteCompact: {
    fontSize: 11,
  },
});
