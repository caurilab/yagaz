import { useMemo } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeStatutCommande } from '../../components/BadgeStatut';
import { BandeauSync } from '../../components/BandeauSync';
import { useMandataire } from '../../data/MandataireContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { ReapproMandataire } from '../../api/types';

interface GroupeDepot {
  depotUuid: string;
  depotNom: string;
  depotZone: string | null;
  reappros: ReapproMandataire[];
}

function grouperParDepot(reappros: ReapproMandataire[]): GroupeDepot[] {
  const parDepot = new Map<string, GroupeDepot>();
  for (const reappro of reappros) {
    const existant = parDepot.get(reappro.depot.uuid);
    if (existant) {
      existant.reappros.push(reappro);
    } else {
      parDepot.set(reappro.depot.uuid, {
        depotUuid: reappro.depot.uuid,
        depotNom: reappro.depot.nom,
        depotZone: reappro.depot.zone,
        reappros: [reappro],
      });
    }
  }
  return Array.from(parDepot.values());
}

function formaterDate(iso: string): string {
  const date = new Date(iso);
  return date.toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

/**
 * Réappros des dépôts du mandataire (parité web, doc 11 §1) : lecture seule
 * ici (l'ajustement/la confirmation se fait côté dépôt, ADR 0009 §D) -
 * regroupés par dépôt, les `confirmee` (fermes) mis en avant.
 */
export default function EcranReapprosMandataire() {
  const { reappros, statutSync, chargementInitial, rafraichir } = useMandataire();

  const groupes = useMemo(() => grouperParDepot(reappros), [reappros]);

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <FlatList
        data={groupes}
        keyExtractor={(g) => g.depotUuid}
        contentContainerStyle={styles.liste}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}
        ListHeaderComponent={
          <>
            <View style={styles.entete}>
              <Text style={styles.titre}>Réappros</Text>
              <Text style={styles.sousTitre}>Demandes de réappro des dépôts</Text>
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
              <Text style={styles.texteChargement}>Chargement des réappros...</Text>
            </View>
          ) : (
            <View style={styles.vide}>
              <Text style={styles.texteVide}>Aucun réappro pour l'instant.</Text>
            </View>
          )
        }
        renderItem={({ item }) => <CarteGroupeDepot groupe={item} />}
      />
    </SafeAreaView>
  );
}

function CarteGroupeDepot({ groupe }: { groupe: GroupeDepot }) {
  return (
    <View style={styles.carteGroupe}>
      <View style={styles.enteteGroupe}>
        <Text style={styles.nomDepot} numberOfLines={1}>
          {groupe.depotNom}
        </Text>
        {groupe.depotZone ? <Text style={styles.zoneDepot}>{groupe.depotZone}</Text> : null}
      </View>
      <View style={styles.ligneReappros}>
        {groupe.reappros.map((reappro) => (
          <LigneReappro key={reappro.uuid} reappro={reappro} />
        ))}
      </View>
    </View>
  );
}

function LigneReappro({ reappro }: { reappro: ReapproMandataire }) {
  const ferme = reappro.statut === 'confirmee';
  return (
    <View style={[styles.ligne, ferme && styles.ligneFerme]}>
      <View style={styles.ligneEntete}>
        <Text style={styles.formatTexte}>
          {reappro.format.code} - {reappro.format.marque}
        </Text>
        <BadgeStatutCommande statut={reappro.statut} />
      </View>
      <View style={styles.ligneDetail}>
        <Text style={styles.quantiteTexte}>{reappro.quantite} bouteilles</Text>
        <Text style={styles.dateTexte}>{formaterDate(reappro.created_at)}</Text>
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
  },
  carteGroupe: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.lg,
    marginBottom: espacements.md,
  },
  enteteGroupe: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'baseline',
    gap: espacements.sm,
  },
  nomDepot: {
    fontSize: 17,
    fontWeight: '700',
    color: couleurs.texte,
    flexShrink: 1,
  },
  zoneDepot: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
  ligneReappros: {
    marginTop: espacements.md,
    gap: espacements.sm,
  },
  ligne: {
    borderTopWidth: 1,
    borderTopColor: couleurs.bordure,
    paddingTop: espacements.sm,
  },
  ligneFerme: {
    backgroundColor: couleurs.rougeClair,
    borderTopWidth: 0,
    borderRadius: rayons.md,
    padding: espacements.sm,
  },
  ligneEntete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacements.sm,
  },
  formatTexte: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  ligneDetail: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 4,
  },
  quantiteTexte: {
    fontSize: 13,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  dateTexte: {
    fontSize: 12,
    color: couleurs.texteDoux,
  },
});
