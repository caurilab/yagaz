import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, Easing, Modal, Pressable, StyleSheet, Text, View } from 'react-native';

import { Icone } from './icones';
import { IllustrationAstuce } from './illustrations/IllustrationAstuce';
import { ASTUCES, LIBELLES_CATEGORIE_ASTUCE } from '../data/astuces';
import { couleurs, espacements, rayons } from '../../theme/couleurs';

interface Props {
  visible: boolean;
  onClose: () => void;
  /** Index de départ dans `ASTUCES`. Défaut : 0. */
  indexInitial?: number;
  /** Action du bouton "Voir toutes les astuces" (ouvre la page dédiée). */
  onVoirToutes?: () => void;
}

const ECHELLE_FERMEE = 0.92;

/**
 * Modale maison présentant les astuces gaz une par une, avec navigation
 * cyclique ("Astuce suivante"). Overlay animé en opacité, carte en
 * fondu + léger scale - cohérent avec `SelecteurSite`.
 */
export function ModaleAstuces({ visible, onClose, indexInitial = 0, onVoirToutes }: Props) {
  const [index, setIndex] = useState(indexInitial);
  const opacite = useRef(new Animated.Value(0)).current;
  const echelle = useRef(new Animated.Value(ECHELLE_FERMEE)).current;

  const fermer = useCallback(() => {
    Animated.parallel([
      Animated.timing(opacite, { toValue: 0, duration: 160, useNativeDriver: true }),
      Animated.timing(echelle, {
        toValue: ECHELLE_FERMEE,
        duration: 160,
        easing: Easing.in(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start(() => {
      onClose();
    });
  }, [opacite, echelle, onClose]);

  // Ré-initialise l'astuce affichée et anime l'entrée à chaque ouverture.
  useEffect(() => {
    if (!visible) return;
    setIndex(indexInitial);
    opacite.setValue(0);
    echelle.setValue(ECHELLE_FERMEE);
    const animationEntree = Animated.parallel([
      Animated.timing(opacite, { toValue: 1, duration: 200, useNativeDriver: true }),
      Animated.spring(echelle, { toValue: 1, friction: 8, tension: 90, useNativeDriver: true }),
    ]);
    animationEntree.start();
    return () => animationEntree.stop();
  }, [visible, indexInitial, opacite, echelle]);

  const voirToutes = useCallback(() => {
    Animated.parallel([
      Animated.timing(opacite, { toValue: 0, duration: 160, useNativeDriver: true }),
      Animated.timing(echelle, {
        toValue: ECHELLE_FERMEE,
        duration: 160,
        easing: Easing.in(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start(() => {
      onClose();
      onVoirToutes?.();
    });
  }, [opacite, echelle, onClose, onVoirToutes]);

  const astuce = ASTUCES[index] ?? ASTUCES[0];

  return (
    <Modal visible={visible} animationType="none" transparent onRequestClose={fermer}>
      <View style={styles.conteneur}>
        <Animated.View style={[styles.overlay, { opacity: opacite }]}>
          <Pressable style={StyleSheet.absoluteFill} onPress={fermer} />
        </Animated.View>

        <Animated.View style={[styles.carte, { opacity: opacite, transform: [{ scale: echelle }] }]}>
          <Pressable
            style={styles.boutonFermer}
            onPress={fermer}
            hitSlop={12}
            accessibilityRole="button"
            accessibilityLabel="Fermer">
            <Icone nom="fermer" taille={18} couleur={couleurs.texteDoux} />
          </Pressable>

          <View style={styles.zoneIllustration}>
            <IllustrationAstuce categorie={astuce.categorie} taille={128} />
          </View>

          <View style={styles.etiquette}>
            <Text style={styles.texteEtiquette}>{LIBELLES_CATEGORIE_ASTUCE[astuce.categorie]}</Text>
          </View>

          <Text style={styles.titre}>{astuce.titre}</Text>
          <Text style={styles.message}>{astuce.message}</Text>

          <Pressable style={styles.boutonVoirToutes} onPress={voirToutes} accessibilityRole="button">
            <Text style={styles.texteBoutonVoirToutes}>Voir toutes les astuces</Text>
            <Icone nom="chevron" taille={16} couleur={couleurs.blanc} />
          </Pressable>
          <Pressable style={styles.boutonFermerTexte} onPress={fermer} accessibilityRole="button">
            <Text style={styles.texteBoutonFermer}>Fermer</Text>
          </Pressable>
        </Animated.View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: espacements.lg,
  },
  overlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(20,25,40,0.45)',
  },
  carte: {
    width: '100%',
    maxWidth: 400,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.18,
    shadowRadius: 24,
    shadowOffset: { width: 0, height: 12 },
    elevation: 12,
  },
  boutonFermer: {
    position: 'absolute',
    top: espacements.md,
    right: espacements.md,
    width: 32,
    height: 32,
    borderRadius: rayons.rond,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: couleurs.fond,
    zIndex: 1,
  },
  zoneIllustration: {
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: espacements.sm,
    marginBottom: espacements.md,
  },
  etiquette: {
    alignSelf: 'center',
    backgroundColor: couleurs.rougeClair,
    borderRadius: rayons.rond,
    paddingHorizontal: espacements.sm,
    paddingVertical: 3,
    marginBottom: espacements.sm,
  },
  texteEtiquette: {
    fontSize: 11.5,
    fontWeight: '700',
    color: couleurs.rouge,
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  titre: {
    fontSize: 19,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.sm,
    textAlign: 'center',
  },
  message: {
    fontSize: 14.5,
    lineHeight: 21,
    color: couleurs.texteDoux,
    marginBottom: espacements.lg,
    textAlign: 'center',
  },
  boutonVoirToutes: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.xs,
    backgroundColor: couleurs.rouge,
    borderRadius: rayons.lg,
    paddingVertical: espacements.md,
  },
  texteBoutonVoirToutes: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  boutonFermerTexte: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: espacements.md,
    marginTop: espacements.xs,
  },
  texteBoutonFermer: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texteDoux,
  },
});
