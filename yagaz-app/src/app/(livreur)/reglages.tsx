import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { useAuth } from '../../auth/AuthContext';
import { useDialogue } from '../../data/DialogueContext';
import { useEspace } from '../../espace/EspaceContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

export default function EcranReglagesLivreur() {
  const { user, deconnecter } = useAuth();
  const { espacesDisponibles, reinitialiserChoix } = useEspace();
  const { confirmer } = useDialogue();

  async function confirmerDeconnexion() {
    if (
      await confirmer({
        titre: 'Se déconnecter',
        message: 'Voulez-vous vraiment vous déconnecter ?',
        texteConfirmer: 'Se déconnecter',
        destructif: true,
      })
    ) {
      deconnecter();
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top', 'bottom']}>
      <ScrollView contentContainerStyle={styles.contenu}>
        <Text style={styles.titre}>Réglages</Text>

        {user ? (
          <View style={styles.carte}>
            <Text style={styles.nomUtilisateur}>{user.nom}</Text>
            <Text style={styles.telephoneUtilisateur}>{user.telephone}</Text>
          </View>
        ) : null}

        {espacesDisponibles.length > 1 ? (
          <Bouton titre="Changer d'espace" variante="contour" onPress={reinitialiserChoix} style={styles.bouton} />
        ) : null}

        <Bouton titre="Se déconnecter" variante="discret" onPress={confirmerDeconnexion} style={styles.bouton} />
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  contenu: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.lg,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginBottom: espacements.lg,
  },
  nomUtilisateur: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
  },
  telephoneUtilisateur: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  bouton: {
    marginTop: espacements.md,
  },
});
