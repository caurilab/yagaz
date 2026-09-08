import { useState } from 'react';
import { useLocalSearchParams } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeEtat } from '../../../components/BadgeEtat';
import { BandeauSync } from '../../../components/BandeauSync';
import { Bouton } from '../../../components/Bouton';
import { useDonnees } from '../../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../../theme/couleurs';
import { formaterAutonomie } from '../../../utils/niveau';

const PAS_SEUIL = 5;
const SEUIL_MIN = 5;
const SEUIL_MAX = 50;

export default function EcranDetailBouteille() {
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const { bouteilles, majSeuilBouteille } = useDonnees();
  const bouteille = bouteilles.find((b) => b.uuid === uuid);

  const [seuil, setSeuil] = useState(bouteille?.seuil_bas_pct ?? 20);
  const [enCours, setEnCours] = useState(false);

  if (!bouteille) {
    return (
      <SafeAreaView style={styles.conteneur}>
        <View style={styles.centre}>
          <Text style={styles.texteVide}>Bouteille introuvable.</Text>
        </View>
      </SafeAreaView>
    );
  }

  const { niveau } = bouteille;

  async function enregistrerSeuil() {
    setEnCours(true);
    try {
      await majSeuilBouteille(bouteille!.uuid, seuil);
      Alert.alert('Seuil mis à jour', `Alerte déclenchée sous ${seuil} %.`);
    } catch {
      Alert.alert('Erreur', 'Impossible de mettre à jour le seuil.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.contenu}>
        <View style={styles.enTete}>
          <Text style={styles.format}>
            {bouteille.format.code} - {bouteille.format.marque}
          </Text>
          <BadgeEtat etat={niveau.etat} />
        </View>

        {!niveau.frais ? <BandeauSync texte="Dernière valeur connue - hors ligne" variante="alerte" /> : null}

        <View style={styles.carte}>
          <Text style={styles.chiffreAutonomie}>{formaterAutonomie(niveau.autonomie_heures)}</Text>
          <Text style={styles.libelle}>d'autonomie restante</Text>

          <View style={styles.barreNiveau}>
            <View style={[styles.barreNiveauRemplie, { width: `${Math.max(0, Math.min(100, niveau.niveau_pct))}%` }]} />
          </View>
          <Text style={styles.texteSecondaire}>Niveau : {niveau.niveau_pct} % ({niveau.gaz_g} g)</Text>
          {niveau.estimation ? (
            <Text style={styles.texteEstimation}>Estimation en cours d'affinage</Text>
          ) : null}
        </View>

        <Text style={styles.sectionTitre}>Tare</Text>
        <View style={styles.carteInfo}>
          <Text style={styles.texteInfo}>
            {bouteille.tare_g != null ? `${bouteille.tare_g} g` : 'Non renseignée (valeur nominale utilisée)'}
          </Text>
          <Text style={[styles.statutTare, bouteille.tare_fiable ? styles.statutFiable : styles.statutEnCours]}>
            {bouteille.tare_fiable ? 'Fiable' : "En cours d'affinage"}
          </Text>
        </View>

        <Text style={styles.sectionTitre}>Seuil d'alerte bas</Text>
        <View style={styles.carteInfo}>
          <View style={styles.ligneSeuil}>
            <Bouton
              titre="-"
              variante="contour"
              onPress={() => setSeuil((s) => Math.max(SEUIL_MIN, s - PAS_SEUIL))}
              style={styles.boutonSeuil}
            />
            <Text style={styles.valeurSeuil}>{seuil} %</Text>
            <Bouton
              titre="+"
              variante="contour"
              onPress={() => setSeuil((s) => Math.min(SEUIL_MAX, s + PAS_SEUIL))}
              style={styles.boutonSeuil}
            />
          </View>
          <Text style={styles.texteRassurant}>
            Une alerte sera envoyée dès que le niveau descend sous ce seuil.
          </Text>
          <Bouton
            titre="Enregistrer le seuil"
            onPress={enregistrerSeuil}
            enCours={enCours}
            desactive={seuil === bouteille.seuil_bas_pct}
            style={styles.boutonEnregistrer}
          />
        </View>

        <Text style={styles.sectionTitre}>Courbe de niveau</Text>
        <View style={styles.placeholderCourbe}>
          <Text style={styles.texteVide}>Courbe de niveau bientôt disponible</Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  centre: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  contenu: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  enTete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: espacements.md,
  },
  format: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    alignItems: 'center',
    marginBottom: espacements.lg,
  },
  chiffreAutonomie: {
    fontSize: 56,
    fontWeight: '800',
    color: couleurs.texte,
  },
  libelle: {
    fontSize: 15,
    color: couleurs.texteDoux,
    marginBottom: espacements.md,
  },
  barreNiveau: {
    alignSelf: 'stretch',
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
  texteSecondaire: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: espacements.sm,
  },
  texteEstimation: {
    fontSize: 12,
    color: couleurs.ambre,
    marginTop: espacements.xs,
    fontWeight: '600',
  },
  sectionTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  carteInfo: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.md,
    padding: espacements.lg,
    marginBottom: espacements.lg,
    gap: espacements.sm,
  },
  texteInfo: {
    fontSize: 15,
    color: couleurs.texte,
  },
  statutTare: {
    fontSize: 13,
    fontWeight: '700',
    alignSelf: 'flex-start',
  },
  statutFiable: {
    color: couleurs.vertOk,
  },
  statutEnCours: {
    color: couleurs.ambre,
  },
  ligneSeuil: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.lg,
  },
  boutonSeuil: {
    width: 56,
    minHeight: 56,
    paddingHorizontal: 0,
  },
  valeurSeuil: {
    fontSize: 28,
    fontWeight: '800',
    color: couleurs.texte,
    minWidth: 80,
    textAlign: 'center',
  },
  texteRassurant: {
    fontSize: 13,
    color: couleurs.texteDoux,
    textAlign: 'center',
  },
  boutonEnregistrer: {
    marginTop: espacements.sm,
  },
  placeholderCourbe: {
    height: 140,
    borderRadius: rayons.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: couleurs.carte,
  },
  texteVide: {
    color: couleurs.texteDoux,
    fontSize: 14,
  },
});
