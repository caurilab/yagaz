import { useMemo, useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeStatutCommande } from '../../components/BadgeStatut';
import { Bouton } from '../../components/Bouton';
import { EnteteEcran } from '../../components/EnteteEcran';
import { useDepot } from '../../data/DepotContext';
import { useDialogue } from '../../data/DialogueContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { CommandeDepot, MembreLivreur } from '../../api/types';

const STATUTS_FILE: CommandeDepot['statut'][] = ['confirmee', 'preparee', 'en_livraison'];

/** File des commandes entrantes : préparer, puis affecter un livreur (UX §3, contrat §4). */
export default function EcranCommandesDepot() {
  const { commandes, livreurs, preparerCommande, affecterLivraison } = useDepot();
  const { alerter } = useDialogue();
  const [uuidEnCours, setUuidEnCours] = useState<string | null>(null);
  const [commandeAffectation, setCommandeAffectation] = useState<string | null>(null);

  const file = useMemo(
    () =>
      commandes
        .filter((c) => STATUTS_FILE.includes(c.statut))
        .sort((a, b) => (a.created_at < b.created_at ? 1 : -1)),
    [commandes]
  );

  async function preparer(commande: CommandeDepot) {
    setUuidEnCours(commande.uuid);
    try {
      await preparerCommande(commande.uuid);
    } catch {
      void alerter({ titre: 'Action impossible', message: 'Impossible de préparer cette commande pour le moment.' });
    } finally {
      setUuidEnCours(null);
    }
  }

  async function affecter(livreur: MembreLivreur) {
    if (!commandeAffectation) return;
    const uuid = commandeAffectation;
    setCommandeAffectation(null);
    setUuidEnCours(uuid);
    try {
      await affecterLivraison(uuid, livreur.uuid);
    } catch {
      void alerter({ titre: 'Action impossible', message: "Impossible d'affecter ce livreur pour le moment." });
    } finally {
      setUuidEnCours(null);
    }
  }

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Commandes" sousTitre={`${file.length} commande(s) en cours`} />

      <FlatList
        data={file}
        keyExtractor={(c) => c.uuid}
        contentContainerStyle={styles.liste}
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucune commande en attente pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={styles.carte}>
            <View style={styles.ligneEntete}>
              <View style={styles.zoneNom}>
                <Text style={styles.nomSite}>{item.site.nom}</Text>
                {item.site.adresse ? <Text style={styles.adresseSite}>{item.site.adresse}</Text> : null}
              </View>
              <BadgeStatutCommande statut={item.statut} />
            </View>

            <Text style={styles.details}>
              {item.format.code} - {item.format.marque} · {item.quantite} bouteille(s)
            </Text>

            {item.livraison ? (
              <Text style={styles.livreurInfo}>
                Livreur : {item.livraison.livreur_nom ?? 'à confirmer'} - {item.livraison.statut}
              </Text>
            ) : null}

            {item.statut === 'confirmee' ? (
              <Bouton
                titre="Préparer"
                enCours={uuidEnCours === item.uuid}
                onPress={() => preparer(item)}
                style={styles.bouton}
              />
            ) : null}

            {item.statut === 'preparee' ? (
              <Bouton
                titre="Affecter un livreur"
                variante="contour"
                enCours={uuidEnCours === item.uuid}
                onPress={() => setCommandeAffectation(item.uuid)}
                style={styles.bouton}
              />
            ) : null}
          </View>
        )}
      />

      <Modal
        visible={commandeAffectation !== null}
        animationType="slide"
        transparent
        onRequestClose={() => setCommandeAffectation(null)}>
        <View style={styles.superposition}>
          <Pressable style={StyleSheet.absoluteFill} onPress={() => setCommandeAffectation(null)} />
          <View style={styles.feuille}>
            <SafeAreaView edges={['bottom']}>
              <Text style={styles.titreFeuille}>Choisir un livreur</Text>
              {livreurs.length === 0 ? (
                <Text style={styles.texteVide}>Aucun livreur rattaché à ce dépôt.</Text>
              ) : (
                livreurs.map((livreur) => (
                  <Pressable key={livreur.uuid} style={styles.ligneLivreur} onPress={() => affecter(livreur)}>
                    <Text style={styles.nomLivreur}>{livreur.nom}</Text>
                  </Pressable>
                ))
              )}
            </SafeAreaView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
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
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.lg,
    marginBottom: espacements.md,
  },
  ligneEntete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: espacements.sm,
  },
  zoneNom: {
    flex: 1,
  },
  nomSite: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
  },
  adresseSite: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  details: {
    fontSize: 14,
    color: couleurs.texte,
    marginTop: espacements.sm,
  },
  livreurInfo: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  bouton: {
    marginTop: espacements.md,
  },
  superposition: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  feuille: {
    backgroundColor: couleurs.carte,
    borderTopLeftRadius: rayons.xl,
    borderTopRightRadius: rayons.xl,
    paddingHorizontal: espacements.lg,
    paddingTop: espacements.lg,
    maxHeight: '70%',
  },
  titreFeuille: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.md,
  },
  ligneLivreur: {
    paddingVertical: espacements.md,
    borderBottomWidth: 1,
    borderBottomColor: couleurs.bordure,
  },
  nomLivreur: {
    fontSize: 16,
    fontWeight: '600',
    color: couleurs.texte,
  },
});
