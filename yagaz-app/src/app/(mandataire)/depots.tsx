import { LinearGradient } from 'expo-linear-gradient';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BandeauSync } from '../../components/BandeauSync';
import { useMandataire } from '../../data/MandataireContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { DepotConsolide, DepotStockConsolide } from '../../api/types';

/**
 * Vue rapide, en lecture, des dépôts du mandataire (doc 11 §1) : stock et
 * tensions par format, pour situer la journée. Le pilotage complet
 * (préparation des tournées) reste sur le dashboard web (doc 11 §4).
 */
export default function EcranDepotsMandataire() {
  const { orgNom, depots, statutSync, chargementInitial, rafraichir } = useMandataire();

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.texteEntete}>{orgNom ?? 'Mes dépôts'}</Text>
          <Text style={styles.sousTexteEntete}>Vue rapide - pilotage complet sur le web</Text>
        </SafeAreaView>
      </LinearGradient>

      <ScrollView
        style={styles.zoneContenu}
        contentContainerStyle={styles.contenuScroll}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={false} onRefresh={rafraichir} tintColor={couleurs.rouge} />}>
        {chargementInitial ? (
          <View style={styles.chargement}>
            <ActivityIndicator color={couleurs.rouge} size="large" />
            <Text style={styles.texteChargement}>Chargement des dépôts...</Text>
          </View>
        ) : (
          <>
            {statutSync === 'hors_ligne' ? (
              <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
            ) : null}
            {depots.length === 0 ? (
              <View style={styles.vide}>
                <Text style={styles.texteVide}>Aucun dépôt rattaché pour l'instant.</Text>
              </View>
            ) : (
              depots.map((depot) => <CarteDepot key={depot.uuid} depot={depot} />)
            )}
          </>
        )}
      </ScrollView>
    </View>
  );
}

function CarteDepot({ depot }: { depot: DepotConsolide }) {
  return (
    <View style={[styles.carte, depot.en_tension && styles.carteTension]}>
      <View style={styles.ligneEntete}>
        <Text style={styles.nomDepot} numberOfLines={1}>
          {depot.nom}
        </Text>
        {depot.en_tension ? <Etiquette texte="Tension" couleur={couleurs.ambre} /> : null}
      </View>
      {depot.zone ? <Text style={styles.zone}>{depot.zone}</Text> : null}
      <Text style={styles.derniereActivite}>
        {depot.derniere_activite_at ? `Dernière activité : ${formaterDate(depot.derniere_activite_at)}` : 'Aucune activité récente'}
      </Text>
      {depot.vides_a_recuperer > 0 ? (
        <Text style={styles.videsARecuperer}>{depot.vides_a_recuperer} vides à récupérer</Text>
      ) : null}

      <View style={styles.ligneFormats}>
        {depot.stocks.map((stock) => (
          <LigneFormat key={stock.format_id} stock={stock} />
        ))}
      </View>
    </View>
  );
}

function LigneFormat({ stock }: { stock: DepotStockConsolide }) {
  return (
    <View style={styles.formatLigne}>
      <Text style={styles.formatCode}>{stock.format_code}</Text>
      <View style={styles.formatChiffres}>
        <Text style={[styles.formatChiffre, stock.tension && styles.formatChiffreAlerte]}>{stock.pleines} pleines</Text>
        <Text style={[styles.formatChiffre, stock.tension && styles.formatChiffreAlerte]}>{stock.vides} vides</Text>
      </View>
    </View>
  );
}

function Etiquette({ texte, couleur }: { texte: string; couleur: string }) {
  return (
    <View style={[styles.etiquette, { backgroundColor: couleur }]}>
      <Text style={styles.texteEtiquette}>{texte}</Text>
    </View>
  );
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
    paddingBottom: espacements.xl,
    borderBottomLeftRadius: rayons.xl,
    borderBottomRightRadius: rayons.xl,
  },
  texteEntete: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.blanc,
    marginTop: espacements.sm,
  },
  sousTexteEntete: {
    fontSize: 14,
    color: couleurs.blanc,
    opacity: 0.9,
    marginTop: espacements.xs,
  },
  zoneContenu: {
    flex: 1,
  },
  contenuScroll: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
    gap: espacements.md,
  },
  chargement: {
    alignItems: 'center',
    paddingVertical: espacements.xxl,
    gap: espacements.md,
  },
  texteChargement: {
    color: couleurs.texteDoux,
    fontSize: 14,
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
    borderWidth: 1,
    borderColor: couleurs.bordure,
  },
  carteTension: {
    borderColor: couleurs.ambre,
    borderWidth: 2,
  },
  ligneEntete: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espacements.sm,
  },
  nomDepot: {
    fontSize: 17,
    fontWeight: '700',
    color: couleurs.texte,
    flexShrink: 1,
  },
  etiquette: {
    paddingHorizontal: espacements.sm,
    paddingVertical: 4,
    borderRadius: rayons.rond,
  },
  texteEtiquette: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  zone: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  derniereActivite: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  videsARecuperer: {
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.ambre,
    marginTop: espacements.xs,
  },
  ligneFormats: {
    marginTop: espacements.md,
    gap: espacements.sm,
  },
  formatLigne: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: couleurs.bordure,
    paddingTop: espacements.sm,
  },
  formatCode: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  formatChiffres: {
    flexDirection: 'row',
    gap: espacements.md,
  },
  formatChiffre: {
    fontSize: 13,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  formatChiffreAlerte: {
    color: couleurs.ambre,
  },
});
