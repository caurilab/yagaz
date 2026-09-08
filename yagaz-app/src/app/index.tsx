import { LinearGradient } from 'expo-linear-gradient';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { couleurs, espacements, rayons } from '../../theme/couleurs';

// --- Données de démonstration (à remplacer par les données réelles de l'API) ---

type EtatBouteille = 'correct' | 'bas';

const bouteilleActive = {
  autonomieHeures: 18,
  niveauPourcent: 62,
  etat: 'correct' as EtatBouteille,
};

const autresBouteilles: {
  id: string;
  libelle: string;
  niveauPourcent: number;
  etat: EtatBouteille;
}[] = [
  { id: 'b2', libelle: 'Cuisine', niveauPourcent: 40, etat: 'correct' },
  { id: 'b3', libelle: 'Chauffage', niveauPourcent: 12, etat: 'bas' },
  { id: 'b4', libelle: 'Réserve', niveauPourcent: 100, etat: 'correct' },
];

// --- Écran d'accueil foyer ---

export default function EcranAccueilFoyer() {
  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.texteBonjour}>Bonjour</Text>
          <Text style={styles.texteSousTitre}>Voici l'état de vos bouteilles</Text>
        </SafeAreaView>
      </LinearGradient>

      <ScrollView
        style={styles.zoneContenu}
        contentContainerStyle={styles.contenuScroll}
        showsVerticalScrollIndicator={false}>
        {/* Carte principale : bouteille active */}
        <View style={styles.carteActive}>
          <View style={styles.enTeteCarteActive}>
            <Text style={styles.libelleCarteActive}>Bouteille active - Salon</Text>
            <BadgeEtat etat={bouteilleActive.etat} />
          </View>

          <View style={styles.blocAutonomie}>
            <Text style={styles.chiffreAutonomie}>{bouteilleActive.autonomieHeures} h</Text>
            <Text style={styles.libelleAutonomie}>d'autonomie restante</Text>
          </View>

          <View style={styles.barreNiveau}>
            <View
              style={[
                styles.barreNiveauRemplie,
                { width: `${bouteilleActive.niveauPourcent}%` },
              ]}
            />
          </View>
          <Text style={styles.texteNiveauSecondaire}>
            Niveau estimé : {bouteilleActive.niveauPourcent} %
          </Text>
        </View>

        {/* Rangée des autres bouteilles */}
        <Text style={styles.titreSection}>Autres bouteilles</Text>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.rangeeAutresBouteilles}>
          {autresBouteilles.map((bouteille) => (
            <View key={bouteille.id} style={styles.carteMiniature}>
              <Text style={styles.libelleMiniature}>{bouteille.libelle}</Text>
              <Text style={styles.pourcentMiniature}>{bouteille.niveauPourcent} %</Text>
              <BadgeEtat etat={bouteille.etat} compact />
            </View>
          ))}
        </ScrollView>
      </ScrollView>
    </View>
  );
}

function BadgeEtat({ etat, compact = false }: { etat: EtatBouteille; compact?: boolean }) {
  const estCorrect = etat === 'correct';
  return (
    <View
      style={[
        styles.badge,
        compact && styles.badgeCompact,
        { backgroundColor: estCorrect ? couleurs.vertOk : couleurs.rouge },
      ]}>
      <Text style={[styles.texteBadge, compact && styles.texteBadgeCompact]}>
        {estCorrect ? 'Correct' : 'Bas'}
      </Text>
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
    paddingBottom: espacements.xl,
    borderBottomLeftRadius: rayons.xl,
    borderBottomRightRadius: rayons.xl,
  },
  texteBonjour: {
    fontSize: 30,
    fontWeight: '700',
    color: couleurs.blanc,
    marginTop: espacements.sm,
  },
  texteSousTitre: {
    fontSize: 15,
    color: couleurs.blanc,
    opacity: 0.9,
    marginTop: espacements.xs,
  },
  zoneContenu: {
    flex: 1,
  },
  contenuScroll: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  carteActive: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginTop: -espacements.xl,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 8 },
    elevation: 4,
  },
  enTeteCarteActive: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  libelleCarteActive: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  blocAutonomie: {
    alignItems: 'center',
    marginVertical: espacements.lg,
  },
  chiffreAutonomie: {
    fontSize: 72,
    fontWeight: '800',
    color: couleurs.texte,
    lineHeight: 76,
  },
  libelleAutonomie: {
    fontSize: 16,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  barreNiveau: {
    height: 10,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    overflow: 'hidden',
  },
  barreNiveauRemplie: {
    height: '100%',
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rouge,
  },
  texteNiveauSecondaire: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: espacements.sm,
    textAlign: 'center',
  },
  titreSection: {
    fontSize: 17,
    fontWeight: '700',
    color: couleurs.texte,
    marginTop: espacements.xl,
    marginBottom: espacements.md,
  },
  rangeeAutresBouteilles: {
    gap: espacements.md,
    paddingRight: espacements.lg,
  },
  carteMiniature: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.md,
    width: 120,
    alignItems: 'center',
    gap: espacements.xs,
  },
  libelleMiniature: {
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.texte,
  },
  pourcentMiniature: {
    fontSize: 22,
    fontWeight: '700',
    color: couleurs.texte,
  },
  badge: {
    paddingHorizontal: espacements.sm,
    paddingVertical: espacements.xs / 2,
    borderRadius: rayons.rond,
  },
  badgeCompact: {
    paddingHorizontal: espacements.sm,
    paddingVertical: 2,
  },
  texteBadge: {
    fontSize: 12,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  texteBadgeCompact: {
    fontSize: 11,
  },
});
