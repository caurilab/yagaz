import { useEffect, useRef, useState, type ReactNode } from 'react';
import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import {
  Animated,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeEtat } from '../../components/BadgeEtat';
import { BandeauSync } from '../../components/BandeauSync';
import { BarreProgression } from '../../components/BarreProgression';
import { Bouton } from '../../components/Bouton';
import { BouteilleGaz } from '../../components/BouteilleGaz';
import { CarteAstuce } from '../../components/CarteAstuce';
import { ChargementYagaz } from '../../components/ChargementYagaz';
import { EncartConnecterMateriel } from '../../components/EncartConnecterMateriel';
import { EncartTemperature } from '../../components/EncartTemperature';
import { Icone, LogoYagaz } from '../../components/icones';
import { ModaleAstuces } from '../../components/ModaleAstuces';
import { SelecteurSite } from '../../components/SelecteurSite';
import { indexAstuceSecuriteDuJour } from '../../data/astuces';
import { useAuth } from '../../auth/AuthContext';
import { useDonnees } from '../../data/DonneesContext';
import { useTemperatureSite } from '../../data/useTemperatureSite';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterAutonomie, couleursEtat, libellesEtat } from '../../utils/niveau';
import { couleurPourFormat } from '../../utils/marque';
import type { Bouteille, EtatNiveau, Marque } from '../../api/types';

/**
 * Ne montrer la modale de sécurité qu'une fois par lancement de l'app
 * (démarrage à froid) : ce drapeau au niveau module survit aux re-rendus et
 * aux retours sur l'accueil, mais est réinitialisé à chaque relance.
 */
let modaleSecuriteDejaAffichee = false;

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
  const { temperature } = useTemperatureSite(siteActif?.uuid);
  const alertesActives = alertes.filter((a) => a.statut !== 'resolue').length;
  const aBalance = siteActif?.a_balance ?? true;

  // Modale de sécurité au démarrage : une astuce de sécurité, une seule fois
  // par lancement, une fois les données prêtes (pas pendant le chargement).
  const [modaleSecuriteVisible, setModaleSecuriteVisible] = useState(false);
  const [indexSecurite] = useState(indexAstuceSecuriteDuJour);
  useEffect(() => {
    if (modaleSecuriteDejaAffichee || chargementInitial) return;
    modaleSecuriteDejaAffichee = true;
    setModaleSecuriteVisible(true);
  }, [chargementInitial]);

  const heroAutonomie = bouteilleActive
    ? aBalance
      ? formaterAutonomie(bouteilleActive.niveau.autonomie_heures).replace(/\s*h$/i, '')
      : '--'
    : null;

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <View style={styles.ligneHaut}>
            <LogoYagaz variante="complet" taille={30} ton="blanc" />
            <View style={styles.ligneActionsEntete}>
              <Pressable
                onPress={() => router.push('/analyse')}
                hitSlop={12}
                style={styles.boutonCloche}
                accessibilityRole="button"
                accessibilityLabel="Voir mes statistiques">
                <Icone nom="analyse" taille={22} couleur={couleurs.blanc} />
              </Pressable>
              <Pressable
                onPress={() => router.push('/alertes')}
                hitSlop={12}
                style={styles.boutonCloche}
                accessibilityRole="button"
                accessibilityLabel="Voir les alertes">
                <Icone nom="cloche" taille={22} couleur={couleurs.blanc} />
                {alertesActives > 0 ? <View style={styles.pastilleAlerte} /> : null}
              </Pressable>
            </View>
          </View>

          <View style={styles.ligneGreeting}>
            <Text style={styles.texteBonjour} numberOfLines={1}>
              Bonjour{user ? `, ${prenomDe(user.nom)}` : ''}
            </Text>
            <SelecteurSite sites={sites} siteActif={siteActif} onChoisir={definirSiteActif} />
          </View>

          {bouteilleActive && heroAutonomie != null ? (
            <>
              <View style={styles.ligneHero}>
                <Text style={[styles.heroChiffre, !aBalance && styles.heroDesactive]}>{heroAutonomie}</Text>
                <Text style={[styles.heroUnite, !aBalance && styles.heroDesactive]}>heures de flamme</Text>
              </View>
              <View style={styles.lignePill}>
                <View style={styles.pill}>
                  <Text style={styles.pillTexte}>Bouteille active</Text>
                </View>
                <Text style={styles.pillDescription} numberOfLines={1}>
                  {bouteilleActive.format.code} - {bouteilleActive.format.marque} -{' '}
                  {etatLisible(bouteilleActive.niveau.etat)}
                </Text>
              </View>
            </>
          ) : null}
        </SafeAreaView>
      </LinearGradient>

      <ScrollView
        style={styles.zoneContenu}
        contentContainerStyle={styles.contenuScroll}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}>
        {chargementInitial ? (
          <View style={styles.chargement}>
            <ChargementYagaz texte="Chargement de vos bouteilles..." />
          </View>
        ) : !bouteilleActive ? (
          <EtatVide />
        ) : (
          <>
            {statutSync === 'hors_ligne' ? (
              <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
            ) : null}

            <CarteBouteilleActive bouteille={bouteilleActive} marques={marques} aBalance={aBalance} />

            <Pressable style={styles.lienGererBouteilles} onPress={() => router.push('/bouteilles')}>
              <Text style={styles.texteLienGererBouteilles}>Gérer mes bouteilles</Text>
              <Icone nom="chevron" taille={18} couleur={couleurs.rouge} />
            </Pressable>

            <View style={styles.blocTemperature}>
              <EncartTemperature
                temperature={temperature}
                siteUuid={siteActif?.uuid}
                connecte={siteActif?.a_temperature ?? true}
              />
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

            <CarteAstuce />
          </>
        )}
      </ScrollView>

      <ModaleAstuces
        visible={modaleSecuriteVisible}
        onClose={() => setModaleSecuriteVisible(false)}
        indexInitial={indexSecurite}
        onVoirToutes={() => router.push('/astuces')}
      />
    </View>
  );
}

