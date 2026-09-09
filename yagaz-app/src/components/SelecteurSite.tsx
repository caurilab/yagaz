import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from 'expo-router';
import { Animated, Easing, FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Icone } from './icones';
import { couleurs, espacements, rayons } from '../../theme/couleurs';
import type { Site } from '../api/types';

interface Props {
  sites: Site[];
  siteActif: Site | null;
  onChoisir: (uuid: string) => void;
}

const TRANSLATION_FERMEE = 560;

/**
 * Sélecteur multi-lieux (UX §2). La feuille monte du bas et le fond apparaît
 * en fondu (opacité) - pas de balayage de tout l'écran : l'overlay est animé
 * séparément du panneau, pour un rendu doux et moderne.
 */
export function SelecteurSite({ sites, siteActif, onChoisir }: Props) {
  const [visible, setVisible] = useState(false);
  const opacite = useRef(new Animated.Value(0)).current;
  const translation = useRef(new Animated.Value(TRANSLATION_FERMEE)).current;

  const ouvrir = useCallback(() => {
    setVisible(true);
  }, []);

  const fermer = useCallback(
    (apres?: () => void) => {
      Animated.parallel([
        Animated.timing(opacite, { toValue: 0, duration: 180, useNativeDriver: true }),
        Animated.timing(translation, {
          toValue: TRANSLATION_FERMEE,
          duration: 220,
          easing: Easing.in(Easing.cubic),
          useNativeDriver: true,
        }),
      ]).start(() => {
        setVisible(false);
        apres?.();
      });
    },
    [opacite, translation]
  );

  // Anime l'entrée dès que la modale est montée.
  useEffect(() => {
    if (!visible) return;
    opacite.setValue(0);
    translation.setValue(TRANSLATION_FERMEE);
    Animated.parallel([
      Animated.timing(opacite, { toValue: 1, duration: 220, useNativeDriver: true }),
      Animated.spring(translation, { toValue: 0, friction: 9, tension: 70, useNativeDriver: true }),
    ]).start();
  }, [visible, opacite, translation]);

  return (
    <>
      <Pressable style={styles.pilule} onPress={ouvrir} accessibilityRole="button">
        <Icone nom="localisation" taille={15} couleur={couleurs.blanc} />
        <Text style={styles.texteSite} numberOfLines={1}>
          {siteActif?.nom ?? (sites.length === 0 ? 'Ajouter un lieu' : 'Choisir un lieu')}
        </Text>
        <Text style={styles.chevron}>▾</Text>
      </Pressable>

      <Modal visible={visible} animationType="none" transparent onRequestClose={() => fermer()}>
        <View style={styles.conteneur}>
          <Animated.View style={[styles.overlay, { opacity: opacite }]}>
            <Pressable style={StyleSheet.absoluteFill} onPress={() => fermer()} />
          </Animated.View>

          <Animated.View style={[styles.feuille, { transform: [{ translateY: translation }] }]}>
            <SafeAreaView edges={['bottom']}>
              <View style={styles.poignee} />
              <Text style={styles.titreFeuille}>Mes lieux</Text>
              <FlatList
                data={sites}
                keyExtractor={(s) => s.uuid}
                showsVerticalScrollIndicator={false}
                renderItem={({ item }) => {
                  const actif = item.uuid === siteActif?.uuid;
                  return (
                    <Pressable
                      style={[styles.ligne, actif && styles.ligneActive]}
                      onPress={() => fermer(() => onChoisir(item.uuid))}>
                      <View style={[styles.iconeLieu, actif && styles.iconeLieuActive]}>
                        <Icone nom="localisation" taille={18} couleur={actif ? couleurs.blanc : couleurs.rouge} />
                      </View>
                      <View style={styles.ligneTexte}>
                        <Text style={styles.nomSite} numberOfLines={1}>
                          {item.nom}
                        </Text>
                        {item.adresse ? (
                          <Text style={styles.adresseSite} numberOfLines={1}>
                            {item.adresse}
                          </Text>
                        ) : null}
                      </View>
                      {item.a_alerte_active ? <View style={styles.pointAlerte} /> : null}
                      {actif ? <Icone nom="check" taille={20} couleur={couleurs.rouge} /> : null}
                    </Pressable>
                  );
                }}
                ListFooterComponent={
                  <Pressable
                    style={styles.ligneAjout}
                    onPress={() => fermer(() => router.push('/(app)/nouveau-lieu'))}
                    accessibilityRole="button">
                    <View style={styles.iconeAjout}>
                      <Icone nom="plus" taille={18} couleur={couleurs.rouge} />
                    </View>
                    <Text style={styles.texteAjout}>Nouveau lieu</Text>
                  </Pressable>
                }
              />
            </SafeAreaView>
          </Animated.View>
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  pilule: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(255,255,255,0.2)',
    borderRadius: rayons.rond,
    paddingHorizontal: espacements.md,
    paddingVertical: espacements.sm,
    maxWidth: 260,
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
  conteneur: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  overlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(20,25,40,0.45)',
  },
  feuille: {
    backgroundColor: couleurs.carte,
    borderTopLeftRadius: rayons.xl,
    borderTopRightRadius: rayons.xl,
    paddingHorizontal: espacements.lg,
    paddingTop: espacements.sm,
    maxHeight: '76%',
    shadowColor: '#000',
    shadowOpacity: 0.18,
    shadowRadius: 24,
    shadowOffset: { width: 0, height: -8 },
    elevation: 12,
  },
  poignee: {
    alignSelf: 'center',
    width: 42,
    height: 5,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.bordure,
    marginBottom: espacements.md,
  },
  titreFeuille: {
    fontSize: 20,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.md,
  },
  ligne: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
    paddingVertical: espacements.sm,
    paddingHorizontal: espacements.sm,
    borderRadius: rayons.lg,
    marginBottom: espacements.xs,
  },
  ligneActive: {
    backgroundColor: couleurs.rougeClair,
  },
  iconeLieu: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconeLieuActive: {
    backgroundColor: couleurs.rouge,
  },
  ligneTexte: {
    flex: 1,
  },
  nomSite: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
  },
  adresseSite: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  pointAlerte: {
    width: 9,
    height: 9,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.danger,
  },
  ligneAjout: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
    paddingVertical: espacements.sm,
    paddingHorizontal: espacements.sm,
    marginTop: espacements.xs,
  },
  iconeAjout: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: couleurs.rouge,
    borderStyle: 'dashed',
  },
  texteAjout: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.rouge,
  },
});
