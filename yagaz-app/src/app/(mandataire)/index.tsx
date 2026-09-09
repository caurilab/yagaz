import { useMemo } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';

import { BadgeStatutTournee } from '../../components/BadgeStatut';
import { BandeauSync } from '../../components/BandeauSync';
import { EnteteEcran } from '../../components/EnteteEcran';
import { useMandataire } from '../../data/MandataireContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { TourneeLigne } from '../../api/types';

interface Arret {
  depotUuid: string;
  depotNom: string;
  lignes: TourneeLigne[];
}

function grouperParDepot(lignes: TourneeLigne[]): Arret[] {
  const parDepot = new Map<string, Arret>();
  for (const ligne of lignes) {
    // `depot` peut être null côté API (relation absente) : on ignore la ligne
    // plutôt que de planter sur `ligne.depot.uuid`.
    if (!ligne.depot) continue;
    const existant = parDepot.get(ligne.depot.uuid);
    if (existant) {
      existant.lignes.push(ligne);
    } else {
      parDepot.set(ligne.depot.uuid, { depotUuid: ligne.depot.uuid, depotNom: ligne.depot.nom, lignes: [ligne] });
    }
  }
  return Array.from(parDepot.values());
}

function formaterDateTournee(dateIso: string): string {
  const date = new Date(`${dateIso}T00:00:00`);
  const texte = date.toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: 'long' });
  return texte.charAt(0).toUpperCase() + texte.slice(1);
}

/**
 * Tournée du jour, dépôt par dépôt (doc 11 §1, §4 ; UX §4) : pour chaque
 * arrêt, ce qu'il faut déposer (pleines) et récupérer (vides) par format, et
 * un seul geste large pour faire avancer tout l'arrêt (arrivé -> déposé ->
 * vides récupérés). Le pilotage des tournées (préparation, affectation)
 * reste sur le web - ici, uniquement l'exécution terrain.
 */
export default function EcranTourneeDuJour() {
  const { tourneeDuJour, statutSync, chargementInitial, rafraichir } = useMandataire();

  const arrets = useMemo(() => (tourneeDuJour ? grouperParDepot(tourneeDuJour.lignes) : []), [tourneeDuJour]);

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Tournée du jour" />
      <FlatList
        data={arrets}
        keyExtractor={(a) => a.depotUuid}
        contentContainerStyle={styles.liste}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}
        ListHeaderComponent={
          <>
            {tourneeDuJour ? (
              <View style={styles.entete}>
                <View style={styles.ligneSousTitre}>
                  <Text style={styles.sousTitre}>{formaterDateTournee(tourneeDuJour.date)}</Text>
                  <BadgeStatutTournee statut={tourneeDuJour.statut} />
                </View>
                {tourneeDuJour.livreur_nom ? (
                  <Text style={styles.livreur}>Livreur : {tourneeDuJour.livreur_nom}</Text>
                ) : null}
                {arrets.length > 0 ? (
                  <Text style={styles.progression}>
                    {arrets.length} arrêt{arrets.length > 1 ? 's' : ''}
                  </Text>
                ) : null}
              </View>
            ) : null}
            {statutSync === 'hors_ligne' ? (
              <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
            ) : null}
          </>
        }
        ListEmptyComponent={
          chargementInitial ? (
            <View style={styles.chargement}>
              <ActivityIndicator color={couleurs.rouge} size="large" />
              <Text style={styles.texteChargement}>Chargement de la tournée...</Text>
            </View>
          ) : (
            <View style={styles.vide}>
              <Text style={styles.texteVide}>
                {tourneeDuJour
                  ? 'Aucun arrêt dans cette tournée.'
                  : "Aucune tournée validée pour aujourd'hui - le pilotage se fait sur le web."}
              </Text>
            </View>
          )
        }
        renderItem={({ item }) => <CarteArret arret={item} />}
      />
    </View>
  );
}

function CarteArret({ arret }: { arret: Arret }) {
  return (
    <View style={styles.carte}>
      <View style={styles.ligneEnteteCarte}>
        <Text style={styles.nomDepot} numberOfLines={1}>
          {arret.depotNom}
        </Text>
      </View>

      <View style={styles.ligneFormats}>
        {arret.lignes.map((ligne, index) => (
          <View key={index} style={styles.ligneFormat}>
            {ligne.format ? (
              <Text style={styles.formatCode}>
                {ligne.format.code} - {ligne.format.marque}
              </Text>
            ) : null}
            <View style={styles.formatChiffres}>
              <Text style={styles.texteDepotRecup}>À déposer : {ligne.pleines}</Text>
              <Text style={styles.texteDepotRecup}>À récupérer : {ligne.vides_a_recuperer}</Text>
            </View>
          </View>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  entete: {
    marginBottom: espacements.md,
  },
  ligneSousTitre: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sousTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
  },
  livreur: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  progression: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
    fontWeight: '600',
  },
  liste: {
    padding: espacements.lg,
    gap: espacements.md,
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
  vide: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
  },
  texteVide: {
    color: couleurs.texteDoux,
    textAlign: 'center',
    paddingHorizontal: espacements.lg,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginBottom: espacements.md,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  ligneEnteteCarte: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacements.sm,
  },
  nomDepot: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
    flexShrink: 1,
  },
  ligneFormats: {
    marginTop: espacements.md,
    gap: espacements.sm,
  },
  ligneFormat: {
    borderTopWidth: 1,
    borderTopColor: couleurs.bordure,
    paddingTop: espacements.sm,
  },
  formatCode: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  formatChiffres: {
    flexDirection: 'row',
    gap: espacements.lg,
    marginTop: 2,
  },
  texteDepotRecup: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
});