function prenomDe(nomComplet: string): string {
  return nomComplet.split(' ')[0] ?? nomComplet;
}

/** Libellé lisible et sobre pour l'état de niveau, intégré dans une phrase (ex. "niveau correct"). */
function etatLisible(etat: EtatNiveau): string {
  return `niveau ${libellesEtat[etat].toLowerCase()}`;
}

function EtatVide() {
  return (
    <View style={styles.carteVide}>
      <View style={styles.iconeVide}>
        <Icone nom="flamme" taille={28} couleur={couleurs.rouge} />
      </View>
      <Text style={styles.titreVide}>Aucune bouteille enregistrée</Text>
      <Text style={styles.texteVide}>
        Enregistrez votre première bouteille pour suivre son autonomie en heures.
      </Text>
      <Bouton titre="Enregistrer une bouteille" onPress={() => router.push('/enregistrer')} style={styles.boutonVide} />
    </View>
  );
}

/**
 * Pressable avec léger retour tactile (scale ~0.97 au press, retour à 1 au relâchement) - ne
 * change pas le style visuel au repos, juste un `Animated.View` interne pour le scale.
 */
function CartePressable({
  style,
  onPress,
  children,
}: {
  style?: StyleProp<ViewStyle>;
  onPress: () => void;
  children: ReactNode;
}) {
  const echelle = useRef(new Animated.Value(1)).current;

  function surPressIn() {
    Animated.spring(echelle, { toValue: 0.97, friction: 6, tension: 120, useNativeDriver: true }).start();
  }

  function surPressOut() {
    Animated.spring(echelle, { toValue: 1, friction: 6, tension: 120, useNativeDriver: true }).start();
  }

  return (
    <Pressable onPress={onPress} onPressIn={surPressIn} onPressOut={surPressOut} style={style}>
      <Animated.View style={{ transform: [{ scale: echelle }] }}>{children}</Animated.View>
    </Pressable>
  );
}

