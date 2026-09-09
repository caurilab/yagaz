import { router } from 'expo-router';
import { useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';

import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { EnteteEcran } from '../../components/EnteteEcran';
import { useLivreur } from '../../data/LivreurContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Notification } from '../../api/types';

/**
 * Notifications du livreur (doc 11 §3), dont le rappel de tension d'un foyer
 * habituel : uniquement le sous-ensemble minimal `contexte` (nom, zone,
 * format) est affiché - jamais de niveau, d'autonomie ni d'historique
 * (ADR 0008). Ce rappel n'est PAS actionnable directement : il ne porte pas
 * de `site_uuid` (ADR 0008). « Proposer » renvoie vers la file actionnable
 * (`/tension`, `GET /api/livreur/foyers-en-tension`) qui, elle, identifie le
 * foyer (ADR 0008, précision « maillon C »).
 */
export default function EcranNotificationsLivreur() {
  const { notifications, statutSync, rafraichir, marquerNotificationVue } = useLivreur();
  const [idEnCours, setIdEnCours] = useState<number | null>(null);

  const triees = [...notifications].sort((a, b) => (a.created_at < b.created_at ? 1 : -1));

  async function marquerVue(notification: Notification) {
    setIdEnCours(notification.id);
    try {
      await marquerNotificationVue(notification.id);
    } finally {
      setIdEnCours(null);
    }
  }

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Notifications" />

      <FlatList
        data={triees}
        keyExtractor={(n) => String(n.id)}
        contentContainerStyle={styles.liste}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}
        ListHeaderComponent={
          statutSync === 'hors_ligne' ? (
            <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
          ) : null
        }
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucune notification pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={[styles.carte, item.statut === 'vue' && styles.carteVue]}>
            {item.contexte ? (
              <>
                <Text style={styles.nomSite}>{item.contexte.site_nom}</Text>
                <Text style={styles.zone}>{item.contexte.zone}</Text>
                <Text style={styles.details}>Format {item.contexte.format_code}</Text>
              </>
            ) : (
              <Text style={styles.message}>{item.message}</Text>
            )}

            {item.statut !== 'vue' ? (
              <View style={styles.actions}>
                {item.contexte ? (
                  <Bouton
                    titre="Proposer une livraison"
                    onPress={() => router.push('/tension')}
                    style={styles.boutonAction}
                  />
                ) : null}
                <Bouton
                  titre="Marquer comme vue"
                  variante="discret"
                  enCours={idEnCours === item.id}
                  onPress={() => marquerVue(item)}
                  style={styles.boutonAction}
                />
              </View>
            ) : null}
          </View>
        )}
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
    borderLeftWidth: 4,
    borderLeftColor: couleurs.rouge,
    padding: espacements.lg,
    marginBottom: espacements.md,
  },
  carteVue: {
    borderLeftColor: couleurs.grisNeutre,
    opacity: 0.6,
  },
  nomSite: {
    fontSize: 17,
    fontWeight: '700',
    color: couleurs.texte,
  },
  zone: {
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
  message: {
    fontSize: 15,
    color: couleurs.texte,
  },
  actions: {
    flexDirection: 'row',
    gap: espacements.sm,
    marginTop: espacements.md,
  },
  boutonAction: {
    flex: 1,
  },
});
