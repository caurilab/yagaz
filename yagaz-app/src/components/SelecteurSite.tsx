import { useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { couleurs, espacements, rayons } from '../../theme/couleurs';
import type { Site } from '../api/types';

interface Props {
  sites: Site[];
  siteActif: Site | null;
  onChoisir: (uuid: string) => void;
}

/** Sélecteur multi-sites en tête d'accueil (UX §2 "Multi-sites"). */
export function SelecteurSite({ sites, siteActif, onChoisir }: Props) {
  const [ouvert, setOuvert] = useState(false);

  if (sites.length <= 1) {
    return <Text style={styles.libelleUnique}>{siteActif?.nom ?? 'Mon domicile'}</Text>;
  }

  return (
    <>
      <Pressable style={styles.pilule} onPress={() => setOuvert(true)} accessibilityRole="button">
        <Text style={styles.texteSite} numberOfLines={1}>
          {siteActif?.nom ?? 'Choisir un site'}
        </Text>
        <Text style={styles.chevron}>▾</Text>
      </Pressable>

      <Modal visible={ouvert} animationType="slide" transparent onRequestClose={() => setOuvert(false)}>
        <View style={styles.superposition}>
          <Pressable style={StyleSheet.absoluteFill} onPress={() => setOuvert(false)} />
          <View style={styles.feuille}>
            <SafeAreaView edges={['bottom']}>
              <Text style={styles.titreFeuille}>Mes sites</Text>
              <FlatList
                data={sites}
                keyExtractor={(s) => s.uuid}
                renderItem={({ item }) => (
                  <Pressable
                    style={[styles.ligne, item.uuid === siteActif?.uuid && styles.ligneActive]}
                    onPress={() => {
                      onChoisir(item.uuid);
                      setOuvert(false);
                    }}>
                    <View style={styles.ligneTexte}>
                      <Text style={styles.nomSite}>{item.nom}</Text>
                      {item.adresse ? <Text style={styles.adresseSite}>{item.adresse}</Text> : null}
                    </View>
                    {item.a_alerte_active ? <View style={styles.pointAlerte} /> : null}
                  </Pressable>
                )}
              />
            </SafeAreaView>
          </View>
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  libelleUnique: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.blanc,
  },
  pilule: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(255,255,255,0.2)',
    borderRadius: rayons.rond,
    paddingHorizontal: espacements.md,
    paddingVertical: espacements.sm,
    maxWidth: 240,
  },
  texteSite: {
    color: couleurs.blanc,
    fontWeight: '700',
    fontSize: 15,
  },
  chevron: {
    color: couleurs.blanc,
    fontSize: 14,
  },
  superposition: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  feuille: {
    backgroundColor: couleurs.carte,
    borderTopLeftRadius: rayons.xl,
    borderTopRightRadius: rayons.xl,
    paddingHorizontal: espacements.lg,
    paddingTop: espacements.lg,
    maxHeight: '70%',
  },
  titreFeuille: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.md,
  },
  ligne: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: espacements.md,
    borderBottomWidth: 1,
    borderBottomColor: couleurs.bordure,
  },
  ligneActive: {
    backgroundColor: couleurs.rougeClair,
    marginHorizontal: -espacements.md,
    paddingHorizontal: espacements.md,
    borderRadius: rayons.md,
  },
  ligneTexte: {
    flex: 1,
  },
  nomSite: {
    fontSize: 16,
    fontWeight: '600',
    color: couleurs.texte,
  },
  adresseSite: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  pointAlerte: {
    width: 10,
    height: 10,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rouge,
  },
});
