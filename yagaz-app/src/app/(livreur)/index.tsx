import { useMemo, useState } from 'react';
import { Alert, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeStatutLivraison } from '../../components/BadgeStatut';
import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { useLivreur } from '../../data/LivreurContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { libelleActionLivraison, statutLivraisonSuivant } from '../../utils/statuts';
import type { MissionLivreur } from '../../api/types';

/** Missions du livreur : adresse, format, quantité, statut, gros boutons (UX §6, contrat §5). */
export default function EcranMissionsLivreur() {
  const { missions, statutSync, rafraichir, majStatut } = useLivreur();
  const [idEnCours, setIdEnCours] = useState<number | null>(null);

  const enCours = useMemo(
    () => missions.filter((m) => m.statut !== 'vide_recupere').sort((a, b) => (a.created_at < b.created_at ? -1 : 1)),
    [missions]
  );
  const terminees = useMemo(() => missions.filter((m) => m.statut === 'vide_recupere'), [missions]);

  async function avancer(mission: MissionLivreur) {
    const suivant = statutLivraisonSuivant(mission.statut);
    if (!suivant) return;
    setIdEnCours(mission.livraison_id);
    try {
      await majStatut(mission.livraison_id, suivant, suivant === 'vide_recupere' ? mission.a_recuperer : undefined);
    } catch {
      Alert.alert('Action impossible', 'Impossible de mettre à jour cette mission pour le moment.');
    } finally {
      setIdEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <View style={styles.entete}>
        <Text style={styles.titre}>Mes missions</Text>
        <Text style={styles.sousTitre}>{enCours.length} en cours</Text>
      </View>

      <FlatList
        data={enCours}
        keyExtractor={(m) => String(m.livraison_id)}
        contentContainerStyle={styles.liste}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}
        ListHeaderComponent={
          statutSync === 'hors_ligne' ? (
            <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
          ) : null
        }
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucune mission pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <CarteMission
            mission={item}
            enCours={idEnCours === item.livraison_id}
            onAvancer={() => avancer(item)}
          />
        )}
        ListFooterComponent={
          terminees.length > 0 ? (
            <>
              <Text style={styles.titreSection}>Terminées</Text>
              {terminees.map((mission) => (
                <CarteMission key={mission.livraison_id} mission={mission} enCours={false} onAvancer={() => {}} />
              ))}
            </>
          ) : null
        }
      />
    </SafeAreaView>
  );
}

function CarteMission({
  mission,
  enCours,
  onAvancer,
}: {
  mission: MissionLivreur;
  enCours: boolean;
  onAvancer: () => void;
}) {
  const libelleAction = libelleActionLivraison(mission.statut);

  return (
    <View style={[styles.carte, mission.statut === 'vide_recupere' && styles.carteTerminee]}>
      <View style={styles.ligneEntete}>
        <Text style={styles.nomSite} numberOfLines={1}>
          {mission.site.nom}
        </Text>
        <BadgeStatutLivraison statut={mission.statut} />
      </View>
      {mission.site.adresse ? <Text style={styles.adresse}>{mission.site.adresse}</Text> : null}

      <Text style={styles.details}>
        {mission.format.code} - {mission.format.marque} · {mission.quantite} bouteille(s)
      </Text>
      <View style={styles.rangeeDepotRecup}>
        <Text style={styles.texteDepotRecup}>À déposer : {mission.a_deposer}</Text>
        <Text style={styles.texteDepotRecup}>À récupérer : {mission.a_recuperer}</Text>
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
  titreSection: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginTop: espacements.lg,
    marginBottom: espacements.sm,
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
  ligneEntete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacements.sm,
  },
  nomSite: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
    flexShrink: 1,
  },
  adresse: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  details: {
    fontSize: 15,
    color: couleurs.texte,
    marginTop: espacements.md,
    fontWeight: '600',
  },
  rangeeDepotRecup: {
    flexDirection: 'row',
    gap: espacements.lg,
    marginTop: espacements.xs,
  },
  texteDepotRecup: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
  boutonAction: {
    marginTop: espacements.lg,
  },
});
