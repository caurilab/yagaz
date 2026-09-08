import { useMemo, useState } from 'react';
import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { useDepot } from '../../data/DepotContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Format } from '../../api/types';

/** Créer une proposition de livraison vers un foyer en niveau bas (UX §3, contrat §4). */
export default function EcranPropositionsDepot() {
  const { stocks, commandes, creerProposition } = useDepot();

  const formats = useMemo(() => stocks.map((s) => s.format), [stocks]);
  const [siteUuid, setSiteUuid] = useState('');
  const [formatChoisi, setFormatChoisi] = useState<Format | null>(formats[0] ?? null);
  const [quantite, setQuantite] = useState(1);
  const [enCours, setEnCours] = useState(false);

  const propositionsEnvoyees = useMemo(
    () => commandes.filter((c) => c.statut === 'proposee').sort((a, b) => (a.created_at < b.created_at ? 1 : -1)),
    [commandes]
  );

  async function envoyer() {
    if (!siteUuid.trim()) {
      Alert.alert('Site requis', 'Renseignez l\'identifiant du site du foyer visé.');
      return;
    }
    if (!formatChoisi) {
      Alert.alert('Format requis', 'Choisissez le format à proposer.');
      return;
    }
    setEnCours(true);
    try {
      await creerProposition({ site_uuid: siteUuid.trim(), format_id: formatChoisi.id, quantite });
      setSiteUuid('');
      setQuantite(1);
      Alert.alert('Proposition envoyée', 'Le foyer va recevoir la proposition et pourra l\'accepter ou la refuser.');
    } catch {
      Alert.alert('Erreur', "L'envoi de la proposition a échoué. Réessayez.");
    } finally {
      setEnCours(false);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <FlatList
        data={propositionsEnvoyees}
        keyExtractor={(c) => c.uuid}
        contentContainerStyle={styles.liste}
        ListHeaderComponent={
          <>
            <Text style={styles.titre}>Propositions</Text>
            <Text style={styles.sousTitre}>« Votre bouteille est presque vide, on vous livre ? »</Text>

            <View style={styles.carteFormulaire}>
              <Champ
                etiquette="Site du foyer (identifiant)"
                valeur={siteUuid}
                onChangeText={setSiteUuid}
                placeholder="site-uuid"
                aide="Communiqué par l'alerte de niveau bas du foyer."
              />

              <Text style={styles.etiquetteChamp}>Format</Text>
              <View style={styles.rangee}>
                {formats.map((format) => (
                  <Pressable
                    key={format.id}
                    style={[styles.chip, formatChoisi?.id === format.id && styles.chipActif]}
                    onPress={() => setFormatChoisi(format)}>
                    <Text style={[styles.chipTexte, formatChoisi?.id === format.id && styles.chipTexteActif]}>
                      {format.code}
                    </Text>
                  </Pressable>
                ))}
              </View>

              <Text style={styles.etiquetteChamp}>Quantité</Text>
              <View style={styles.rangeeQuantite}>
                <Pressable
                  style={styles.boutonQuantite}
                  onPress={() => setQuantite((q) => Math.max(1, q - 1))}
                  hitSlop={8}>
                  <Text style={styles.texteBoutonQuantite}>-</Text>
                </Pressable>
                <Text style={styles.chiffreQuantite}>{quantite}</Text>
                <Pressable style={styles.boutonQuantite} onPress={() => setQuantite((q) => q + 1)} hitSlop={8}>
                  <Text style={styles.texteBoutonQuantite}>+</Text>
                </Pressable>
              </View>

              <Bouton titre="Envoyer la proposition" onPress={envoyer} enCours={enCours} style={styles.boutonEnvoyer} />
            </View>

            {propositionsEnvoyees.length > 0 ? <Text style={styles.titreSection}>En attente de réponse</Text> : null}
          </>
        }
        ListEmptyComponent={null}
        renderItem={({ item }) => (
          <View style={styles.cartePropostion}>
            <Text style={styles.propositionDetails}>
              {item.format.code} · {item.quantite} bouteille(s)
            </Text>
            <Text style={styles.propositionSite}>Site : {item.site_uuid}</Text>
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
  carteFormulaire: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    borderWidth: 1,
    borderColor: couleurs.bordure,
  },
  etiquetteChamp: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texte,
    marginBottom: espacements.xs,
  },
  rangee: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espacements.sm,
    marginBottom: espacements.md,
  },
  chip: {
    minHeight: 48,
    paddingHorizontal: espacements.lg,
    justifyContent: 'center',
    borderRadius: rayons.rond,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    backgroundColor: couleurs.fond,
  },
  chipActif: {
    backgroundColor: couleurs.rouge,
    borderColor: couleurs.rouge,
  },
  chipTexte: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.texte,
  },
  chipTexteActif: {
    color: couleurs.blanc,
  },
  rangeeQuantite: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.lg,
    marginBottom: espacements.lg,
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
  boutonEnvoyer: {
    marginTop: espacements.xs,
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
    borderWidth: 1,
    borderColor: couleurs.bordure,
    borderLeftWidth: 4,
    borderLeftColor: couleurs.ambre,
    padding: espacements.md,
    marginBottom: espacements.sm,
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
