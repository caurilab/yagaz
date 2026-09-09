/**
 * Tableau de bord Analyse du foyer (doc 13 §2) : consommation, dépenses,
 * recharges, répartition (donut), jours de cuisine (calendrier heatmap),
 * projection prochaine recharge, série de consommation (barres).
 */
import { useCallback, useEffect, useState } from 'react';
import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BandeauSync } from '../../components/BandeauSync';
import { ChargementYagaz } from '../../components/ChargementYagaz';
import { Icone } from '../../components/icones';
import { BarresConsommation } from '../../components/graphiques/BarresConsommation';
import { CalendrierHeatmap } from '../../components/graphiques/CalendrierHeatmap';
import { Donut, LegendeDonut } from '../../components/graphiques/Donut';
import * as api from '../../api/endpoints';
import { analyseDemoParPeriode, executerAvecSource, insightsDemo } from '../../api/demo';
import { useDonnees } from '../../data/DonneesContext';
import type { StatutSync } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterMontantFcfa } from '../../utils/date';
import type { Analyse, PeriodeAnalyse } from '../../api/types';

const PERIODES: { valeur: PeriodeAnalyse; libelle: string }[] = [
  { valeur: 'semaine', libelle: 'Semaine' },
  { valeur: 'mois', libelle: 'Mois' },
  { valeur: 'annee', libelle: 'Année' },
];

const LIBELLES_PERIODE: Record<PeriodeAnalyse, string> = {
  semaine: 'cette semaine',
  mois: 'ce mois-ci',
  annee: 'cette année',
};

