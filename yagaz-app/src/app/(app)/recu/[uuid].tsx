/**
 * Reçu de paiement d'une commande (contrat API `GET /commandes/{uuid}/recu`) :
 * "facture" - en-tête de marque, montant, moyen de paiement, statut, détail
 * commande (format, quantité, dépôt) et site. Câblage : appel direct + repli
 * démo (`recuCommandeDemo`, cohérent avec `CommandesContext`/le paiement
 * Mobile Money simulé). Partage simple via `Share` (natif, hors app).
 */
import { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, RefreshControl, ScrollView, Share, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../../components/Bouton';
import { LogoYagaz } from '../../../components/icones';
import { useCommandes } from '../../../data/CommandesContext';
import { useDialogue } from '../../../data/DialogueContext';
import { recuCommande } from '../../../api/endpoints';
import { avecRepliDemo, formatsDemo, recuCommandeDemo } from '../../../api/demo';
import { couleurs, espacements, rayons } from '../../../../theme/couleurs';
import { formaterMontantFcfa } from '../../../utils/date';
import { couleursStatutPaiement, libellesStatutPaiement } from '../../../utils/statuts';
import type { Commande, ModePaiement, RecuPaiement } from '../../../api/types';

const LIBELLES_MODE_PAIEMENT: Record<ModePaiement, string> = {
  a_la_livraison: 'Paiement à la livraison',
  mobile_money: 'Mobile Money',
};

export default function EcranRecu() {
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const { commandes } = useCommandes();
  const { alerter } = useDialogue();
  const commande = commandes.find((c) => c.uuid === uuid);

  const [recu, setRecu] = useState<RecuPaiement | null>(null);
  const [chargement, setChargement] = useState(true);

  const charger = useCallback(async () => {
    if (!uuid) return;
    setChargement(true);
    const commandeRepli: Commande = commande ?? {
      uuid,
      site_uuid: '',
      format: formatsDemo[0],
      quantite: 1,
      depot_uuid: '',
      statut: 'confirmee',
      commission_g: 0,
      mode_paiement: 'a_la_livraison',
      statut_paiement: 'en_attente',
      created_at: new Date().toISOString(),
      livraison: null,
    };
    try {
      const data = await avecRepliDemo(
        () => recuCommande(uuid).then((r) => r.data),
        recuCommandeDemo(commandeRepli)
      );
      setRecu(data);
    } finally {
      setChargement(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uuid, commande?.statut_paiement]);

  useEffect(() => {
    charger();
  }, [charger]);

  async function partager() {
    if (!recu) return;
    const detailFormat = recu.commande.format ? `${recu.commande.format.code} - ${recu.commande.format.marque}` : 'Commande Yagaz';
    try {
      await Share.share({
        message: `Reçu Yagaz - ${detailFormat} · ${formaterMontantFcfa(recu.montant)} (${libellesStatutPaiement[recu.statut_paiement]})${recu.reference ? ` - réf. ${recu.reference}` : ''}`,
      });
    } catch {
      void alerter({ titre: 'Erreur', message: "Impossible de partager le reçu pour l'instant." });
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView
        contentContainerStyle={styles.contenu}
        refreshControl={<RefreshControl refreshing={false} onRefresh={charger} tintColor={couleurs.rouge} />}>
        {chargement && !recu ? (
          <View style={styles.chargement}>
            <ActivityIndicator color={couleurs.rouge} size="large" />
          </View>
        ) : !recu ? (
          <Text style={styles.texteVide}>Reçu indisponible pour cette commande.</Text>
        ) : (
          <View style={styles.facture}>
            <View style={styles.enteteFacture}>
              <LogoYagaz taille={34} />
              <View style={[styles.badgeStatut, { backgroundColor: couleursStatutPaiement[recu.statut_paiement] }]}>
                <Text style={styles.texteBadgeStatut}>{libellesStatutPaiement[recu.statut_paiement]}</Text>
              </View>
            </View>

            <View style={styles.blocMontant}>
              <Text style={styles.montant}>{formaterMontantFcfa(recu.montant)}</Text>
            </View>

            <View style={styles.separateur} />

            <LigneRecu libelle="Référence" valeur={recu.reference ?? 'Non attribuée'} />
            <LigneRecu libelle="Moyen de paiement" valeur={LIBELLES_MODE_PAIEMENT[recu.mode_paiement]} />
            <LigneRecu
              libelle="Date"
              valeur={
                recu.date
                  ? new Date(recu.date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
                  : 'Non renseignée'
              }
            />

            <View style={styles.separateur} />

            <Text style={styles.sousTitre}>Détail de la commande</Text>
            <LigneRecu
              libelle="Format"
              valeur={recu.commande.format ? `${recu.commande.format.code} - ${recu.commande.format.marque}` : 'Non renseigné'}
            />
            <LigneRecu libelle="Quantité" valeur={`${recu.commande.quantite} bouteille${recu.commande.quantite > 1 ? 's' : ''}`} />
            <LigneRecu libelle="Dépôt" valeur={recu.commande.depot?.nom ?? 'Non renseigné'} />
            <LigneRecu libelle="Site" valeur={recu.site?.nom ?? 'Non renseigné'} />
          </View>
        )}

        <Bouton titre="Partager" variante="contour" onPress={partager} desactive={!recu} style={styles.boutonPartager} />
      </ScrollView>
    </SafeAreaView>
  );
}

function LigneRecu({ libelle, valeur }: { libelle: string; valeur: string }) {
  return (
    <View style={styles.ligneRecu}>
      <Text style={styles.libelleRecu}>{libelle}</Text>
      <Text style={styles.valeurRecu} numberOfLines={1}>
        {valeur}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  contenu: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  chargement: {
    alignItems: 'center',
    paddingVertical: espacements.xxl,
  },
  texteVide: {
    color: couleurs.texteDoux,
    fontSize: 14,
    textAlign: 'center',
    marginTop: espacements.xxl,
  },
  facture: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.xl,
    marginBottom: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 10 },
    elevation: 4,
  },
  enteteFacture: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: espacements.lg,
  },
  badgeStatut: {
    paddingHorizontal: espacements.sm,
    paddingVertical: espacements.xs,
    borderRadius: rayons.rond,
  },
  texteBadgeStatut: {
    fontSize: 12,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  blocMontant: {
    alignItems: 'center',
    marginBottom: espacements.md,
  },
  montant: {
    fontSize: 40,
    fontWeight: '800',
    color: couleurs.texte,
  },
  separateur: {
    height: 1,
    backgroundColor: couleurs.bordure,
    marginVertical: espacements.md,
  },
  sousTitre: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  ligneRecu: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacements.md,
    paddingVertical: espacements.xs,
  },
  libelleRecu: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
  valeurRecu: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texte,
    flexShrink: 1,
    textAlign: 'right',
  },
  boutonPartager: {
    marginTop: espacements.sm,
  },
});
