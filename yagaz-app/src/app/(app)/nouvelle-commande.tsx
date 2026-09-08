import { useEffect, useMemo, useState } from 'react';
import { router } from 'expo-router';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Bouton } from '../../components/Bouton';
import { Icone } from '../../components/icones';
import { SelecteurFormat } from '../../components/SelecteurFormat';
import { useCommandes } from '../../data/CommandesContext';
import { useDonnees } from '../../data/DonneesContext';
import { listerDepots } from '../../api/endpoints';
import { avecRepliDemo, depotsDemo } from '../../api/demo';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Depot, Format } from '../../api/types';

/** Commander une recharge : format, quantité, dépôt à proximité (UX §2 "Recharge", contrat §3). */
export default function EcranNouvelleCommande() {
  const { formats } = useDonnees();
  const { siteActif } = useDonnees();
  const { creer } = useCommandes();

  const codes = useMemo(() => Array.from(new Set(formats.map((f) => f.code))), [formats]);
  const [codeChoisi, setCodeChoisi] = useState<string | null>(codes[0] ?? null);
  const formatsDuCode = useMemo(() => formats.filter((f) => f.code === codeChoisi), [formats, codeChoisi]);
  const [formatChoisi, setFormatChoisi] = useState<Format | null>(formatsDuCode[0] ?? null);
  const [quantite, setQuantite] = useState(1);

  const [depots, setDepots] = useState<Depot[]>([]);
  const [chargementDepots, setChargementDepots] = useState(false);
  const [depotChoisi, setDepotChoisi] = useState<Depot | null>(null);
  const [enCours, setEnCours] = useState(false);

  function choisirCode(code: string) {
    setCodeChoisi(code);
    setFormatChoisi(formats.find((f) => f.code === code) ?? null);
    setDepotChoisi(null);
    setDepots([]);
  }

  useEffect(() => {
    if (!formatChoisi || !siteActif?.lat || !siteActif?.lng) {
      setDepots([]);
      return;
    }
    setChargementDepots(true);
    avecRepliDemo(() => listerDepots(siteActif.lat!, siteActif.lng!, formatChoisi.id).then((r) => r.data), depotsDemo)
      .then((data) => {
        setDepots(data);
        setDepotChoisi((precedent) => precedent ?? data[0] ?? null);
      })
      .finally(() => setChargementDepots(false));
  }, [formatChoisi, siteActif?.lat, siteActif?.lng]);

  async function valider() {
    if (!siteActif) {
      Alert.alert('Site requis', 'Choisissez un site avant de commander.');
      return;
    }
    if (!formatChoisi) {
      Alert.alert('Format requis', 'Choisissez le format à commander.');
      return;
    }
    if (!depotChoisi) {
      Alert.alert('Dépôt requis', 'Choisissez un dépôt pour la livraison.');
      return;
    }
    setEnCours(true);
    try {
      await creer({
        site_uuid: siteActif.uuid,
        format_id: formatChoisi.id,
        quantite,
        depot_uuid: depotChoisi.uuid,
      });
      router.back();
    } catch {
      Alert.alert('Erreur', 'La commande a échoué. Réessayez.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.contenu} keyboardShouldPersistTaps="handled">
        <Text style={styles.etapeTitre}>1. Format</Text>
        <SelecteurFormat codes={codes} codeChoisi={codeChoisi} onChoisir={choisirCode} />

        <Text style={styles.etapeTitre}>2. Quantité</Text>
        <View style={styles.rangeeQuantite}>
          <Pressable style={styles.boutonQuantite} onPress={() => setQuantite((q) => Math.max(1, q - 1))} hitSlop={8}>
            <Text style={styles.texteBoutonQuantite}>-</Text>
          </Pressable>
          <Text style={styles.chiffreQuantite}>{quantite}</Text>
          <Pressable style={styles.boutonQuantite} onPress={() => setQuantite((q) => q + 1)} hitSlop={8}>
            <Text style={styles.texteBoutonQuantite}>+</Text>
          </Pressable>
        </View>

        <Text style={styles.etapeTitre}>3. Dépôt</Text>
        {!siteActif?.lat || !siteActif?.lng ? (
          <Text style={styles.texteRassurant}>
            Ce site n'a pas d'adresse géolocalisée : ajoutez-en une pour voir les dépôts à proximité.
          </Text>
        ) : chargementDepots ? (
          <ActivityIndicator color={couleurs.rouge} />
        ) : depots.length === 0 ? (
          <Text style={styles.texteVide}>Aucun dépôt disponible pour ce format à proximité.</Text>
        ) : (
          <View style={styles.listeDepots}>
            <Text style={styles.texteCompteurDepots}>
              {depots.length} dépôt{depots.length > 1 ? 's' : ''} à proximité
            </Text>
            {depots.map((depot) => (
              <Pressable
                key={depot.uuid}
                style={[
                  styles.carteDepot,
                  depotChoisi?.uuid === depot.uuid && styles.carteDepotActive,
                  !depot.disponible && styles.carteDepotIndisponible,
                ]}
                onPress={() => setDepotChoisi(depot)}
                disabled={!depot.disponible}>
                <View style={styles.zoneNomDepot}>
                  <View style={styles.ligneNomDepot}>
                    <Icone nom="localisation" taille={16} couleur={couleurs.rouge} />
                    <Text style={styles.nomDepot}>{depot.nom}</Text>
                  </View>
                  {depot.adresse ? <Text style={styles.adresseDepot}>{depot.adresse}</Text> : null}
                  {!depot.disponible ? <Text style={styles.texteIndisponible}>Indisponible pour l'instant</Text> : null}
                </View>
                <View style={styles.zoneDistanceDepot}>
                  <Text style={styles.distanceDepot}>{depot.distance_km.toFixed(1)} km</Text>
                  {depotChoisi?.uuid === depot.uuid ? (
                    <Icone nom="check" taille={20} couleur={couleurs.rouge} />
                  ) : null}
                </View>
              </Pressable>
            ))}
          </View>
        )}
      </ScrollView>

      <View style={styles.piedDePage}>
        <Bouton titre="Commander" onPress={valider} enCours={enCours} />
      </View>
    </SafeAreaView>
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
  etapeTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginTop: espacements.lg,
    marginBottom: espacements.sm,
  },
  rangeeQuantite: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.lg,
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
  texteRassurant: {
    fontSize: 13,
    color: couleurs.texteDoux,
    lineHeight: 18,
  },
  texteVide: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
  listeDepots: {
    gap: espacements.sm,
  },
  texteCompteurDepots: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginBottom: espacements.xs,
  },
  carteDepot: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: couleurs.carte,
    borderRadius: rayons.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.md,
  },
  carteDepotActive: {
    borderColor: couleurs.rouge,
    borderWidth: 2,
  },
  carteDepotIndisponible: {
    opacity: 0.5,
  },
  zoneNomDepot: {
    flex: 1,
  },
  ligneNomDepot: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  nomDepot: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  adresseDepot: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  texteIndisponible: {
    fontSize: 12,
    color: couleurs.rouge,
    fontWeight: '600',
    marginTop: 2,
  },
  zoneDistanceDepot: {
    alignItems: 'flex-end',
    gap: espacements.xs,
  },
  distanceDepot: {
    fontSize: 13,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  piedDePage: {
    padding: espacements.lg,
    borderTopWidth: 1,
    borderTopColor: couleurs.bordure,
    backgroundColor: couleurs.fond,
  },
});
