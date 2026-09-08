import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeEtat } from '../../components/BadgeEtat';
import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { BouteilleGaz } from '../../components/BouteilleGaz';
import { EncartTemperature } from '../../components/EncartTemperature';
import { SelecteurSite } from '../../components/SelecteurSite';
import { useAuth } from '../../auth/AuthContext';
import { useDonnees } from '../../data/DonneesContext';
import { useTemperatureSite } from '../../data/useTemperatureSite';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterAutonomie } from '../../utils/niveau';
import { couleurPourFormat } from '../../utils/marque';
import type { Bouteille, Marque } from '../../api/types';

export default function EcranAccueilFoyer() {
  const { user } = useAuth();
  const {
    sites,
    siteActif,
    definirSiteActif,
    bouteilles,
    bouteilleActive,
    marques,
    alertes,
    statutSync,
    chargementInitial,
    rafraichir,
  } = useDonnees();

  const autresBouteilles = bouteilles.filter((b) => b.uuid !== bouteilleActive?.uuid);
  const couleurAmbiance = bouteilleActive ? couleurPourFormat(bouteilleActive.format, marques) : null;
  const { temperature } = useTemperatureSite(siteActif?.uuid);
  const alertesActives = alertes.filter((a) => a.statut !== 'resolue').length;

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <View style={styles.ligneBonjour}>
            <View style={styles.blocBonjour}>
              <Text style={styles.texteBonjour} numberOfLines={1}>
                Bonjour{user ? `, ${prenomDe(user.nom)}` : ''}
              </Text>
              {couleurAmbiance ? (
                <View style={[styles.pastilleAmbiance, { backgroundColor: couleurAmbiance }]} />
              ) : null}
            </View>
            <View style={styles.ligneActionsEntete}>
              <Pressable
                onPress={() => router.push('/analyse')}
                hitSlop={12}
                style={styles.boutonCloche}
                accessibilityRole="button"
                accessibilityLabel="Voir mes statistiques">
                <Ionicons name="stats-chart-outline" size={22} color={couleurs.blanc} />
              </Pressable>
              <Pressable
                onPress={() => router.push('/alertes')}
                hitSlop={12}
                style={styles.boutonCloche}
                accessibilityRole="button"
                accessibilityLabel="Voir les alertes">
                <Ionicons name="notifications-outline" size={22} color={couleurs.blanc} />
                {alertesActives > 0 ? <View style={styles.pastilleAlerte} /> : null}
              </Pressable>
            </View>
          </View>
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

            <CarteBouteilleActive bouteille={bouteilleActive} nomSite={siteActif?.nom} marques={marques} />

            <Pressable style={styles.lienGererBouteilles} onPress={() => router.push('/bouteilles')}>
              <Text style={styles.texteLienGererBouteilles}>Gérer mes bouteilles</Text>
              <Ionicons name="chevron-forward" size={18} color={couleurs.rouge} />
            </Pressable>

            <View style={styles.blocTemperature}>
              <EncartTemperature temperature={temperature} siteUuid={siteActif?.uuid} />
            </View>

            {autresBouteilles.length > 0 ? (
              <>
                <Text style={styles.titreSection}>Autres bouteilles</Text>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.rangeeAutresBouteilles}>
                  {autresBouteilles.map((bouteille) => (
                    <CarteMiniature key={bouteille.uuid} bouteille={bouteille} marques={marques} />
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
      <View style={styles.iconeVide}>
        <Ionicons name="flame-outline" size={28} color={couleurs.rouge} />
      </View>
      <Text style={styles.titreVide}>Aucune bouteille enregistrée</Text>
      <Text style={styles.texteVide}>
        Enregistrez votre première bouteille pour suivre son autonomie en heures.
      </Text>
      <Bouton titre="Enregistrer une bouteille" onPress={() => router.push('/enregistrer')} style={styles.boutonVide} />
    </View>
  );
}

function CarteBouteilleActive({
  bouteille,
  nomSite,
  marques,
}: {
  bouteille: Bouteille;
  nomSite?: string;
  marques: Marque[];
}) {
  const { niveau } = bouteille;
  const couleurMarqueBouteille = couleurPourFormat(bouteille.format, marques);
  return (
    <Pressable style={styles.carteActive} onPress={() => router.push(`/bouteille/${bouteille.uuid}`)}>
      <View style={styles.enTeteCarteActive}>
        <View style={styles.libelleAvecPastille}>
          <View style={[styles.pastilleMarque, { backgroundColor: couleurMarqueBouteille }]} />
          <Text style={styles.libelleCarteActive} numberOfLines={1}>
            Bouteille active{nomSite ? ` - ${nomSite}` : ''} · {bouteille.format.marque}
          </Text>
        </View>
        <BadgeEtat etat={niveau.etat} />
      </View>

      {!niveau.frais ? <BandeauSync texte="Dernière valeur connue - hors ligne" variante="alerte" /> : null}

      <View style={styles.heroNiveau}>
        <BouteilleGaz
          couleur={couleurMarqueBouteille}
          code={bouteille.format.code}
          marqueNom={bouteille.format.marque}
          niveauPct={niveau.niveau_pct}
          taille={132}
        />
        <View style={styles.blocAutonomie}>
          <Text style={styles.chiffreAutonomie}>{formaterAutonomie(niveau.autonomie_heures)}</Text>
          <Text style={styles.libelleAutonomie}>d'autonomie restante</Text>
        </View>
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

function CarteMiniature({ bouteille, marques }: { bouteille: Bouteille; marques: Marque[] }) {
  const couleurMarqueBouteille = couleurPourFormat(bouteille.format, marques);
  return (
    <Pressable style={styles.carteMiniature} onPress={() => router.push(`/bouteille/${bouteille.uuid}`)}>
      <BouteilleGaz
        couleur={couleurMarqueBouteille}
        code={bouteille.format.code}
        marqueNom={bouteille.format.marque}
        niveauPct={bouteille.niveau.niveau_pct}
        taille={72}
        reflet={false}
      />
      <Text style={styles.libelleMiniature} numberOfLines={1}>
        {bouteille.role_bouteille === 'active' ? 'Active' : 'Secours'}
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
  ligneBonjour: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: espacements.sm,
  },
  blocBonjour: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    flexShrink: 1,
  },
  texteBonjour: {
    fontSize: 26,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  pastilleAmbiance: {
    width: 12,
    height: 12,
    borderRadius: rayons.rond,
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,0.7)',
  },
  ligneActionsEntete: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
  },
  boutonCloche: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.2)',
  },
  pastilleAlerte: {
    position: 'absolute',
    top: 8,
    right: 8,
    width: 9,
    height: 9,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.danger,
    borderWidth: 1.5,
    borderColor: couleurs.degradeDebut,
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
    borderRadius: rayons.xl,
    padding: espacements.lg,
    marginTop: -espacements.xl,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 10 },
    elevation: 4,
  },
  iconeVide: {
    width: 56,
    height: 56,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: espacements.md,
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
    borderRadius: rayons.xl,
    padding: espacements.lg,
    marginTop: -espacements.xl,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 10 },
    elevation: 4,
  },
  enTeteCarteActive: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacements.sm,
  },
  libelleAvecPastille: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    flexShrink: 1,
  },
  pastilleMarque: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  libelleCarteActive: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texteDoux,
    flexShrink: 1,
  },
  heroNiveau: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.lg,
    marginVertical: espacements.lg,
  },
  blocAutonomie: {
    alignItems: 'center',
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
  lienGererBouteilles: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.xs,
    marginTop: espacements.md,
    paddingVertical: espacements.sm,
  },
  texteLienGererBouteilles: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.rouge,
  },
  blocTemperature: {
    marginTop: espacements.md,
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
    borderRadius: rayons.lg,
    padding: espacements.md,
    width: 130,
    alignItems: 'center',
    gap: espacements.xs,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
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
