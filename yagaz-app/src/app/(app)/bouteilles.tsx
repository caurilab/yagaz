import { useState } from 'react';
import { router } from 'expo-router';
import { Alert, FlatList, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeEtat } from '../../components/BadgeEtat';
import { Bouton } from '../../components/Bouton';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterAutonomie } from '../../utils/niveau';
import { couleurPourFormat } from '../../utils/marque';
import type { Bouteille } from '../../api/types';

export default function EcranBouteilles() {
  const { siteActif, bouteilles, marques, activerBouteille } = useDonnees();
  const [uuidEnCours, setUuidEnCours] = useState<string | null>(null);

  async function definirActive(bouteille: Bouteille) {
    setUuidEnCours(bouteille.uuid);
    try {
      await activerBouteille(bouteille.uuid);
    } catch {
      Alert.alert('Action impossible', "Impossible de définir cette bouteille comme active pour l'instant.");
    } finally {
      setUuidEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <View style={styles.entete}>
        <Text style={styles.titre}>Bouteilles</Text>
        {siteActif ? <Text style={styles.sousTitre}>{siteActif.nom}</Text> : null}
      </View>

      <FlatList
        data={bouteilles}
        keyExtractor={(b) => b.uuid}
        contentContainerStyle={styles.liste}
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucune bouteille pour ce site pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={[styles.carte, item.role_bouteille === 'active' && styles.carteActive]}>
            <View style={styles.ligneEntete}>
              <View style={styles.roleZone}>
                <Text style={styles.role}>{item.role_bouteille === 'active' ? 'Active' : 'Secours'}</Text>
                <View style={styles.ligneFormat}>
                  <View style={[styles.pastille, { backgroundColor: couleurPourFormat(item.format, marques) }]} />
                  <Text style={styles.format}>
                    {item.format.code} - {item.format.marque}
                  </Text>
                </View>
              </View>
              <BadgeEtat etat={item.niveau.etat} />
            </View>

            <View style={styles.ligneChiffres}>
              <Text style={styles.autonomie}>{formaterAutonomie(item.niveau.autonomie_heures)}</Text>
              <Text style={styles.pourcent}>{item.niveau.niveau_pct} %</Text>
            </View>

            {item.niveau.estimation ? (
              <Text style={styles.texteEstimation}>Estimation en cours d'affinage</Text>
            ) : null}
            {!item.niveau.frais ? <Text style={styles.texteHorsLigne}>Dernière valeur connue</Text> : null}

            <View style={styles.actions}>
              {item.role_bouteille !== 'active' ? (
                <Bouton
                  titre="Définir comme active"
                  variante="contour"
                  enCours={uuidEnCours === item.uuid}
                  onPress={() => definirActive(item)}
                  style={styles.boutonAction}
                />
              ) : null}
              <Bouton
                titre="Détails"
                variante="discret"
                onPress={() => router.push(`/bouteille/${item.uuid}`)}
                style={styles.boutonAction}
              />
            </View>
          </View>
        )}
      />

      <View style={styles.piedDePage}>
        <Bouton titre="Enregistrer une bouteille" onPress={() => router.push('/enregistrer')} />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  entete: {
    paddingHorizontal: espacements.lg,
    paddingTop: espacements.md,
  },
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
  },
  sousTitre: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  liste: {
    padding: espacements.lg,
    gap: espacements.md,
  },
  vide: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
  },
  texteVide: {
    color: couleurs.texteDoux,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    marginBottom: espacements.md,
  },
  carteActive: {
    borderColor: couleurs.rouge,
    borderWidth: 2,
  },
  ligneEntete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: espacements.sm,
  },
  roleZone: {
    flex: 1,
  },
  role: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
  },
  ligneFormat: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    marginTop: 2,
  },
  pastille: {
    width: 9,
    height: 9,
    borderRadius: 5,
  },
  format: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
  ligneChiffres: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: espacements.sm,
    marginTop: espacements.md,
  },
  autonomie: {
    fontSize: 30,
    fontWeight: '800',
    color: couleurs.texte,
  },
  pourcent: {
    fontSize: 15,
    color: couleurs.texteDoux,
  },
  texteEstimation: {
    fontSize: 12,
    color: couleurs.ambre,
    marginTop: espacements.xs,
    fontWeight: '600',
  },
  texteHorsLigne: {
    fontSize: 12,
    color: couleurs.rouge,
    marginTop: espacements.xs,
    fontWeight: '600',
  },
  actions: {
    flexDirection: 'row',
    gap: espacements.sm,
    marginTop: espacements.md,
  },
  boutonAction: {
    flex: 1,
  },
  piedDePage: {
    padding: espacements.lg,
    borderTopWidth: 1,
    borderTopColor: couleurs.bordure,
    backgroundColor: couleurs.fond,
  },
});