export default function EcranAnalyse() {
  const { siteActif } = useDonnees();
  const [periode, setPeriode] = useState<PeriodeAnalyse>('mois');
  const [analyse, setAnalyse] = useState<Analyse | null>(null);
  const [conseils, setConseils] = useState<string | null>(null);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const [rafraichissement, setRafraichissement] = useState(false);

  const charger = useCallback(async () => {
    const params = { site_uuid: siteActif?.uuid, periode };
    const { data, source } = await executerAvecSource(
      () => api.analyse(params).then((r) => r.data),
      analyseDemoParPeriode[periode]
    );
    setAnalyse(data);
    setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');

    // Conseils IA (ADR 0013) - best-effort, jamais bloquant pour l'analyse.
    try {
      const { data: ins } = await executerAvecSource(
        () => api.analyseInsights(params).then((r) => r.data),
        insightsDemo(periode)
      );
      setConseils(ins.insights);
    } catch {
      setConseils(null);
    }
  }, [siteActif?.uuid, periode]);

  useEffect(() => {
    let annule = false;
    setChargementInitial(true);
    charger()
      .catch(() => {
        if (!annule) setStatutSync('hors_ligne');
      })
      .finally(() => {
        if (!annule) setChargementInitial(false);
      });
    return () => {
      annule = true;
    };
  }, [charger]);

  async function rafraichir() {
    setRafraichissement(true);
    try {
      await charger();
    } catch {
      setStatutSync('hors_ligne');
    } finally {
      setRafraichissement(false);
    }
  }

  const libellePeriode = LIBELLES_PERIODE[periode];

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <View style={styles.ligneHaut}>
            <Pressable
              onPress={() => router.back()}
              hitSlop={12}
              style={styles.boutonRetour}
              accessibilityRole="button"
              accessibilityLabel="Retour">
              <View style={styles.chevronRetour}>
                <Icone nom="chevron" taille={22} couleur={couleurs.blanc} />
              </View>
            </Pressable>
            <View style={styles.ligneMarque}>
              <View style={styles.badgeMarque}>
                <Icone nom="analyse" taille={18} couleur={couleurs.blanc} />
              </View>
              <Text style={styles.texteMarque}>Analyse</Text>
            </View>
          </View>

          {analyse ? (
            <>
              <Text style={styles.texteSalut} numberOfLines={1}>
                Ta consommation, {libellePeriode}
              </Text>
              <View style={styles.ligneHero}>
                <Text style={styles.heroChiffre}>{analyse.consommation_kg}</Text>
                <Text style={styles.heroUnite}>kg consommés</Text>
              </View>
            </>
          ) : (
            <Text style={styles.texteSalut} numberOfLines={1}>
              Ta consommation, {libellePeriode}
            </Text>
          )}
        </SafeAreaView>
      </LinearGradient>

      <ScrollView
        style={styles.zoneContenu}
        contentContainerStyle={styles.contenu}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={rafraichissement} onRefresh={rafraichir} tintColor={couleurs.rouge} />
        }>
        <View style={styles.selecteurPeriode}>
          {PERIODES.map((p) => (
            <Pressable
              key={p.valeur}
              style={[styles.chipPeriode, periode === p.valeur && styles.chipPeriodeActif]}
              onPress={() => setPeriode(p.valeur)}
              accessibilityRole="button">
              <Text style={[styles.chipPeriodeTexte, periode === p.valeur && styles.chipPeriodeTexteActif]}>
                {p.libelle}
              </Text>
            </Pressable>
          ))}
        </View>

        {statutSync === 'hors_ligne' ? (
          <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
        ) : null}

        {chargementInitial ? (
          <View style={styles.chargement}>
            <ChargementYagaz texte="Chargement de votre analyse..." />
          </View>
        ) : !analyse ? (
          <View style={styles.chargement}>
            <View style={styles.iconeVide}>
              <Icone nom="analyse" taille={30} couleur={couleurs.grisNeutre} />
            </View>
            <Text style={styles.texteVide}>Aucune analyse disponible.</Text>
          </View>
        ) : (
          <>
            {conseils ? (
              <View style={styles.carteConseils}>
                <View style={styles.ligneConseils}>
                  <Icone nom="eclair" taille={16} couleur={couleurs.degradeFin} />
                  <Text style={styles.titreConseils}>Conseils</Text>
                </View>
                <Text style={styles.texteConseils}>{conseils}</Text>
              </View>
            ) : null}

            <View style={styles.grilleStats}>
              <CarteStat
                libelle="Consommation"
                valeur={`${analyse.consommation_kg} kg`}
                tendancePct={analyse.consommation_tendance_pct}
              />
              <CarteStat
                libelle="Dépense"
                valeur={formaterMontantFcfa(analyse.depense_fcfa)}
                tendancePct={analyse.depense_tendance_pct}
              />
              <CarteStat
                libelle="Recharges"
                valeur={String(analyse.recharges.nombre)}
                sousValeur={
                  analyse.recharges.nombre > 0
                    ? `${formaterMontantFcfa(analyse.recharges.cout_moyen_fcfa)} en moyenne`
                    : 'Aucune sur la période'
                }
              />
              <CarteStat
                libelle="Jours de cuisine"
                valeur={String(analyse.jours_cuisine.nombre)}
                sousValeur={`Autonomie moy. ${Math.round(analyse.autonomie_moyenne_h)} h`}
              />
            </View>

            <View style={styles.carte}>
              <Text style={styles.sectionTitre}>Répartition par bouteille</Text>
              {analyse.repartition.par_bouteille.length > 0 ? (
                <View style={styles.ligneDonut}>
                  <Donut
                    donnees={analyse.repartition.par_bouteille}
                    libelleCentre={`${analyse.consommation_kg} kg`}
                    sousLibelleCentre="au total"
                  />
                  <LegendeDonut
                    donnees={analyse.repartition.par_bouteille}
                    formaterValeur={(v) => `${v} kg`}
                    iconeBouteille
                  />
                </View>
              ) : (
                <View style={styles.videSection}>
                  <Icone nom="camembert" taille={26} couleur={couleurs.grisNeutre} />
                  <Text style={styles.texteVide}>Pas de répartition sur cette période.</Text>
                </View>
              )}
            </View>

            <View style={[styles.carte, styles.carteProjection]}>
              <Icone nom="calendrier" taille={22} couleur={couleurs.rouge} />
              <View style={styles.texteProjection}>
                <Text style={styles.titreProjection}>Prochaine recharge estimée</Text>
                <Text style={styles.chiffreProjection}>
                  dans ~{Math.round(analyse.projection_prochaine_recharge_jours)} jours
                </Text>
                <Text style={styles.texteSecondaireProjection}>Au rythme de consommation observé.</Text>
              </View>
            </View>

            <View style={styles.carte}>
              <Text style={styles.sectionTitre}>Jours de cuisine</Text>
              <CalendrierHeatmap serie={analyse.jours_cuisine.serie_journaliere} />
            </View>

            <View style={styles.carte}>
              <Text style={styles.sectionTitre}>Consommation dans le temps</Text>
              <BarresConsommation serie={analyse.serie_consommation} />
            </View>
          </>
        )}
      </ScrollView>
    </View>
  );
}

function CarteStat({
  libelle,
  valeur,
  sousValeur,
  tendancePct,
}: {
  libelle: string;
  valeur: string;
  sousValeur?: string;
  tendancePct?: number;
}) {
  return (
    <View style={styles.carteStat}>
      <Text style={styles.libelleStat}>{libelle}</Text>
      <Text style={styles.chiffreStat} numberOfLines={1}>
        {valeur}
      </Text>
      {sousValeur ? (
        <Text style={styles.sousValeurStat} numberOfLines={1}>
          {sousValeur}
        </Text>
      ) : null}
      {tendancePct != null ? <Tendance pct={tendancePct} /> : null}
    </View>
  );
}