function CarteBouteilleActive({
  bouteille,
  marques,
  aBalance,
}: {
  bouteille: Bouteille;
  marques: Marque[];
  /** `a_balance` du site (gating ADR 0012) : `false` grise le niveau/l'autonomie avec un CTA vers Matériels. */
  aBalance: boolean;
}) {
  const { niveau } = bouteille;
  const couleurMarqueBouteille = couleurPourFormat(bouteille.format, marques);
  const pctBorne = Math.max(0, Math.min(100, niveau.niveau_pct));
  const gazRestantKg = (niveau.gaz_g / 1000).toFixed(1).replace('.', ',');

  return (
    <CartePressable style={styles.carteActive} onPress={() => router.push(`/bouteille/${bouteille.uuid}`)}>
      <View style={styles.enTeteCarteActive}>
        <Text style={styles.titreCarteActive}>Ma bouteille active</Text>
        <View style={styles.lienDetail}>
          <Text style={styles.texteLienDetail}>Détail</Text>
          <Icone nom="chevron" taille={14} couleur={couleurs.rouge} />
        </View>
      </View>

      {aBalance && !niveau.frais ? <BandeauSync texte="Dernière valeur connue - hors ligne" variante="alerte" /> : null}

      <View style={styles.corpsBouteilleActive}>
        <BouteilleGaz
          couleur={aBalance ? couleurMarqueBouteille : null}
          code={bouteille.format.code}
          marqueNom={bouteille.format.marque}
          niveauPct={aBalance ? niveau.niveau_pct : undefined}
          taille={116}
        />

        <View style={styles.infoBlocActive}>
          <View style={styles.chipMarque}>
            <View style={[styles.pointMarque, { backgroundColor: couleurMarqueBouteille }]} />
            <Text style={styles.texteChipMarque} numberOfLines={1}>
              {bouteille.format.marque} - {bouteille.format.code}
            </Text>
          </View>

          {aBalance ? (
            <>
              <View style={styles.ligneNiveauActive}>
                <Text style={styles.pctNiveauActive}>{niveau.niveau_pct} %</Text>
                <BadgeEtat etat={niveau.etat} />
              </View>

              <View style={styles.barreNiveauConteneur}>
                <BarreProgression pct={pctBorne} hauteur={9} couleurFond={couleurs.rougeClair} />
              </View>

              {niveau.estimation ? (
                <Text style={styles.texteEstimation}>Estimation en cours d'affinage</Text>
              ) : null}

              <View style={styles.metaLigne}>
                <View style={styles.metaCol}>
                  <Text style={styles.metaCle}>Autonomie</Text>
                  <Text style={styles.metaValeur} numberOfLines={1}>
                    {formaterAutonomie(niveau.autonomie_heures)}
                  </Text>
                </View>
                <View style={styles.metaCol}>
                  <Text style={styles.metaCle}>Gaz restant</Text>
                  <Text style={styles.metaValeur} numberOfLines={1}>
                    {gazRestantKg} kg
                  </Text>
                </View>
                <View style={styles.metaCol}>
                  <Text style={styles.metaCle}>Débit</Text>
                  <Text style={styles.metaValeur} numberOfLines={1}>
                    {niveau.debit_g_par_h} g/h
                  </Text>
                </View>
              </View>
            </>
          ) : (
            <EncartConnecterMateriel texte="Connectez votre pèse-bouteille pour voir le niveau" />
          )}
        </View>
      </View>
    </CartePressable>
  );
}

