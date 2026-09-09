import { useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeStatutLigneTournee, BadgeStatutTournee } from '../../components/BadgeStatut';
import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { useDialogue } from '../../data/DialogueContext';
import { useMandataire } from '../../data/MandataireContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { libelleActionLigneTournee, statutArretTournee, statutLigneTourneeSuivant } from '../../utils/statuts';
import type { TourneeLigne } from '../../api/types';

interface Arret {
  depotUuid: string;
  depotNom: string;
  lignes: TourneeLigne[];
}

function grouperParDepot(lignes: TourneeLigne[]): Arret[] {
  const parDepot = new Map<string, Arret>();
  for (const ligne of lignes) {
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
  const { tourneeDuJour, statutSync, chargementInitial, rafraichir, avancerArret } = useMandataire();
  const { alerter } = useDialogue();
  const [depotUuidEnCours, setDepotUuidEnCours] = useState<string | null>(null);

  const arrets = useMemo(() => (tourneeDuJour ? grouperParDepot(tourneeDuJour.lignes) : []), [tourneeDuJour]);
  const arretsTermines = arrets.filter((a) => statutArretTournee(a.lignes.map((l) => l.statut)) === 'vides_recuperes').length;

  async function avancer(arret: Arret) {
    if (!tourneeDuJour) return;
    const statutActuel = statutArretTournee(arret.lignes.map((l) => l.statut));
    const suivant = statutLigneTourneeSuivant(statutActuel);
    if (!suivant) return;
    setDepotUuidEnCours(arret.depotUuid);
    try {
      await avancerArret(
        tourneeDuJour.uuid,
        arret.lignes.map((l) => l.id),
        suivant
      );
    } catch {
      void alerter({ titre: 'Action impossible', message: 'Impossible de mettre à jour cet arrêt pour le moment.' });
    } finally {
      setDepotUuidEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <FlatList
        data={arrets}
        keyExtractor={(a) => a.depotUuid}
        contentContainerStyle={styles.liste}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}
        ListHeaderComponent={
          <>
            <View style={styles.entete}>
              <Text style={styles.titre}>Tournée du jour</Text>
              {tourneeDuJour ? (
                <>
                  <View style={styles.ligneSousTitre}>
                    <Text style={styles.sousTitre}>{formaterDateTournee(tourneeDuJour.date)}</Text>
                    <BadgeStatutTournee statut={tourneeDuJour.statut} />
                  </View>
                  {tourneeDuJour.livreur_nom ? (
                    <Text style={styles.livreur}>Livreur : {tourneeDuJour.livreur_nom}</Text>
                  ) : null}
                  {arrets.length > 0 ? (
                    <Text style={styles.progression}>
                      {arretsTermines} / {arrets.length} arrêts terminés
                    </Text>
                  ) : null}
                </>
              ) : null}
            </View>
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
        renderItem={({ item }) => (
          <CarteArret arret={item} enCours={depotUuidEnCours === item.depotUuid} onAvancer={() => avancer(item)} />
        )}
      />
    </SafeAreaView>
  );
}

function CarteArret({ arret, enCours, onAvancer }: { arret: Arret; enCours: boolean; onAvancer: () => void }) {
  const statutArret = statutArretTournee(arret.lignes.map((l) => l.statut));
  const libelleAction = libelleActionLigneTournee(statutArret);
  const termine = statutArret === 'vides_recuperes';

  return (
    <View style={[styles.carte, termine && styles.carteTerminee]}>
      <View style={styles.ligneEnteteCarte}>
        <Text style={styles.nomDepot} numberOfLines={1}>
          {arret.depotNom}
        </Text>
        <BadgeStatutLigneTournee statut={statutArret} />
      </View>

      <View style={styles.ligneFormats}>
        {arret.lignes.map((ligne) => (
          <View key={ligne.id} style={styles.ligneFormat}>
            <Text style={styles.formatCode}>
              {ligne.format.code} - {ligne.format.marque}
            </Text>
            <View style={styles.formatChiffres}>
              <Text style={styles.texteDepotRecup}>À déposer : {ligne.pleines}</Text>
              <Text style={styles.texteDepotRecup}>À récupérer : {ligne.vides_a_recuperer}</Text>
            </View>
          </View>
        ))}
      </View>

      {libelleAction ? (
        <Bouton titre={libelleAction} enCours={enCours} onPress={onAvancer} style={styles.boutonAction} />
      ) : null}
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
    paddingTop: espacements.md,
  },
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
  },
  ligneSousTitre: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: espacements.sm,
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
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.lg,
    marginBottom: espacements.md,
  },
  carteTerminee: {
    opacity: 0.6,
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
  boutonAction: {
    marginTop: espacements.lg,
  },
});
