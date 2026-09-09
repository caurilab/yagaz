import { useMemo, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';

import { Bouton } from '../../components/Bouton';
import { EnteteEcran } from '../../components/EnteteEcran';
import { useDepot } from '../../data/DepotContext';
import { useDialogue } from '../../data/DialogueContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { FoyerEnTension } from '../../api/types';

/**
 * File des foyers en tension (ADR 0009 §B) et propositions envoyées : le
 * site_uuid vient de la file elle-même, plus de saisie d'UUID à l'aveugle
 * (UX §3, contrat §4).
 */
export default function EcranPropositionsDepot() {
  const { foyersEnTension, commandes, proposerDepuisTension } = useDepot();
  const { alerter } = useDialogue();
  const [siteEnCours, setSiteEnCours] = useState<string | null>(null);

  const propositionsEnvoyees = useMemo(
    () => commandes.filter((c) => c.statut === 'proposee').sort((a, b) => (a.created_at < b.created_at ? 1 : -1)),
    [commandes]
  );

  async function proposer(foyer: FoyerEnTension) {
    setSiteEnCours(foyer.site_uuid);
    try {
      await proposerDepuisTension(foyer);
    } catch {
      void alerter({ titre: 'Erreur', message: "L'envoi de la proposition a échoué. Réessayez." });
    } finally {
      setSiteEnCours(null);
    }
  }

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Foyers en tension" sousTitre="« Votre bouteille est presque vide, on vous livre ? »" />
      <FlatList
        data={foyersEnTension}
        keyExtractor={(f) => f.site_uuid}
        contentContainerStyle={styles.liste}
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucun foyer en tension pour l'instant dans votre zone.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={styles.carteFoyer}>
            <Text style={styles.nomFoyer}>{item.nom}</Text>
            <Text style={styles.zoneFoyer}>{item.zone}</Text>
            <Text style={styles.detailsFoyer}>
              {item.format ? `${item.format.code} - ${item.format.marque} · ` : ''}à {item.distance_km} km
            </Text>
            {item.format ? (
              <Bouton
                titre="Proposer une livraison"
                enCours={siteEnCours === item.site_uuid}
                onPress={() => proposer(item)}
                style={styles.boutonProposer}
              />
            ) : null}
          </View>
        )}
        ListFooterComponent={
          propositionsEnvoyees.length > 0 ? (
            <>
              <Text style={styles.titreSection}>En attente de réponse</Text>
              {propositionsEnvoyees.map((item) => (
                <View key={item.uuid} style={styles.cartePropostion}>
                  <Text style={styles.propositionDetails}>
                    {item.format ? `${item.format.code} · ` : ''}{item.quantite} bouteille(s)
                  </Text>
                  <Text style={styles.propositionSite}>{item.site.nom}</Text>
                </View>
              ))}
            </>
          ) : null
        }
      />
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
    paddingBottom: espacements.xxl,
  },
  vide: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
  },
  texteVide: {
    color: couleurs.texteDoux,
    textAlign: 'center',
  },
  carteFoyer: {
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
  nomFoyer: {
    fontSize: 17,
    fontWeight: '700',
    color: couleurs.texte,
  },
  zoneFoyer: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  detailsFoyer: {
    fontSize: 14,
    color: couleurs.texte,
    marginTop: espacements.sm,
    fontWeight: '600',
  },
  boutonProposer: {
    marginTop: espacements.md,
  },
  titreSection: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginTop: espacements.xl,
    marginBottom: espacements.sm,
  },
  cartePropostion: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.md,
    padding: espacements.md,
    marginBottom: espacements.sm,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  propositionDetails: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.texte,
  },
  propositionSite: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
});
