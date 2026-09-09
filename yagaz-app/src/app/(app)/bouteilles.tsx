import { useState } from 'react';
import { router } from 'expo-router';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BadgeEtat } from '../../components/BadgeEtat';
import { Bouton } from '../../components/Bouton';
import { BouteilleGaz } from '../../components/BouteilleGaz';
import { Icone } from '../../components/icones';
import { useDialogue } from '../../data/DialogueContext';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterAutonomie } from '../../utils/niveau';
import { couleurPourFormat } from '../../utils/marque';
import type { Bouteille } from '../../api/types';

export default function EcranBouteilles() {
  const { siteActif, bouteilles, marques, activerBouteille } = useDonnees();
  const { alerter } = useDialogue();
  const [uuidEnCours, setUuidEnCours] = useState<string | null>(null);

  async function definirActive(bouteille: Bouteille) {
    setUuidEnCours(bouteille.uuid);
    try {
      await activerBouteille(bouteille.uuid);
    } catch {
      void alerter({ titre: 'Action impossible', message: "Impossible de définir cette bouteille comme active pour l'instant." });
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
            <View style={styles.iconeVide}>
              <Icone nom="flamme" taille={32} couleur={couleurs.grisNeutre} />
            </View>
            <Text style={styles.texteVide}>Aucune bouteille pour ce site pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={styles.carte}>
            <View style={styles.carteLigne}>
              <BouteilleGaz
                couleur={couleurPourFormat(item.format, marques)}
                code={item.format.code}
                marqueNom={item.format.marque}
                niveauPct={item.niveau.niveau_pct}
                taille={86}
                reflet={false}
              />
              <View style={styles.carteContenu}>
                <View style={styles.ligneEntete}>
                  <View style={styles.roleZone}>
                    <View style={styles.ligneRole}>
                      {item.role_bouteille === 'active' ? <View style={styles.pastilleActive} /> : null}
                      <Text style={styles.role}>{item.role_bouteille === 'active' ? 'Active' : 'Secours'}</Text>
                    </View>
                    <Text style={styles.format}>{item.format.marque}</Text>
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
              </View>
            </View>

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
  iconeVide: {
    marginBottom: espacements.sm,
  },
  texteVide: {
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
  carteLigne: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
  },
  carteContenu: {
    flex: 1,
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
  ligneRole: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  pastilleActive: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: couleurs.rouge,
  },
  role: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
  },
  format: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
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
