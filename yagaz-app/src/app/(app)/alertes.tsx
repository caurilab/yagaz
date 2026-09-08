import { useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Alerte } from '../../api/types';

const LIBELLES_TYPE: Record<Alerte['type'], string> = {
  seuil_bas: 'Seuil bas',
  proposition: 'Proposition de livraison',
};

export default function EcranAlertes() {
  const { alertes, sites, majAlerteStatut } = useDonnees();
  const [idEnCours, setIdEnCours] = useState<number | null>(null);

  const alertesTriees = [...alertes].sort((a, b) => (a.created_at < b.created_at ? 1 : -1));

  async function agir(alerte: Alerte, statut: 'vue' | 'resolue') {
    setIdEnCours(alerte.id);
    try {
      await majAlerteStatut(alerte.id, statut);
    } finally {
      setIdEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <View style={styles.entete}>
        <Text style={styles.titre}>Alertes</Text>
      </View>

      <FlatList
        data={alertesTriees}
        keyExtractor={(a) => String(a.id)}
        contentContainerStyle={styles.liste}
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucune alerte pour l'instant. Tout va bien.</Text>
          </View>
        }
        renderItem={({ item }) => {
          const nomSite = sites.find((s) => s.uuid === item.site_uuid)?.nom;
          return (
            <View style={[styles.carte, item.statut === 'resolue' && styles.carteResolue]}>
              <View style={styles.ligneEntete}>
                <View style={styles.libelleAvecPastille}>
                  <View
                    style={[
                      styles.pastilleStatut,
                      { backgroundColor: item.statut === 'resolue' ? couleurs.vertOk : couleurs.danger },
                    ]}
                  />
                  <Text style={styles.type}>{LIBELLES_TYPE[item.type]}</Text>
                </View>
                <Text style={styles.statut}>{libelleStatut(item.statut)}</Text>
              </View>
              {nomSite ? <Text style={styles.site}>{nomSite}</Text> : null}
              <Text style={styles.message}>{item.message}</Text>
              <Text style={styles.date}>{formaterDate(item.created_at)}</Text>

              {item.statut !== 'resolue' ? (
                <View style={styles.actions}>
                  {item.statut === 'emise' ? (
                    <Bouton
                      titre="Marquer comme vue"
                      variante="discret"
                      enCours={idEnCours === item.id}
                      onPress={() => agir(item, 'vue')}
                      style={styles.boutonAction}
                    />
                  ) : null}
                  <Bouton
                    titre="Résoudre"
                    variante="contour"
                    enCours={idEnCours === item.id}
                    onPress={() => agir(item, 'resolue')}
                    style={styles.boutonAction}
                  />
                </View>
              ) : null}
            </View>
          );
        }}
      />
    </SafeAreaView>
  );
}

function libelleStatut(statut: Alerte['statut']): string {
  if (statut === 'emise') return 'Nouvelle';
  if (statut === 'vue') return 'Vue';
  return 'Résolue';
}

function formaterDate(iso: string): string {
  const date = new Date(iso);
  return date.toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
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
    padding: espacements.lg,
    marginBottom: espacements.md,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  carteResolue: {
    opacity: 0.7,
  },
  ligneEntete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  libelleAvecPastille: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  pastilleStatut: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  type: {
    fontSize: 13,
    fontWeight: '700',
    color: couleurs.texte,
    textTransform: 'uppercase',
  },
  statut: {
    fontSize: 12,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  site: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  message: {
    fontSize: 15,
    color: couleurs.texte,
    marginTop: espacements.sm,
  },
  date: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
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