function CarteMiniature({ bouteille, marques }: { bouteille: Bouteille; marques: Marque[] }) {
  const couleurMarqueBouteille = couleurPourFormat(bouteille.format, marques);
  const couleurPct = couleursEtat[bouteille.niveau.etat];
  return (
    <CartePressable style={styles.carteMiniature} onPress={() => router.push(`/bouteille/${bouteille.uuid}`)}>
      <BouteilleGaz
        couleur={couleurMarqueBouteille}
        code={bouteille.format.code}
        marqueNom={bouteille.format.marque}
        niveauPct={bouteille.niveau.niveau_pct}
        taille={72}
        reflet={false}
      />
      <Text style={styles.libelleMiniature} numberOfLines={1}>
        {bouteille.format.code} {bouteille.role_bouteille === 'active' ? 'active' : 'secours'}
      </Text>
      <Text style={[styles.pourcentMiniature, { color: couleurPct }]}>{bouteille.niveau.niveau_pct} %</Text>
    </CartePressable>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  entete: {
    paddingHorizontal: espacements.lg,
    paddingBottom: espacements.lg,
    borderBottomLeftRadius: rayons.xl,
    borderBottomRightRadius: rayons.xl,
  },
  ligneHaut: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: espacements.sm,
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
  ligneGreeting: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espacements.sm,
    marginTop: espacements.md,
  },
  texteBonjour: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.blanc,
    opacity: 0.9,
    flexShrink: 1,
  },
  ligneHero: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: espacements.sm,
    marginTop: espacements.md,
  },
  heroChiffre: {
    fontSize: 60,
    fontWeight: '800',
    color: couleurs.blanc,
    lineHeight: 62,
    letterSpacing: -1.5,
  },
  heroUnite: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.blanc,
    opacity: 0.92,
    paddingBottom: espacements.sm,
  },
  heroDesactive: {
    opacity: 0.55,
  },
  lignePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    marginTop: espacements.sm,
    flexWrap: 'wrap',
  },
  pill: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: espacements.sm,
    paddingVertical: 3,
    borderRadius: rayons.rond,
  },
  pillTexte: {
    fontSize: 11.5,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  pillDescription: {
    fontSize: 12.5,
    color: couleurs.blanc,
    opacity: 0.9,
    flexShrink: 1,
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
    marginBottom: espacements.md,
  },
  titreCarteActive: {
    fontSize: 14,
    fontWeight: '800',
    color: couleurs.texte,
  },
  lienDetail: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
  },
  texteLienDetail: {
    fontSize: 12.5,
    fontWeight: '700',
    color: couleurs.rouge,
  },
  corpsBouteilleActive: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
  },
  infoBlocActive: {
    flex: 1,
    minWidth: 0,
  },
  chipMarque: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  pointMarque: {
    width: 9,
    height: 9,
    borderRadius: rayons.rond,
  },
  texteChipMarque: {
    fontSize: 12,
    fontWeight: '700',
    color: couleurs.texteDoux,
    flexShrink: 1,
  },
  ligneNiveauActive: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: espacements.sm,
    marginTop: espacements.sm,
    marginBottom: espacements.xs,
  },
  pctNiveauActive: {
    fontSize: 30,
    fontWeight: '800',
    color: couleurs.texte,
    letterSpacing: -1,
  },
  barreNiveauConteneur: {
    marginTop: espacements.xs,
  },
  texteEstimation: {
    fontSize: 12,
    color: couleurs.ambre,
    marginTop: espacements.xs,
    fontWeight: '600',
  },
  metaLigne: {
    flexDirection: 'row',
    gap: espacements.md,
    marginTop: espacements.md,
  },
  metaCol: {
    flex: 1,
    minWidth: 0,
  },
  metaCle: {
    fontSize: 11,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  metaValeur: {
    fontSize: 14,
    fontWeight: '800',
    color: couleurs.texte,
    marginTop: 2,
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
    width: 120,
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
    fontWeight: '700',
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  pourcentMiniature: {
    fontSize: 16,
    fontWeight: '800',
  },
});
