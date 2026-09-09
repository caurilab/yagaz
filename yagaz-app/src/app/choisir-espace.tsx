import { LinearGradient } from 'expo-linear-gradient';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAuth } from '../auth/AuthContext';
import { useEspace } from '../espace/EspaceContext';
import { couleurs, espacements, rayons } from '../../theme/couleurs';

const DESCRIPTIONS: Record<string, string> = {
  foyer: 'Suivre mes bouteilles et mes commandes.',
  depot: 'Gérer le stock et les commandes du comptoir.',
  livreur: 'Voir mes missions de livraison du jour.',
  mandataire: 'Suivre la tournée du jour et mes dépôts.',
  distributeur: 'Pilotage régional - sur le tableau de bord web.',
};

const SYMBOLES: Record<string, string> = {
  foyer: '🏠',
  depot: '🏬',
  livreur: '🛵',
  mandataire: '🚚',
  distributeur: '📊',
};

/**
 * Sélecteur d'espace après connexion (contrat 10 §1). Affiché seulement
 * quand le compte a plus d'un rôle disponible - un foyer simple ne passe
 * jamais par cet écran.
 */
export default function EcranChoisirEspace() {
  const { user, deconnecter } = useAuth();
  const { roles, espacesDisponibles, definirEspace } = useEspace();

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.titre}>Bonjour{user ? `, ${user.nom.split(' ')[0]}` : ''}</Text>
          <Text style={styles.sousTitre}>Quel espace voulez-vous ouvrir ?</Text>
        </SafeAreaView>
      </LinearGradient>

      <View style={styles.corps}>
        {espacesDisponibles.map((option) => {
          if (option.type === 'depot' && roles && roles.depots.length > 1) {
            return roles.depots.map((depot) => (
              <Pressable
                key={depot.uuid}
                style={styles.carte}
                onPress={() => definirEspace('depot', depot.uuid)}
                accessibilityRole="button">
                <Text style={styles.symbole}>{SYMBOLES.depot}</Text>
                <View style={styles.carteTexte}>
                  <Text style={styles.carteTitre}>Dépôt - {depot.nom}</Text>
                  <Text style={styles.carteDescription}>{DESCRIPTIONS.depot}</Text>
                </View>
              </Pressable>
            ));
          }
          if (option.type === 'mandataire' && roles && roles.mandataires.length > 1) {
            return roles.mandataires.map((mandataire) => (
              <Pressable
                key={mandataire.uuid}
                style={styles.carte}
                onPress={() => definirEspace('mandataire', mandataire.uuid)}
                accessibilityRole="button">
                <Text style={styles.symbole}>{SYMBOLES.mandataire}</Text>
                <View style={styles.carteTexte}>
                  <Text style={styles.carteTitre}>Mandataire - {mandataire.nom}</Text>
                  <Text style={styles.carteDescription}>{DESCRIPTIONS.mandataire}</Text>
                </View>
              </Pressable>
            ));
          }
          return (
            <Pressable
              key={option.type}
              style={styles.carte}
              onPress={() =>
                definirEspace(option.type, roles?.depots[0]?.uuid ?? roles?.mandataires[0]?.uuid)
              }
              accessibilityRole="button">
              <Text style={styles.symbole}>{SYMBOLES[option.type]}</Text>
              <View style={styles.carteTexte}>
                <Text style={styles.carteTitre}>{option.libelle}</Text>
                <Text style={styles.carteDescription}>{DESCRIPTIONS[option.type]}</Text>
              </View>
            </Pressable>
          );
        })}

        <Pressable style={styles.lienDeconnexion} onPress={() => deconnecter()}>
          <Text style={styles.texteDeconnexion}>Se déconnecter</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  entete: {
    paddingHorizontal: espacements.lg,
    paddingBottom: espacements.xxl,
    borderBottomLeftRadius: rayons.xl,
    borderBottomRightRadius: rayons.xl,
  },
  titre: {
    fontSize: 28,
    fontWeight: '800',
    color: couleurs.blanc,
    marginTop: espacements.md,
  },
  sousTitre: {
    fontSize: 15,
    color: couleurs.blanc,
    opacity: 0.9,
    marginTop: espacements.xs,
  },
  corps: {
    padding: espacements.lg,
    marginTop: -espacements.xl,
    gap: espacements.md,
  },
  carte: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    minHeight: 88,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 8 },
    elevation: 4,
  },
  symbole: {
    fontSize: 32,
  },
  carteTexte: {
    flex: 1,
  },
  carteTitre: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
  },
  carteDescription: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  lienDeconnexion: {
    alignSelf: 'center',
    marginTop: espacements.lg,
    padding: espacements.sm,
  },
  texteDeconnexion: {
    color: couleurs.texteDoux,
    fontWeight: '600',
    fontSize: 14,
  },
});
