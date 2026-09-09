import { useState } from 'react';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Icone } from './icones';
import { ModaleAstuces } from './ModaleAstuces';
import { ASTUCES } from '../data/astuces';
import { couleurs, espacements, rayons } from '../../theme/couleurs';

/** Index du jour dans `ASTUCES`, stable pour toute la journée en cours. */
function indexAstuceDuJour(): number {
  const jour = Math.floor(Date.now() / (24 * 60 * 60 * 1000));
  return jour % ASTUCES.length;
}

/**
 * Carte discrète de l'accueil qui met en avant l'astuce du jour et ouvre
 * `ModaleAstuces` au tap, positionnée sur cette même astuce.
 */
export function CarteAstuce() {
  const [indexDuJour] = useState(indexAstuceDuJour);
  const [visible, setVisible] = useState(false);
  const astuceDuJour = ASTUCES[indexDuJour] ?? ASTUCES[0];

  return (
    <>
      <Pressable
        style={styles.carte}
        onPress={() => setVisible(true)}
        accessibilityRole="button"
        accessibilityLabel={`Astuce du jour : ${astuceDuJour.titre}`}>
        <View style={styles.pastilleIcone}>
          <Icone nom={astuceDuJour.icone} taille={20} couleur={couleurs.rouge} />
        </View>
        <View style={styles.texteZone}>
          <Text style={styles.libelle}>Astuce</Text>
          <Text style={styles.titre} numberOfLines={1}>
            {astuceDuJour.titre}
          </Text>
        </View>
        <Icone nom="chevron" taille={18} couleur={couleurs.rouge} />
      </Pressable>

      <ModaleAstuces
        visible={visible}
        onClose={() => setVisible(false)}
        indexInitial={indexDuJour}
        onVoirToutes={() => router.push('/astuces')}
      />
    </>
  );
}

const styles = StyleSheet.create({
  carte: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    backgroundColor: couleurs.rougeClair,
    borderRadius: rayons.lg,
    paddingVertical: espacements.sm,
    paddingHorizontal: espacements.md,
    marginTop: espacements.lg,
  },
  pastilleIcone: {
    width: 36,
    height: 36,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.carte,
    alignItems: 'center',
    justifyContent: 'center',
  },
  texteZone: {
    flex: 1,
    minWidth: 0,
  },
  libelle: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.rouge,
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  titre: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.texte,
    marginTop: 1,
  },
});
