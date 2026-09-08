/**
 * Historique unifié du foyer (doc 13 §1) : timeline des recharges, paiements,
 * alertes et sessions de cuisson, filtrable, pull-to-refresh + pagination
 * "charger plus" (contrat `GET /api/historique`).
 */
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Icone, type NomIcone } from '../../components/icones';

import { BandeauSync } from '../../components/BandeauSync';
import { Bouton } from '../../components/Bouton';
import * as api from '../../api/endpoints';
import { executerAvecSource, historiquePageDemo } from '../../api/demo';
import { useDonnees } from '../../data/DonneesContext';
import type { StatutSync } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterDateRelative, formaterMontantFcfa } from '../../utils/date';
import type { EvenementHistorique, IconeEvenementHistorique, Pagination } from '../../api/types';

const PAR_PAGE = 15;

const FILTRES: { valeur: IconeEvenementHistorique | 'tous'; libelle: string }[] = [
  { valeur: 'tous', libelle: 'Tous' },
  { valeur: 'commande', libelle: 'Recharges' },
  { valeur: 'paiement', libelle: 'Paiements' },
  { valeur: 'alerte', libelle: 'Alertes' },
  { valeur: 'cuisson', libelle: 'Cuisson' },
];

const ICONES_EVENEMENT: Record<IconeEvenementHistorique, NomIcone> = {
  commande: 'commande',
  paiement: 'recu',
  alerte: 'alerte',
  cuisson: 'flamme',
};

export default function EcranHistorique() {
  const { siteActif } = useDonnees();

  const [filtre, setFiltre] = useState<IconeEvenementHistorique | 'tous'>('tous');
  const [evenements, setEvenements] = useState<EvenementHistorique[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const [chargementPlus, setChargementPlus] = useState(false);
  const [rafraichissement, setRafraichissement] = useState(false);

  const charger = useCallback(
    async (page: number, remplacer: boolean) => {
      const params = {
        site_uuid: siteActif?.uuid,
        type: filtre === 'tous' ? undefined : filtre,
        page,
        par_page: PAR_PAGE,
      };
      const { data, source } = await executerAvecSource(() => api.historique(params), historiquePageDemo(params));
      setEvenements((precedent) => (remplacer ? data.data : [...precedent, ...data.data]));
      setPagination(data.pagination);
      setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');
    },
    [siteActif?.uuid, filtre]
  );

  useEffect(() => {
    let annule = false;
    setChargementInitial(true);
    charger(1, true)
      .catch(() => {
        if (!annule) setStatutSync('hors_ligne');
      })
      .finally(() => {
        if (!annule) setChargementInitial(false);
      });
    return () => {
      annule = true;
    };
  }, [charger]);

  async function rafraichir() {
    setRafraichissement(true);
    try {
      await charger(1, true);
    } catch {
      setStatutSync('hors_ligne');
    } finally {
      setRafraichissement(false);
    }
  }

  async function chargerPlus() {
    if (!pagination || pagination.page >= pagination.total_pages) return;
    setChargementPlus(true);
    try {
      await charger(pagination.page + 1, false);
    } catch {
      setStatutSync('hors_ligne');
    } finally {
      setChargementPlus(false);
    }
  }

  const peutChargerPlus = pagination ? pagination.page < pagination.total_pages : false;

  return (
    <SafeAreaView style={styles.conteneur} edges={['top']}>
      <View style={styles.entete}>
        <Text style={styles.titre}>Historique</Text>
      </View>

      <View style={styles.filtres}>
        {FILTRES.map((f) => (
          <Pressable
            key={f.valeur}
            style={[styles.chip, filtre === f.valeur && styles.chipActif]}
            onPress={() => setFiltre(f.valeur)}
            accessibilityRole="button">
            <Text style={[styles.chipTexte, filtre === f.valeur && styles.chipTexteActif]}>{f.libelle}</Text>
          </Pressable>
        ))}
      </View>

      <FlatList
        data={evenements}
        keyExtractor={(item, index) => `${item.type}-${item.date}-${index}`}
        contentContainerStyle={styles.liste}
        refreshControl={
          <RefreshControl refreshing={rafraichissement} onRefresh={rafraichir} tintColor={couleurs.rouge} />
        }
        ListHeaderComponent={
          statutSync === 'hors_ligne' ? (
            <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
          ) : null
        }
        ListEmptyComponent={
          chargementInitial ? (
            <View style={styles.chargement}>
              <ActivityIndicator color={couleurs.rouge} />
            </View>
          ) : (
            <View style={styles.vide}>
              <Text style={styles.texteVide}>Aucun événement pour l'instant.</Text>
            </View>
          )
        }
        renderItem={({ item }) => <LigneEvenement evenement={item} />}
        ListFooterComponent={
          peutChargerPlus ? (
            <Bouton
              titre="Charger plus"
              variante="contour"
              enCours={chargementPlus}
              onPress={chargerPlus}
              style={styles.boutonPlus}
            />
          ) : null
        }
      />
    </SafeAreaView>
  );
}

function LigneEvenement({ evenement }: { evenement: EvenementHistorique }) {
  const icone = ICONES_EVENEMENT[evenement.icone] ?? 'cercle';
  return (
    <View style={styles.ligne}>
      <View style={styles.pastilleIcone}>
        <Icone nom={icone} taille={20} couleur={couleurs.rouge} />
      </View>
      <View style={styles.ligneTexte}>
        <Text style={styles.ligneTitre} numberOfLines={1}>
          {evenement.titre}
        </Text>
        <Text style={styles.ligneDetail} numberOfLines={2}>
          {evenement.detail}
        </Text>
        <Text style={styles.ligneDate}>{formaterDateRelative(evenement.date)}</Text>
      </View>
      {evenement.montant != null || evenement.statut ? (
        <View style={styles.ligneDroite}>
          {evenement.montant != null ? (
            <Text style={styles.ligneMontant}>{formaterMontantFcfa(evenement.montant)}</Text>
          ) : null}
          {evenement.statut ? (
            <Text style={styles.ligneStatut} numberOfLines={1}>
              {evenement.statut}
            </Text>
          ) : null}
        </View>
      ) : null}
    </View>
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
  filtres: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espacements.sm,
    paddingHorizontal: espacements.lg,
    paddingTop: espacements.md,
  },
  chip: {
    paddingHorizontal: espacements.md,
    paddingVertical: espacements.sm,
    borderRadius: rayons.rond,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    backgroundColor: couleurs.carte,
  },
  chipActif: {
    backgroundColor: couleurs.rouge,
    borderColor: couleurs.rouge,
  },
  chipTexte: {
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.texte,
  },
  chipTexteActif: {
    color: couleurs.blanc,
  },
  liste: {
    padding: espacements.lg,
  },
  chargement: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
  },
  vide: {
    paddingVertical: espacements.xxl,
    alignItems: 'center',
  },
  texteVide: {
    color: couleurs.texteDoux,
  },
  ligne: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: espacements.md,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.md,
    marginBottom: espacements.md,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  pastilleIcone: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
  },
  ligneTexte: {
    flex: 1,
    gap: 2,
  },
  ligneTitre: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  ligneDetail: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
  ligneDate: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  ligneDroite: {
    alignItems: 'flex-end',
    gap: 2,
  },
  ligneMontant: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.texte,
  },
  ligneStatut: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.rouge,
    textTransform: 'uppercase',
  },
  boutonPlus: {
    marginTop: espacements.sm,
  },
});
