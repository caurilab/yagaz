import { useState } from 'react';
import { Alert, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import { useLivreur } from '../../data/LivreurContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Notification } from '../../api/types';

/**
 * Notifications du livreur (doc 11 §3), dont l'alerte de tension d'un foyer
 * habituel : uniquement le sous-ensemble minimal `contexte` (nom, zone,
 * format) est affiché - jamais de niveau, d'autonomie ni d'historique
 * (ADR 0008). Depuis une notification de tension, le livreur peut proposer
 * une livraison (ADR 0009 §C).
 */
export default function EcranNotificationsLivreur() {
  const { notifications, formats, statutSync, rafraichir, marquerNotificationVue, proposerLivraison } = useLivreur();
  const [idEnCours, setIdEnCours] = useState<number | null>(null);

  const triees = [...notifications].sort((a, b) => (a.created_at < b.created_at ? 1 : -1));

  async function proposer(notification: Notification) {
    if (!notification.contexte) return;
    const format = formats.find((f) => f.code === notification.contexte?.format_code);
    if (!format) {
      Alert.alert('Format inconnu', 'Impossible de préparer cette proposition pour le moment.');
      return;
    }
    setIdEnCours(notification.id);
    try {
      await proposerLivraison({ notification_id: notification.id, format_id: format.id, quantite: 1 });
      Alert.alert('Proposition envoyée', 'Le foyer va recevoir votre proposition et pourra l\'accepter ou la refuser.');
    } catch {
      Alert.alert('Erreur', "L'envoi de la proposition a échoué. Réessayez.");
    } finally {
      setIdEnCours(null);
    }
  }

  async function marquerVue(notification: Notification) {
    setIdEnCours(notification.id);
    try {
      await marquerNotificationVue(notification.id);
    } finally {
      setIdEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <View style={styles.entete}>
        <Text style={styles.titre}>Notifications</Text>
      </View>

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
                    titre="Proposer la livraison"
                    enCours={idEnCours === item.id}
                    onPress={() => proposer(item)}
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
