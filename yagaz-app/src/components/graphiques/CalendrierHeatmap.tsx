/**
 * Calendrier heatmap des jours de cuisine (analyse §2), style "streak" orange
 * (cf. `_reference_design/`) : grille de cases colorées selon l'intensité
 * (nombre de sessions du jour), semaines en colonnes - léger, `react-native-svg`.
 */
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import Svg, { Rect } from 'react-native-svg';

import { couleurs, espacements } from '../../../theme/couleurs';
import type { JourCuissonSerie } from '../../api/types';

const TAILLE_CASE = 13;
const ESPACE_CASE = 3;
const PAS = TAILLE_CASE + ESPACE_CASE;

function couleurIntensite(sessions: number): string {
  if (sessions <= 0) return couleurs.bordure;
  if (sessions === 1) return couleurs.rougeClair;
  if (sessions === 2) return couleurs.degradeDebut;
  return couleurs.rouge;
}

export function CalendrierHeatmap({ serie }: { serie: JourCuissonSerie[] }) {
  if (serie.length === 0) {
    return (
      <View style={styles.vide}>
        <Text style={styles.texteVide}>Pas encore de données de cuisson sur cette période.</Text>
      </View>
    );
  }

  // Aligne la première case sur le bon jour de semaine (lundi = colonne 0),
  // comme un calendrier de type "streak".
  const premiereDate = new Date(`${serie[0].date}T00:00:00`);
  const jourSemaine = premiereDate.getDay();
  // Garde anti-crash : une date mal formée donnerait `NaN`, et `Array(NaN)`
  // lève une RangeError (l'app se ferme). On retombe alors sur 0 (pas de décalage).
  const decalage = Number.isFinite(jourSemaine) ? (jourSemaine + 6) % 7 : 0;
  const cases: (JourCuissonSerie | null)[] = [...Array(decalage).fill(null), ...serie];
  const nbColonnes = Math.ceil(cases.length / 7);
  const largeur = nbColonnes * PAS;
  const hauteur = 7 * PAS;

  return (
    <View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.scroll}>
        <Svg width={largeur} height={hauteur}>
          {cases.map((jour, index) => {
            if (!jour) return null;
            const colonne = Math.floor(index / 7);
            const ligne = index % 7;
            return (
              <Rect
                key={jour.date}
                x={colonne * PAS}
                y={ligne * PAS}
                width={TAILLE_CASE}
                height={TAILLE_CASE}
                rx={4}
                fill={couleurIntensite(jour.sessions)}
              />
            );
          })}
        </Svg>
      </ScrollView>
      <View style={styles.legende}>
        <Text style={styles.texteLegende}>Moins</Text>
        {[0, 1, 2, 3].map((n) => (
          <View key={n} style={[styles.pastilleLegende, { backgroundColor: couleurIntensite(n) }]} />
        ))}
        <Text style={styles.texteLegende}>Plus</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: {
    paddingVertical: espacements.xs,
  },
  vide: {
    paddingVertical: espacements.lg,
    alignItems: 'center',
  },
  texteVide: {
    color: couleurs.texteDoux,
    fontSize: 13,
    textAlign: 'center',
  },
  legende: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginTop: espacements.sm,
  },
  texteLegende: {
    fontSize: 11,
    color: couleurs.texteDoux,
  },
  pastilleLegende: {
    width: 10,
    height: 10,
    borderRadius: 3,
  },
});
