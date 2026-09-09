import type { ReactNode } from 'react';
import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Icone, type NomIcone } from './icones';
import { couleurs, espacements, rayons } from '../../theme/couleurs';

interface ActionEntete {
  titre: string;
  icone?: NomIcone;
  onPress: () => void;
}

interface Props {
  /** Titre de la page, en blanc sur le cache orange (ex. "Mes bouteilles"). */
  titre: string;
  /** Sous-titre optionnel, plus discret (ex. nom du lieu actif). */
  sousTitre?: string;
  /** Affiche un bouton retour rond à gauche du titre (pages ouvertes en pile). */
  retour?: boolean;
  /** Contenu aligné à droite du titre (boutons ronds : cloche, analyse...). */
  actions?: ReactNode;
  /** Bouton d'action principal, large, posé en bas du cache (ex. "Ajouter une bouteille"). */
  bouton?: ActionEntete;
}

/**
 * Cache orange réutilisable, décliné en version compacte du bandeau d'accueil
 * (même dégradé, mêmes coins arrondis en bas) : il remplit tout le haut de
 * l'écran (statut compris) pour qu'il n'y ait plus de bande blanche native au
 * dessus. Le titre y vit en blanc, et les actions de la page (bouton principal,
 * boutons ronds) y sont posées plutôt que dans le contenu.
 *
 * Prérequis : mettre `headerShown: false` sur l'écran dans `_layout.tsx`, sinon
 * l'en-tête natif se superpose au cache.
 */
export function EnteteEcran({ titre, sousTitre, retour = false, actions, bouton }: Props) {
  return (
    <LinearGradient
      colors={[couleurs.degradeDebut, couleurs.degradeFin]}
      start={{ x: 0, y: 0 }}
      end={{ x: 1, y: 1 }}
      style={styles.entete}>
      <SafeAreaView edges={['top']}>
        <View style={styles.ligneHaut}>
          {retour ? (
            <Pressable
              onPress={() => router.back()}
              hitSlop={12}
              style={styles.boutonRond}
              accessibilityRole="button"
              accessibilityLabel="Retour">
              <View style={styles.chevronRetour}>
                <Icone nom="chevron" taille={22} couleur={couleurs.blanc} />
              </View>
            </Pressable>
          ) : null}
          <View style={styles.zoneTitre}>
            <Text style={styles.titre} numberOfLines={1}>
              {titre}
            </Text>
            {sousTitre ? (
              <Text style={styles.sousTitre} numberOfLines={1}>
                {sousTitre}
              </Text>
            ) : null}
          </View>
          {actions ? <View style={styles.actions}>{actions}</View> : null}
        </View>

        {bouton ? (
          <Pressable
            style={({ pressed }) => [styles.bouton, pressed && styles.boutonPresse]}
            onPress={bouton.onPress}
            accessibilityRole="button">
            {bouton.icone ? <Icone nom={bouton.icone} taille={18} couleur={couleurs.rouge} /> : null}
            <Text style={styles.texteBouton} numberOfLines={1}>
              {bouton.titre}
            </Text>
          </Pressable>
        ) : null}
      </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  entete: {
    paddingHorizontal: espacements.lg,
    paddingBottom: espacements.md,
    borderBottomLeftRadius: rayons.xl,
    borderBottomRightRadius: rayons.xl,
  },
  ligneHaut: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    marginTop: espacements.xs,
    minHeight: 40,
  },
  boutonRond: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.2)',
  },
  chevronRetour: {
    transform: [{ rotate: '180deg' }],
  },
  zoneTitre: {
    flex: 1,
    flexShrink: 1,
  },
  titre: {
    fontSize: 24,
    fontWeight: '800',
    color: couleurs.blanc,
    letterSpacing: -0.3,
  },
  sousTitre: {
    fontSize: 13,
    color: couleurs.blanc,
    opacity: 0.9,
    marginTop: 2,
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
  },
  bouton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.sm,
    backgroundColor: couleurs.blanc,
    borderRadius: rayons.rond,
    paddingVertical: espacements.sm + 3,
    paddingHorizontal: espacements.lg,
    marginTop: espacements.md,
  },
  boutonPresse: {
    opacity: 0.9,
  },
  texteBouton: {
    fontSize: 15,
    fontWeight: '800',
    color: couleurs.rouge,
  },
});
