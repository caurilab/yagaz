import { Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Icone, LogoYagaz } from '../../components/icones';
import { useAuth } from '../../auth/AuthContext';
import { useEspace } from '../../espace/EspaceContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

const URL_TABLEAU_DE_BORD = 'https://yagaz.ci';

/**
 * Écran de renvoi de l'espace distributeur sur mobile : le pilotage
 * distributeur (demande régionale, tensions par zone, volumes) vit sur le
 * tableau de bord web. On évite ainsi qu'un compte distributeur retombe sur
 * l'interface foyer.
 */
export default function EcranDistributeur() {
  const { user, deconnecter } = useAuth();
  const { espacesDisponibles, reinitialiserChoix } = useEspace();
  const peutChangerEspace = espacesDisponibles.length > 1;

  return (
    <SafeAreaView style={styles.conteneur} edges={['top', 'bottom']}>
      <View style={styles.entete}>
        <LogoYagaz variante="complet" taille={34} />
      </View>

      <View style={styles.corps}>
        <View style={styles.pastille}>
          <Icone nom="analyse" taille={40} couleur={couleurs.rouge} />
        </View>
        <Text style={styles.titre}>Espace distributeur</Text>
        <Text style={styles.texte}>
          Bonjour{user ? ` ${user.nom.split(' ')[0]}` : ''}. Le pilotage distributeur - demande
          regionale, tensions par zone et volumes - se gere sur le tableau de bord web, pense pour
          les grands ecrans.
        </Text>

        <Pressable
          style={styles.boutonPrincipal}
          onPress={() => Linking.openURL(URL_TABLEAU_DE_BORD)}
          accessibilityRole="button">
          <Icone nom="analyse" taille={20} couleur={couleurs.blanc} />
          <Text style={styles.boutonPrincipalTexte}>Ouvrir le tableau de bord</Text>
        </Pressable>
      </View>

      <View style={styles.pied}>
        {peutChangerEspace ? (
          <Pressable style={styles.lien} onPress={reinitialiserChoix} accessibilityRole="button">
            <Icone nom="repeat" taille={18} couleur={couleurs.texteDoux} />
            <Text style={styles.lienTexte}>Changer d'espace</Text>
          </Pressable>
        ) : null}
        <Pressable style={styles.lien} onPress={() => deconnecter()} accessibilityRole="button">
          <Text style={styles.lienTexte}>Se deconnecter</Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
    paddingHorizontal: espacements.lg,
  },
  entete: {
    paddingTop: espacements.md,
  },
  corps: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.md,
  },
  pastille: {
    width: 84,
    height: 84,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: espacements.sm,
  },
  titre: {
    fontSize: 24,
    fontWeight: '800',
    color: couleurs.texte,
  },
  texte: {
    fontSize: 15,
    lineHeight: 22,
    color: couleurs.texteDoux,
    textAlign: 'center',
    maxWidth: 320,
  },
  boutonPrincipal: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    backgroundColor: couleurs.rouge,
    paddingVertical: espacements.md,
    paddingHorizontal: espacements.xl,
    borderRadius: rayons.rond,
    marginTop: espacements.md,
  },
  boutonPrincipalTexte: {
    color: couleurs.blanc,
    fontSize: 16,
    fontWeight: '700',
  },
  pied: {
    alignItems: 'center',
    gap: espacements.sm,
    paddingBottom: espacements.lg,
  },
  lien: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    padding: espacements.sm,
  },
  lienTexte: {
    color: couleurs.texteDoux,
    fontWeight: '600',
    fontSize: 14,
  },
});
