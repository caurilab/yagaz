/**
 * Analyse détaillée (drill-down) de la température de la cuisine (ADR 0011,
 * doc 13 §3) : ouvert en tapant l'encart température (accueil, détail
 * bouteille). Température courante + cuisson, courbe horaire, histogramme
 * des cuissons par heure, heure de pointe + période dominante, fréquence.
 * Style élégant orange, cartes blanches sans bordure colorée.
 */
import { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BandeauSync } from '../../../components/BandeauSync';
import { CourbeTemperatureHoraire } from '../../../components/graphiques/CourbeTemperatureHoraire';
import { Icone } from '../../../components/icones';
import * as api from '../../../api/endpoints';
import { executerAvecSource, temperatureAnalyseDemoParPeriode } from '../../../api/demo';
import { useDonnees } from '../../../data/DonneesContext';
import type { StatutSync } from '../../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../../theme/couleurs';
import { SEUIL_TEMPERATURE_ELEVEE_C } from '../../../components/EncartTemperature';
import type { PeriodeTemperature, TemperatureAnalyse } from '../../../api/types';

const PERIODES: { valeur: PeriodeTemperature; libelle: string }[] = [
  { valeur: 'jour', libelle: 'Jour' },
  { valeur: 'semaine', libelle: 'Semaine' },
  { valeur: 'mois', libelle: 'Mois' },
];

function libelleHeure(heure: number): string {
  return `${heure}h`;
}

