import { useState } from 'react';
import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { useDepot } from '../../data/DepotContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Reappro } from '../../api/types';

/**
 * Réappros préparés par la plateforme pour ce dépôt (ADR 0009 §D) : le
 * pendant dépôt de la boucle montante - la plateforme prépare, le dépôt
 * ajuste et confirme avant que la demande n'apparaisse au mandataire.
 */
export default function EcranReapprosDepot() {
  const { reappros, confirmerReappro } = useDepot();
  const [quantites, setQuantites] = useState<Record<string, number>>({});
  const [uuidEnCours, setUuidEnCours] = useState<string | null>(null);

  function quantiteAffichee(reappro: Reappro): number {
    return quantites[reappro.uuid] ?? reappro.quantite;
  }

  function ajuster(reappro: Reappro, delta: number) {
    setQuantites((precedent) => ({
      ...precedent,
      [reappro.uuid]: Math.max(1, quantiteAffichee(reappro) + delta),
    }));
  }

  async function confirmer(reappro: Reappro) {
    const quantiteAjustee = quantites[reappro.uuid];
    setUuidEnCours(reappro.uuid);
    try {
      await confirmerReappro(
        reappro.uuid,
        quantiteAjustee != null && quantiteAjustee !== reappro.quantite ? { quantite: quantiteAjustee } : undefined
      );
    } catch {
      Alert.alert('Action impossible', 'Impossible de confirmer ce réappro pour le moment.');
    } finally {
      setUuidEnCours(null);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <View style={styles.entete}>
        <Text style={styles.titre}>Réappros</Text>
        <Text style={styles.sousTitre}>Proposés par la plateforme depuis l'état du stock</Text>
      </View>

      <FlatList
        data={reappros}
        keyExtractor={(r) => r.uuid}
        contentContainerStyle={styles.liste}
        ListEmptyComponent={
          <View style={styles.vide}>
            <Text style={styles.texteVide}>Aucun réappro proposé pour l'instant.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={styles.carte}>
            <Text style={styles.formatTitre}>
              {item.format.code} - {item.format.marque}
            </Text>

            <View style={styles.rangeeQuantite}>
              <Pressable style={styles.boutonQuantite} onPress={() => ajuster(item, -1)} hitSlop={8}>
                <Text style={styles.texteBoutonQuantite}>-</Text>
              </Pressable>
              <Text style={styles.chiffreQuantite}>{quantiteAffichee(item)}</Text>
              <Pressable style={styles.boutonQuantite} onPress={() => ajuster(item, 1)} hitSlop={8}>
                <Text style={styles.texteBoutonQuantite}>+</Text>
              </Pressable>
            </View>

            <Bouton
              titre="Confirmer"
              enCours={uuidEnCours === item.uuid}
              onPress={() => confirmer(item)}
              style={styles.boutonConfirmer}
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
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.lg,
    marginBottom: espacements.md,
  },
  formatTitre: {
    fontSize: 17,
    fontWeight: '700',
    color: couleurs.texte,
  },
  rangeeQuantite: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.lg,
    marginTop: espacements.md,
    marginBottom: espacements.md,
  },
  boutonQuantite: {
    width: 48,
    height: 48,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
  },
  texteBoutonQuantite: {
    fontSize: 22,
    fontWeight: '800',
    color: couleurs.rouge,
  },
  chiffreQuantite: {
    fontSize: 24,
    fontWeight: '800',
    color: couleurs.texte,
    minWidth: 32,
    textAlign: 'center',
  },
  boutonConfirmer: {
    marginTop: espacements.xs,
  },
});
