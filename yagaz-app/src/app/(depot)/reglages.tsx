import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Bouton } from '../../components/Bouton';
import { EnteteEcran } from '../../components/EnteteEcran';
import { Icone } from '../../components/icones';
import { useAuth } from '../../auth/AuthContext';
import { useDepot } from '../../data/DepotContext';
import { useDialogue } from '../../data/DialogueContext';
import { useEspace } from '../../espace/EspaceContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

export default function EcranReglagesDepot() {
  const { user, deconnecter } = useAuth();
  const { orgNom } = useDepot();
  const { roles, espacesDisponibles, definirDepotOrgActif, depotOrgActifUuid, reinitialiserChoix } = useEspace();
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
    <View style={styles.conteneur}>
      <EnteteEcran titre="Réglages" />
      <ScrollView contentContainerStyle={styles.contenu}>
        {user ? (
          <View style={styles.carte}>
            <Text style={styles.nomUtilisateur}>{user.nom}</Text>
            <Text style={styles.telephoneUtilisateur}>{user.telephone}</Text>
            <Text style={styles.orgActuel}>{orgNom ?? 'Dépôt'}</Text>
          </View>
        ) : null}

        <Text style={styles.sectionTitre}>Équipe</Text>
        <Pressable style={styles.carteLien} onPress={() => router.push('/(depot)/equipe')} accessibilityRole="button">
          <View style={styles.ligneLien}>
            <Icone nom="livraison" taille={20} couleur={couleurs.rouge} />
            <Text style={styles.libelleLien}>Mon équipe (livreurs)</Text>
          </View>
          <Icone nom="chevron" taille={18} couleur={couleurs.texteDoux} />
        </Pressable>

        {roles && roles.depots.length > 1 ? (
          <>
            <Text style={styles.sectionTitre}>Mes dépôts</Text>
            <View style={styles.carte}>
              {roles.depots.map((depot) => (
                <Pressable
                  key={depot.uuid}
                  style={styles.ligne}
                  onPress={() => definirDepotOrgActif(depot.uuid)}>
                  <Text style={styles.libelleLigne}>{depot.nom}</Text>
                  {depot.uuid === depotOrgActifUuid ? <Text style={styles.coche}>✓</Text> : null}
                </Pressable>
              ))}
            </View>
          </>
        ) : null}

        {espacesDisponibles.length > 1 ? (
          <Bouton titre="Changer d'espace" variante="contour" onPress={reinitialiserChoix} style={styles.bouton} />
        ) : null}

        <Bouton titre="Se déconnecter" variante="discret" onPress={confirmerDeconnexion} style={styles.bouton} />
      </ScrollView>
    </View>
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
  orgActuel: {
    fontSize: 13,
    color: couleurs.rouge,
    fontWeight: '600',
    marginTop: espacements.sm,
  },
  sectionTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  carteLien: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginBottom: espacements.lg,
  },
  ligneLien: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    flexShrink: 1,
  },
  libelleLien: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.texte,
    flexShrink: 1,
  },
  ligne: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: espacements.sm,
  },
  libelleLigne: {
    fontSize: 15,
    color: couleurs.texte,
  },
  coche: {
    fontSize: 16,
    color: couleurs.rouge,
    fontWeight: '700',
  },
  bouton: {
    marginTop: espacements.md,
  },
});