function Tendance({ pct }: { pct: number }) {
  if (pct === 0) {
    return (
      <View style={[styles.pucePct, styles.pucePctNeutre]}>
        <Text style={[styles.textePucePct, styles.textePucePctNeutre]}>Stable</Text>
      </View>
    );
  }
  const hausse = pct > 0;
  return (
    <View style={[styles.pucePct, hausse ? styles.pucePctHausse : styles.pucePctBaisse]}>
      <Icone nom={hausse ? 'flecheHaut' : 'flecheBas'} taille={10} couleur={hausse ? couleurs.ambre : couleurs.vertOk} />
      <Text style={[styles.textePucePct, { color: hausse ? couleurs.ambre : couleurs.vertOk }]}>
        {Math.abs(pct)} %
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
  ligneHaut: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    marginTop: espacements.sm,
    marginBottom: espacements.lg,
  },
  boutonRetour: {
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
  ligneMarque: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
  },
  badgeMarque: {
    width: 34,
    height: 34,
    borderRadius: rayons.md - 4,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.18)',
    borderWidth: 1.5,
    borderColor: 'rgba(255,255,255,0.85)',
  },
  texteMarque: {
    fontSize: 17,
    fontWeight: '800',
    color: couleurs.blanc,
    letterSpacing: -0.3,
  },
  texteSalut: {
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.blanc,
    opacity: 0.9,
  },
  ligneHero: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: espacements.sm,
    marginTop: espacements.xs,
  },
  heroChiffre: {
    fontSize: 44,
    fontWeight: '800',
    color: couleurs.blanc,
    lineHeight: 46,
    letterSpacing: -1,
  },
  heroUnite: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.blanc,
    opacity: 0.92,
    paddingBottom: espacements.xs,
  },
  zoneContenu: {
    flex: 1,
  },
  contenu: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  selecteurPeriode: {
    flexDirection: 'row',
    gap: espacements.sm,
    marginBottom: espacements.md,
  },
  chipPeriode: {
    flex: 1,
    paddingVertical: espacements.sm + 1,
    alignItems: 'center',
    borderRadius: rayons.md,
    backgroundColor: couleurs.carte,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 3 },
    elevation: 2,
  },
  chipPeriodeActif: {
    backgroundColor: couleurs.rouge,
  },
  chipPeriodeTexte: {
    fontSize: 12.5,
    fontWeight: '700',
    color: couleurs.texteDoux,
  },
  chipPeriodeTexteActif: {
    color: couleurs.blanc,
  },
  chargement: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
  },
  iconeVide: {
    marginBottom: espacements.sm,
  },
  videSection: {
    alignItems: 'center',
    gap: espacements.xs,
    paddingVertical: espacements.md,
  },
  texteVide: {
    color: couleurs.texteDoux,
    fontSize: 13,
  },
  grilleStats: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espacements.md,
    marginBottom: espacements.md,
  },
  carteStat: {
    flexBasis: '47%',
    flexGrow: 1,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.md,
    gap: 4,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  libelleStat: {
    fontSize: 11.5,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  chiffreStat: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
    letterSpacing: -0.5,
    marginTop: 2,
  },
  sousValeurStat: {
    fontSize: 11.5,
    color: couleurs.texteDoux,
  },
  pucePct: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    alignSelf: 'flex-start',
    paddingHorizontal: espacements.sm,
    paddingVertical: 3,
    borderRadius: rayons.rond,
    marginTop: 2,
  },
  pucePctHausse: {
    backgroundColor: '#FDF2E2',
  },
  pucePctBaisse: {
    backgroundColor: '#E7F8EF',
  },
  pucePctNeutre: {
    backgroundColor: couleurs.fond,
  },
  textePucePct: {
    fontSize: 11,
    fontWeight: '800',
  },
  textePucePctNeutre: {
    color: couleurs.texteDoux,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    marginBottom: espacements.md,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  carteConseils: {
    backgroundColor: couleurs.rougeClair,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    marginBottom: espacements.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
  },
  ligneConseils: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    marginBottom: espacements.sm,
  },
  titreConseils: {
    fontSize: 12.5,
    fontWeight: '800',
    color: couleurs.degradeFin,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  texteConseils: {
    fontSize: 13.5,
    lineHeight: 20,
    color: couleurs.texte,
  },
  sectionTitre: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.md,
  },
  ligneDonut: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.lg,
  },
  carteProjection: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
  },
  texteProjection: {
    flex: 1,
    gap: 2,
  },
  titreProjection: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  chiffreProjection: {
    fontSize: 22,
    fontWeight: '800',
    color: couleurs.texte,
  },
  texteSecondaireProjection: {
    fontSize: 12,
    color: couleurs.texteDoux,
  },
});
