import { useMemo, useState } from 'react';
import { router } from 'expo-router';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeStatutCommande, BadgeStatutPaiement } from '../../components/BadgeStatut';
import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { useCommandes } from '../../data/CommandesContext';
import { useDialogue } from '../../data/DialogueContext';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Commande, StatutPaiement } from '../../api/types';

/**
 * Suivi de commande jusqu'à la livraison, et réponse à une proposition
 * reçue (oui/non) - contrat 10 §2-3, UX §2 "Recharge" et "Proposition reçue".
 */
export default function EcranCommandesFoyer() {
  const { commandes, statutSync, rafraichir, repondre, paiements, payerMobileMoney } = useCommandes();
  const { sites } = useDonnees();
  const { alerter } = useDialogue();
  const [uuidEnCours, setUuidEnCours] = useState<string | null>(null);
  const [uuidPaiementEnCours, setUuidPaiementEnCours] = useState<string | null>(null);

  const commandesTriees = useMemo(
    () => [...commandes].sort((a, b) => (a.created_at < b.created_at ? 1 : -1)),
    [commandes]
  );

  async function repondreProposition(commande: Commande, accepte: boolean) {
    setUuidEnCours(commande.uuid);
    try {
      await repondre(commande.uuid, accepte);
    } catch {
      void alerter({ titre: 'Action impossible', message: 'Impossible d\'enregistrer votre réponse pour le moment.' });
    } finally {
      setUuidEnCours(null);
    }
  }

  async function payer(commande: Commande) {
    setUuidPaiementEnCours(commande.uuid);
    try {
      await payerMobileMoney(commande.uuid);
    } catch {
      void alerter({
        titre: 'Paiement impossible',
        message: "Impossible d'initier le paiement Mobile Money pour le moment - vous pouvez payer à la livraison.",
      });
    } finally {
      setUuidPaiementEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <View style={styles.entete}>
        <Text style={styles.titre}>Mes commandes</Text>
        <Bouton titre="Nouvelle commande" variante="contour" onPress={() => router.push('/nouvelle-commande')} />
      </View>

      <FlatList
        data={commandesTriees}
        keyExtractor={(c) => c.uuid}
        contentContainerStyle={styles.liste}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}
        ListHeaderComponent={
          statutSync === 'hors_ligne' ? (
            <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
          ) : null
        }
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucune commande pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => {
          const nomSite = sites.find((s) => s.uuid === item.site_uuid)?.nom;
          const statutPaiement: StatutPaiement = paiements[item.uuid]?.statut ?? item.statut_paiement;
          const paiementEnCours = uuidPaiementEnCours === item.uuid;
          return (
            <View style={styles.carte}>
              <View style={styles.ligneEntete}>
                <Text style={styles.nomSite}>{nomSite ?? 'Mon site'}</Text>
                <BadgeStatutCommande statut={item.statut} />
              </View>

              <Text style={styles.details}>
                {item.format.code} - {item.format.marque} · {item.quantite} bouteille(s)
              </Text>

              {item.livraison ? (
                <Text style={styles.livreurInfo}>
                  {item.livraison.livreur_nom ? `Livreur : ${item.livraison.livreur_nom} - ` : ''}
                  {item.livraison.statut}
                </Text>
              ) : null}

              {item.statut === 'confirmee' ? (
                <View style={styles.paiement}>
                  {statutPaiement === 'regle' ? (
                    <BadgeStatutPaiement statut={statutPaiement} />
                  ) : (
                    <>
                      {statutPaiement !== 'en_attente' ? <BadgeStatutPaiement statut={statutPaiement} /> : null}
                      <Bouton
                        titre={statutPaiement === 'echoue' || statutPaiement === 'expire' ? 'Réessayer' : 'Payer par Mobile Money'}
                        variante="contour"
                        enCours={paiementEnCours}
                        desactive={statutPaiement === 'initie'}
                        onPress={() => payer(item)}
                      />
                      <Text style={styles.rappelPaiement}>
                        {statutPaiement === 'initie'
                          ? 'Confirmez sur votre téléphone (USSD ou lien reçu de l\'opérateur).'
                          : 'Le paiement à la livraison reste possible.'}
                      </Text>
                    </>
                  )}
                </View>
              ) : null}

              {item.statut === 'proposee' ? (
                <View style={styles.actions}>
                  <Bouton
                    titre="Refuser"
                    variante="contour"
                    enCours={uuidEnCours === item.uuid}
                    onPress={() => repondreProposition(item, false)}
                    style={styles.boutonAction}
                  />
                  <Bouton
                    titre="Accepter"
                    enCours={uuidEnCours === item.uuid}
                    onPress={() => repondreProposition(item, true)}
                    style={styles.boutonAction}
                  />
                </View>
              ) : null}

              {item.statut !== 'proposee' && item.statut !== 'annulee' ? (
                <View style={styles.accesRangee}>
                  {item.statut === 'confirmee' || item.statut === 'preparee' || item.statut === 'en_livraison' ? (
                    <Pressable style={styles.lienAcces} onPress={() => router.push(`/suivi/${item.uuid}`)}>
                      <Text style={styles.texteLienAcces}>Suivre la commande</Text>
                    </Pressable>
                  ) : null}
                  <Pressable style={styles.lienAcces} onPress={() => router.push(`/recu/${item.uuid}`)}>
                    <Text style={styles.texteLienAcces}>Voir le reçu</Text>
                  </Pressable>
                </View>
              ) : null}
            </View>
          );
        }}
      />
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
    gap: espacements.md,
  },
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
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
    alignItems: 'center',
    gap: espacements.sm,
  },
  nomSite: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
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
  paiement: {
    marginTop: espacements.md,
    gap: espacements.sm,
    alignItems: 'flex-start',
  },
  rappelPaiement: {
    fontSize: 12,
    color: couleurs.texteDoux,
  },
  actions: {
    flexDirection: 'row',
    gap: espacements.sm,
    marginTop: espacements.md,
  },
  boutonAction: {
    flex: 1,
  },
  accesRangee: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: espacements.lg,
    marginTop: espacements.md,
  },
  lienAcces: {
    paddingVertical: espacements.xs,
  },
  texteLienAcces: {
    fontSize: 13,
    fontWeight: '700',
    color: couleurs.rouge,
  },
});
