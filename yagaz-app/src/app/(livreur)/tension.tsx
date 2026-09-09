import { useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { useDialogue } from '../../data/DialogueContext';
import { useLivreur } from '../../data/LivreurContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { FoyerEnTensionLivreur } from '../../api/types';

/**
 * File actionnable des foyers habituels en tension (ADR 0008, précision
 * « maillon C » ; contrat 10 §5, `GET /api/livreur/foyers-en-tension`) :
 * le `site_uuid` nécessaire pour proposer vient TOUJOURS de cette file,
 * jamais de la notification de seuil bas (minimale, sans identifiant,
 * ADR 0008) - le rappel de l'onglet Notifications renvoie ici.
 */
export default function EcranTensionLivreur() {
  const { foyersEnTension, statutSync, rafraichir, proposerLivraison } = useLivreur();
  const { alerter } = useDialogue();
  const [siteEnCours, setSiteEnCours] = useState<string | null>(null);

  async function proposer(foyer: FoyerEnTensionLivreur) {
    setSiteEnCours(foyer.site_uuid);
    try {
      await proposerLivraison(foyer);
      void alerter({
        titre: 'Proposition envoyée',
        message: "Le foyer va recevoir votre proposition et pourra l'accepter ou la refuser.",
      });
    } catch {
      void alerter({ titre: 'Erreur', message: "L'envoi de la proposition a échoué. Réessayez." });
    } finally {
      setSiteEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <FlatList
        data={foyersEnTension}
        keyExtractor={(f) => f.site_uuid}
        contentContainerStyle={styles.liste}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}
        ListHeaderComponent={
          <>
            <Text style={styles.titre}>Foyers en tension</Text>
            <Text style={styles.sousTitre}>Vos foyers habituels qui ont besoin d'une recharge.</Text>
            {statutSync === 'hors_ligne' ? (
              <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
            ) : null}
          </>
        }
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucun de vos foyers habituels n'est en tension pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={styles.carte}>
            <Text style={styles.nomFoyer}>{item.nom}</Text>
            <Text style={styles.zoneFoyer}>{item.zone}</Text>
            <Text style={styles.details}>
              {item.format.code} - {item.format.marque}
            </Text>
            <Bouton
              titre="Proposer une livraison"
              enCours={siteEnCours === item.site_uuid}
              onPress={() => proposer(item)}
              style={styles.boutonAction}
            />
          </View>
        )}
      />
    </SafeAreaView>
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
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
  },
  sousTitre: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
    marginBottom: espacements.lg,
  },
  vide: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
  },
  texteVide: {
    color: couleurs.texteDoux,
    textAlign: 'center',
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    borderLeftWidth: 4,
    borderLeftColor: couleurs.rouge,
    padding: espacements.lg,
    marginBottom: espacements.md,
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
  details: {
    fontSize: 14,
    color: couleurs.texte,
    marginTop: espacements.sm,
    fontWeight: '600',
  },
  boutonAction: {
    marginTop: espacements.md,
  },
});
