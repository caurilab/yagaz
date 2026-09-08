/**
 * Tableau de bord Analyse du foyer (doc 13 §2) : consommation, dépenses,
 * recharges, répartition (donut), jours de cuisine (calendrier heatmap),
 * projection prochaine recharge, série de consommation (barres).
 */
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';

import { BandeauSync } from '../../components/BandeauSync';
import { BarresConsommation } from '../../components/graphiques/BarresConsommation';
import { CalendrierHeatmap } from '../../components/graphiques/CalendrierHeatmap';
import { Donut, LegendeDonut } from '../../components/graphiques/Donut';
import * as api from '../../api/endpoints';
import { analyseDemoParPeriode, executerAvecSource } from '../../api/demo';
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

export default function EcranAnalyse() {
  const { siteActif } = useDonnees();
  const [periode, setPeriode] = useState<PeriodeAnalyse>('mois');
  const [analyse, setAnalyse] = useState<Analyse | null>(null);
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

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <ScrollView
        contentContainerStyle={styles.contenu}
        refreshControl={
          <RefreshControl refreshing={rafraichissement} onRefresh={rafraichir} tintColor={couleurs.rouge} />
        }>
        <Text style={styles.titre}>Analyse</Text>

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
            <ActivityIndicator color={couleurs.rouge} size="large" />
          </View>
        ) : !analyse ? (
          <View style={styles.chargement}>
            <Text style={styles.texteVide}>Aucune analyse disponible.</Text>
          </View>
        ) : (
          <>
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
                  />
                </View>
              ) : (
                <Text style={styles.texteVide}>Pas de répartition sur cette période.</Text>
              )}
            </View>

            <View style={[styles.carte, styles.carteProjection]}>
              <Ionicons name="calendar-outline" size={22} color={couleurs.rouge} />
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
    </SafeAreaView>
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
      {tendancePct != null ? <Tendance pct={tendancePct} /> : null}
      {sousValeur ? (
        <Text style={styles.sousValeurStat} numberOfLines={1}>
          {sousValeur}
        </Text>
      ) : null}
    </View>
  );
}

function Tendance({ pct }: { pct: number }) {
  if (pct === 0) {
    return <Text style={styles.tendanceNeutre}>Stable</Text>;
  }
  const hausse = pct > 0;
  return (
    <View style={styles.ligneTendance}>
      <Ionicons
        name={hausse ? 'arrow-up' : 'arrow-down'}
        size={12}
        color={hausse ? couleurs.ambre : couleurs.vertOk}
      />
      <Text style={[styles.texteTendance, { color: hausse ? couleurs.ambre : couleurs.vertOk }]}>
        {Math.abs(pct)} % vs période précédente
      </Text>
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
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.md,
  },
  selecteurPeriode: {
    flexDirection: 'row',
    gap: espacements.sm,
    marginBottom: espacements.md,
  },
  chipPeriode: {
    flex: 1,
    paddingVertical: espacements.sm,
    alignItems: 'center',
    borderRadius: rayons.rond,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    backgroundColor: couleurs.carte,
  },
  chipPeriodeActif: {
    backgroundColor: couleurs.rouge,
    borderColor: couleurs.rouge,
  },
  chipPeriodeTexte: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.texte,
  },
  chipPeriodeTexteActif: {
    color: couleurs.blanc,
  },
  chargement: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
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
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.md,
    gap: 4,
  },
  libelleStat: {
    fontSize: 12,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  chiffreStat: {
    fontSize: 24,
    fontWeight: '800',
    color: couleurs.texte,
  },
  sousValeurStat: {
    fontSize: 11,
    color: couleurs.texteDoux,
  },
  ligneTendance: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  texteTendance: {
    fontSize: 11,
    fontWeight: '700',
  },
  tendanceNeutre: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.texteDoux,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.lg,
    marginBottom: espacements.md,
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
