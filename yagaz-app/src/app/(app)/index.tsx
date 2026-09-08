import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeEtat } from '../../components/BadgeEtat';
import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { SelecteurSite } from '../../components/SelecteurSite';
import { useAuth } from '../../auth/AuthContext';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterAutonomie } from '../../utils/niveau';
import type { Bouteille } from '../../api/types';

export default function EcranAccueilFoyer() {
  const { user } = useAuth();
  const {
    sites,
    siteActif,
    definirSiteActif,
    bouteilles,
    bouteilleActive,
    statutSync,
    chargementInitial,
    rafraichir,
  } = useDonnees();

  const autresBouteilles = bouteilles.filter((b) => b.uuid !== bouteilleActive?.uuid);

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.texteBonjour}>Bonjour{user ? `, ${prenomDe(user.nom)}` : ''}</Text>
          <View style={styles.selecteurZone}>
            <SelecteurSite sites={sites} siteActif={siteActif} onChoisir={definirSiteActif} />
          </View>
        </SafeAreaView>
      </LinearGradient>

      <ScrollView
        style={styles.zoneContenu}
        contentContainerStyle={styles.contenuScroll}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}>
        {chargementInitial ? (
          <View style={styles.chargement}>
            <ActivityIndicator color={couleurs.rouge} size="large" />
            <Text style={styles.texteChargement}>Chargement de vos bouteilles...</Text>
          </View>
        ) : !bouteilleActive ? (
          <EtatVide />
        ) : (
          <>
            {statutSync === 'hors_ligne' ? (
              <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
            ) : null}

            <CarteBouteilleActive bouteille={bouteilleActive} nomSite={siteActif?.nom} />

            {autresBouteilles.length > 0 ? (
              <>
                <Text style={styles.titreSection}>Autres bouteilles</Text>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.rangeeAutresBouteilles}>
                  {autresBouteilles.map((bouteille) => (
                    <CarteMiniature key={bouteille.uuid} bouteille={bouteille} />
                  ))}
                </ScrollView>
              </>
            ) : null}
          </>
        )}
      </ScrollView>
    </View>
  );
}

function prenomDe(nomComplet: string): string {
  return nomComplet.split(' ')[0] ?? nomComplet;
}

function EtatVide() {
  return (
    <View style={styles.carteVide}>
      <Text style={styles.titreVide}>Aucune bouteille enregistrée</Text>
      <Text style={styles.texteVide}>
        Enregistrez votre première bouteille pour suivre son autonomie en heures.
      </Text>
      <Bouton titre="Enregistrer une bouteille" onPress={() => router.push('/enregistrer')} style={styles.boutonVide} />
    </View>
  );
}

function CarteBouteilleActive({ bouteille, nomSite }: { bouteille: Bouteille; nomSite?: string }) {
  const { niveau } = bouteille;
  return (
    <Pressable style={styles.carteActive} onPress={() => router.push(`/bouteille/${bouteille.uuid}`)}>
      <View style={styles.enTeteCarteActive}>
        <Text style={styles.libelleCarteActive} numberOfLines={1}>
          Bouteille active{nomSite ? ` - ${nomSite}` : ''}
        </Text>
        <BadgeEtat etat={niveau.etat} />
      </View>

      {!niveau.frais ? <BandeauSync texte="Dernière valeur connue - hors ligne" variante="alerte" /> : null}

      <View style={styles.blocAutonomie}>
        <Text style={styles.chiffreAutonomie}>{formaterAutonomie(niveau.autonomie_heures)}</Text>
        <Text style={styles.libelleAutonomie}>d'autonomie restante</Text>
      </View>

      <View style={styles.barreNiveau}>
        <View style={[styles.barreNiveauRemplie, { width: `${Math.max(0, Math.min(100, niveau.niveau_pct))}%` }]} />
      </View>
      <Text style={styles.texteNiveauSecondaire}>Niveau : {niveau.niveau_pct} %</Text>

      {niveau.estimation ? (
        <Text style={styles.texteEstimation}>Estimation en cours d'affinage</Text>
      ) : null}
    </Pressable>
  );
}

function CarteMiniature({ bouteille }: { bouteille: Bouteille }) {
  return (
    <Pressable style={styles.carteMiniature} onPress={() => router.push(`/bouteille/${bouteille.uuid}`)}>
      <Text style={styles.libelleMiniature} numberOfLines={1}>
        {bouteille.format.code} - {bouteille.role_bouteille === 'active' ? 'Active' : 'Secours'}
      </Text>
      <Text style={styles.pourcentMiniature}>{bouteille.niveau.niveau_pct} %</Text>
      <Text style={styles.autonomieMiniature}>{formaterAutonomie(bouteille.niveau.autonomie_heures)}</Text>
      <BadgeEtat etat={bouteille.niveau.etat} compact />
    </Pressable>
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
    fontSize: 26,
    fontWeight: '700',
    color: couleurs.blanc,
    marginTop: espacements.sm,
  },
  selecteurZone: {
    marginTop: espacements.md,
  },
  zoneContenu: {
    flex: 1,
  },
  contenuScroll: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  chargement: {
    alignItems: 'center',
    paddingVertical: espacements.xxl,
    gap: espacements.md,
  },
  texteChargement: {
    color: couleurs.texteDoux,
    fontSize: 14,
  },
  carteVide: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginTop: -espacements.xl,
    alignItems: 'center',
  },
  titreVide: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
    textAlign: 'center',
  },
  texteVide: {
    fontSize: 14,
    color: couleurs.texteDoux,
    textAlign: 'center',
    marginBottom: espacements.lg,
  },
  boutonVide: {
    alignSelf: 'stretch',
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
    gap: espacements.sm,
  },
  libelleCarteActive: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texteDoux,
    flexShrink: 1,
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
  texteEstimation: {
    fontSize: 12,
    color: couleurs.ambre,
    marginTop: espacements.xs,
    textAlign: 'center',
    fontWeight: '600',
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
    width: 130,
    alignItems: 'center',
    gap: espacements.xs,
  },
  libelleMiniature: {
    fontSize: 12,
    fontWeight: '600',
    color: couleurs.texte,
  },
  pourcentMiniature: {
    fontSize: 22,
    fontWeight: '700',
    color: couleurs.texte,
  },
  autonomieMiniature: {
    fontSize: 12,
    color: couleurs.texteDoux,
  },
});