export default function EcranTemperatureAnalyse() {
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const { sites } = useDonnees();
  const site = sites.find((s) => s.uuid === uuid);

  const [periode, setPeriode] = useState<PeriodeTemperature>('jour');
  const [analyse, setAnalyse] = useState<TemperatureAnalyse | null>(null);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const [rafraichissement, setRafraichissement] = useState(false);

  const charger = useCallback(async () => {
    if (!uuid) return;
    const { data, source } = await executerAvecSource(
      () => api.temperatureAnalyse(uuid, periode),
      temperatureAnalyseDemoParPeriode[periode]
    );
    setAnalyse(data);
    setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');
  }, [uuid, periode]);

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

  const elevee = analyse != null && analyse.temp_courante_c >= SEUIL_TEMPERATURE_ELEVEE_C;
  const maxCuissons = analyse ? Math.max(...analyse.histogramme_cuissons.map((p) => p.nb_cuissons), 1) : 1;

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView
        contentContainerStyle={styles.contenu}
        refreshControl={
          <RefreshControl refreshing={rafraichissement} onRefresh={rafraichir} tintColor={couleurs.rouge} />
        }>
        <Text style={styles.titre}>Température</Text>
        {site ? <Text style={styles.sousTitre}>{site.nom}</Text> : null}

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
            <Icone nom="thermometre" taille={30} couleur={couleurs.grisNeutre} />
            <Text style={styles.texteVide}>Aucune donnée de température disponible.</Text>
          </View>
        ) : (
          <>
            <View style={[styles.carte, styles.carteTemperature]}>
              <View style={styles.ligneEnteteTemperature}>
                <Icone nom="thermometre" taille={20} couleur={elevee ? couleurs.danger : couleurs.rouge} />
                <Text style={styles.libelleTemperature}>Température courante</Text>
                {analyse.cuisson_en_cours ? (
                  <View style={styles.badgeCuisson}>
                    <Icone nom="flamme" taille={12} couleur={couleurs.blanc} />
                    <Text style={styles.texteBadgeCuisson}>Cuisson en cours</Text>
                  </View>
                ) : null}
              </View>
              <Text style={[styles.chiffreTemperature, elevee && styles.chiffreAlerte]}>
                {Math.round(analyse.temp_courante_c)} °C
              </Text>
              {elevee ? <Text style={styles.texteAlerte}>Température élevée - vérifiez la cuisine</Text> : null}
            </View>

            <View style={styles.carte}>
              <Text style={styles.sectionTitre}>Courbe horaire - température moyenne</Text>
              <CourbeTemperatureHoraire points={analyse.courbe_horaire} />
              <View style={styles.ligneRepereHeures}>
                <Text style={styles.texteRepereHeure}>0h</Text>
                <Text style={styles.texteRepereHeure}>6h</Text>
                <Text style={styles.texteRepereHeure}>12h</Text>
                <Text style={styles.texteRepereHeure}>18h</Text>
                <Text style={styles.texteRepereHeure}>23h</Text>
              </View>
            </View>

            <View style={styles.carte}>
              <Text style={styles.sectionTitre}>Cuissons par heure</Text>
              <View style={styles.histogramme}>
                {analyse.histogramme_cuissons.map((point) => (
                  <View key={point.heure} style={styles.colonneHistogramme}>
                    <View
                      style={[
                        styles.barreHistogramme,
                        {
                          height: Math.max(2, (point.nb_cuissons / maxCuissons) * 64),
                          backgroundColor: point.heure === analyse.heure_pointe ? couleurs.rouge : couleurs.rougeClair,
                        },
                      ]}
                    />
                  </View>
                ))}
              </View>
              <View style={styles.ligneRepereHeures}>
                <Text style={styles.texteRepereHeure}>0h</Text>
                <Text style={styles.texteRepereHeure}>6h</Text>
                <Text style={styles.texteRepereHeure}>12h</Text>
                <Text style={styles.texteRepereHeure}>18h</Text>
                <Text style={styles.texteRepereHeure}>23h</Text>
              </View>
            </View>

            <View style={[styles.carte, styles.carteEnEvidence]}>
              <View style={styles.blocEnEvidence}>
                <Icone nom="flamme" taille={22} couleur={couleurs.rouge} />
                <Text style={styles.libelleEnEvidence}>Heure de pointe</Text>
                <Text style={styles.chiffreEnEvidence}>{libelleHeure(analyse.heure_pointe)}</Text>
              </View>
              <View style={styles.separateurVertical} />
              <View style={styles.blocEnEvidence}>
                <Icone nom="cuisine" taille={22} couleur={couleurs.rouge} />
                <Text style={styles.libelleEnEvidence}>Période dominante</Text>
                <Text style={styles.chiffreEnEvidence} numberOfLines={1}>
                  {analyse.periode_dominante.libelle}
                </Text>
              </View>
            </View>

            <View style={styles.carte}>
              <Text style={styles.sectionTitre}>Fréquence</Text>
              <View style={styles.grilleFrequence}>
                <View style={styles.tuileFrequence}>
                  <Text style={styles.chiffreFrequence}>{analyse.frequence.jours_cuisine}</Text>
                  <Text style={styles.libelleFrequence}>jours de cuisine</Text>
                </View>
                <View style={styles.tuileFrequence}>
                  <Text style={styles.chiffreFrequence}>{analyse.frequence.sessions_par_jour}</Text>
                  <Text style={styles.libelleFrequence}>sessions/jour</Text>
                </View>
                <View style={styles.tuileFrequence}>
                  <Text style={styles.chiffreFrequence}>{analyse.frequence.duree_moyenne_min} min</Text>
                  <Text style={styles.libelleFrequence}>durée moyenne</Text>
                </View>
              </View>
            </View>
          </>
        )}
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
  },
  sousTitre: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginBottom: espacements.md,
  },
  selecteurPeriode: {
    flexDirection: 'row',
    gap: espacements.sm,
    marginBottom: espacements.md,
    marginTop: espacements.sm,
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
    gap: espacements.sm,
  },
  texteVide: {
    color: couleurs.texteDoux,
    fontSize: 13,
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
  carteTemperature: {
    alignItems: 'flex-start',
    gap: espacements.xs,
  },
  ligneEnteteTemperature: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  libelleTemperature: {
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.texteDoux,
    flexShrink: 1,
  },
  badgeCuisson: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: couleurs.rouge,
    borderRadius: rayons.rond,
    paddingHorizontal: espacements.sm,
    paddingVertical: 3,
    marginLeft: 'auto',
  },
  texteBadgeCuisson: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  chiffreTemperature: {
    fontSize: 44,
    fontWeight: '800',
    color: couleurs.texte,
  },
  chiffreAlerte: {
    color: couleurs.danger,
  },
  texteAlerte: {
    fontSize: 12,
    fontWeight: '700',
    color: couleurs.danger,
  },
  sectionTitre: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.md,
  },
  ligneRepereHeures: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: espacements.xs,
  },
  texteRepereHeure: {
    fontSize: 11,
    color: couleurs.texteDoux,
  },
  histogramme: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    height: 68,
    gap: 2,
  },
  colonneHistogramme: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'flex-end',
  },
  barreHistogramme: {
    width: '100%',
    borderRadius: 2,
  },
  carteEnEvidence: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  blocEnEvidence: {
    flex: 1,
    alignItems: 'center',
    gap: 4,
  },
  separateurVertical: {
    width: 1,
    alignSelf: 'stretch',
    backgroundColor: couleurs.bordure,
    marginHorizontal: espacements.md,
  },
  libelleEnEvidence: {
    fontSize: 12,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  chiffreEnEvidence: {
    fontSize: 20,
    fontWeight: '800',
    color: couleurs.texte,
  },
  grilleFrequence: {
    flexDirection: 'row',
    gap: espacements.md,
  },
  tuileFrequence: {
    flex: 1,
    alignItems: 'center',
    gap: 4,
    backgroundColor: couleurs.fond,
    borderRadius: rayons.md,
    paddingVertical: espacements.md,
  },
  chiffreFrequence: {
    fontSize: 20,
    fontWeight: '800',
    color: couleurs.texte,
  },
  libelleFrequence: {
    fontSize: 11,
    color: couleurs.texteDoux,
    textAlign: 'center',
  },
});
