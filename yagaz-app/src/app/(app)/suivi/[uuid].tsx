/**
 * Suivi d'une commande jusqu'à la livraison (contrat API
 * `GET /commandes/{uuid}/suivi`) : timeline verticale des étapes + bloc ETA
 * (estimation grossière, jamais de position GPS live - documenté côté API).
 * Câblage : appel direct + repli démo (`suiviCommandeDemo`, dérivé du statut
 * de la commande courante depuis `CommandesContext`).
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, Animated, Easing, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Icone } from '../../../components/icones';
import { useCommandes } from '../../../data/CommandesContext';
import { suiviCommande } from '../../../api/endpoints';
import { avecRepliDemo, formatsDemo, suiviCommandeDemo } from '../../../api/demo';
import { couleurs, espacements, rayons } from '../../../../theme/couleurs';
import { formaterDateRelative } from '../../../utils/date';
import type { Commande, EtapeSuivi, SuiviCommande } from '../../../api/types';

export default function EcranSuiviCommande() {
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const { commandes } = useCommandes();
  const commande = commandes.find((c) => c.uuid === uuid);

  const [suivi, setSuivi] = useState<SuiviCommande | null>(null);
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
        () => suiviCommande(uuid).then((r) => r.data),
        suiviCommandeDemo(commandeRepli)
      );
      setSuivi(data);
    } finally {
      setChargement(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uuid, commande?.statut, commande?.livraison?.statut]);

  useEffect(() => {
    charger();
  }, [charger]);

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView
        contentContainerStyle={styles.contenu}
        refreshControl={<RefreshControl refreshing={false} onRefresh={charger} tintColor={couleurs.rouge} />}>
        {chargement && !suivi ? (
          <View style={styles.chargement}>
            <ActivityIndicator color={couleurs.rouge} size="large" />
          </View>
        ) : !suivi ? (
          <Text style={styles.texteVide}>Suivi indisponible pour cette commande.</Text>
        ) : (
          <>
            <BlocEta suivi={suivi} />

            <Text style={styles.sectionTitre}>Étapes</Text>
            <View style={styles.carteTimeline}>
              {suivi.etapes.map((etape, index) => (
                <LigneEtape key={etape.cle} etape={etape} derniere={index === suivi.etapes.length - 1} />
              ))}
            </View>
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function BlocEta({ suivi }: { suivi: SuiviCommande }) {
  if (suivi.eta_minutes != null) {
    return (
      <View style={styles.carteEta}>
        <Icone nom="livraison" taille={28} couleur={couleurs.rouge} />
        <Text style={styles.chiffreEta}>~ {suivi.eta_minutes} min</Text>
        <Text style={styles.libelleEta}>
          Arrivée estimée{suivi.livraison.livreur ? ` · ${suivi.livraison.livreur}` : ''}
        </Text>
        <Text style={styles.texteEstimationEta}>Estimation, sans GPS</Text>
      </View>
    );
  }

  if (suivi.distance_km != null) {
    return (
      <View style={styles.carteEta}>
        <Icone nom="depot" taille={28} couleur={couleurs.rouge} />
        <Text style={styles.chiffreEta}>{suivi.distance_km.toFixed(1)} km</Text>
        <Text style={styles.libelleEta}>Dépôt à proximité</Text>
        <Text style={styles.texteEstimationEta}>Estimation, sans GPS</Text>
      </View>
    );
  }

  return (
    <View style={styles.carteEta}>
      <Icone nom="horloge" taille={28} couleur={couleurs.texteDoux} />
      <Text style={styles.libelleEtaNeutre}>Suivi de votre commande</Text>
    </View>
  );
}

function PastilleCourante() {
  const echelle = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(echelle, { toValue: 1.7, duration: 800, easing: Easing.out(Easing.ease), useNativeDriver: true }),
        Animated.timing(echelle, { toValue: 1, duration: 0, useNativeDriver: true }),
        Animated.delay(200),
      ])
    );
    animation.start();
    return () => animation.stop();
  }, [echelle]);

  return (
    <Animated.View
      style={[styles.haloCourant, { opacity: echelle.interpolate({ inputRange: [1, 1.7], outputRange: [0.5, 0] }), transform: [{ scale: echelle }] }]}
    />
  );
}

function LigneEtape({ etape, derniere }: { etape: EtapeSuivi; derniere: boolean }) {
  return (
    <View style={styles.ligneEtape}>
      <View style={styles.colonnePoint}>
        <View style={styles.pointEnveloppe}>
          {etape.courante ? <PastilleCourante /> : null}
          <View style={[styles.point, etape.atteinte && styles.pointAtteint, etape.courante && styles.pointCourant]}>
            {etape.atteinte && !etape.courante ? <Icone nom="check" taille={13} couleur={couleurs.blanc} /> : null}
          </View>
        </View>
        {!derniere ? <View style={[styles.trait, etape.atteinte && styles.traitAtteint]} /> : null}
      </View>
      <View style={styles.contenuEtape}>
        <Text
          style={[
            styles.libelleEtape,
            !etape.atteinte && styles.libelleEtapeAVenir,
            etape.courante && styles.libelleEtapeCourante,
          ]}>
          {etape.libelle}
        </Text>
        {etape.date ? <Text style={styles.dateEtape}>{formaterDateRelative(etape.date)}</Text> : null}
      </View>
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
  carteEta: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.xl,
    alignItems: 'center',
    gap: espacements.xs,
    marginBottom: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 10 },
    elevation: 4,
  },
  chiffreEta: {
    fontSize: 36,
    fontWeight: '800',
    color: couleurs.texte,
    marginTop: espacements.xs,
  },
  libelleEta: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.texteDoux,
    textAlign: 'center',
  },
  libelleEtaNeutre: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  texteEstimationEta: {
    fontSize: 12,
    color: couleurs.grisNeutre,
    marginTop: 2,
  },
  sectionTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  carteTimeline: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  ligneEtape: {
    flexDirection: 'row',
    gap: espacements.md,
  },
  colonnePoint: {
    alignItems: 'center',
    width: 28,
  },
  pointEnveloppe: {
    width: 28,
    height: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
  haloCourant: {
    position: 'absolute',
    top: 6,
    left: 6,
    width: 16,
    height: 16,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rouge,
  },
  point: {
    width: 16,
    height: 16,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.carte,
    borderWidth: 2,
    borderColor: couleurs.bordure,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pointAtteint: {
    backgroundColor: couleurs.rouge,
    borderColor: couleurs.rouge,
  },
  pointCourant: {
    width: 20,
    height: 20,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rouge,
    borderColor: couleurs.rouge,
  },
  trait: {
    width: 2,
    flex: 1,
    minHeight: 28,
    backgroundColor: couleurs.bordure,
    marginTop: 2,
    marginBottom: 2,
  },
  traitAtteint: {
    backgroundColor: couleurs.rouge,
  },
  contenuEtape: {
    flex: 1,
    paddingBottom: espacements.lg,
  },
  libelleEtape: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  libelleEtapeAVenir: {
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  libelleEtapeCourante: {
    color: couleurs.rouge,
  },
  dateEtape: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
});
