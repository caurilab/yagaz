import { LinearGradient } from 'expo-linear-gradient';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BandeauSync } from '../../components/BandeauSync';
import { useDepot } from '../../data/DepotContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { tensionStock } from '../../utils/stock';
import type { StockFormat } from '../../api/types';

/** Stock en un coup d'oeil : pleines/vides par format, tension visuelle (UX §3). */
export default function EcranStockDepot() {
  const { orgNom, stocks, statutSync, chargementInitial, rafraichir, ajusterStock } = useDepot();

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.texteEntete}>{orgNom ?? 'Mon dépôt'}</Text>
          <Text style={styles.sousTexteEntete}>Stock du comptoir</Text>
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
            <Text style={styles.texteChargement}>Chargement du stock...</Text>
          </View>
        ) : (
          <>
            {statutSync === 'hors_ligne' ? (
              <BandeauSync texte="Connexion indisponible - dernières valeurs connues affichées" />
            ) : null}
            {stocks.length === 0 ? (
              <View style={styles.vide}>
                <Text style={styles.texteVide}>Aucun format suivi pour l'instant.</Text>
              </View>
            ) : (
              stocks.map((stock) => (
                <CarteStock key={stock.format.id} stock={stock} onAjuster={ajusterStock} />
              ))
            )}
          </>
        )}
      </ScrollView>
    </View>
  );
}

function CarteStock({
  stock,
  onAjuster,
}: {
  stock: StockFormat;
  onAjuster: (formatId: number, corps: { pleines?: number; vides?: number }) => void;
}) {
  const { stockBas, videsAccumules } = tensionStock(stock);

  return (
    <View style={[styles.carte, (stockBas || videsAccumules) && styles.carteTension]}>
      <View style={styles.ligneEntete}>
        <Text style={styles.formatTitre}>
          {stock.format.code} - {stock.format.marque}
        </Text>
        {stockBas ? <Etiquette texte="Stock bas" couleur={couleurs.rouge} /> : null}
        {videsAccumules ? <Etiquette texte="Vides à retourner" couleur={couleurs.ambre} /> : null}
      </View>

      <View style={styles.rangeeChiffres}>
        <BlocChiffre
          libelle="Pleines"
          valeur={stock.pleines}
          couleur={couleurs.vertOk}
          onMoins={() => onAjuster(stock.format.id, { pleines: Math.max(0, stock.pleines - 1) })}
          onPlus={() => onAjuster(stock.format.id, { pleines: stock.pleines + 1 })}
        />
        <BlocChiffre
          libelle="Vides"
          valeur={stock.vides}
          couleur={couleurs.ambre}
          onMoins={() => onAjuster(stock.format.id, { vides: Math.max(0, stock.vides - 1) })}
          onPlus={() => onAjuster(stock.format.id, { vides: stock.vides + 1 })}
        />
      </View>

      <Text style={styles.texteSeuil}>Seuil bas : {stock.seuil_plein_bas} pleines</Text>
    </View>
  );
}

function BlocChiffre({
  libelle,
  valeur,
  couleur,
  onMoins,
  onPlus,
}: {
  libelle: string;
  valeur: number;
  couleur: string;
  onMoins: () => void;
  onPlus: () => void;
}) {
  return (
    <View style={styles.blocChiffre}>
      <Text style={styles.libelleChiffre}>{libelle}</Text>
      <View style={styles.rangeeAjustement}>
        <Pressable style={styles.boutonAjuster} onPress={onMoins} hitSlop={8} accessibilityRole="button">
          <Text style={styles.texteBoutonAjuster}>-</Text>
        </Pressable>
        <Text style={[styles.chiffre, { color: couleur }]}>{valeur}</Text>
        <Pressable style={styles.boutonAjuster} onPress={onPlus} hitSlop={8} accessibilityRole="button">
          <Text style={styles.texteBoutonAjuster}>+</Text>
        </Pressable>
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
    gap: espacements.sm,
  },
  formatTitre: {
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
  rangeeChiffres: {
    flexDirection: 'row',
    gap: espacements.lg,
    marginTop: espacements.md,
  },
  blocChiffre: {
    flex: 1,
    alignItems: 'center',
  },
  libelleChiffre: {
    fontSize: 13,
    color: couleurs.texteDoux,
    fontWeight: '600',
    marginBottom: espacements.xs,
  },
  rangeeAjustement: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
  },
  boutonAjuster: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
  },
  texteBoutonAjuster: {
    fontSize: 20,
    fontWeight: '800',
    color: couleurs.rouge,
  },
  chiffre: {
    fontSize: 30,
    fontWeight: '800',
    minWidth: 44,
    textAlign: 'center',
  },
  texteSeuil: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: espacements.md,
    textAlign: 'center',
  },
});
