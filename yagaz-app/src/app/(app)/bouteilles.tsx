import { useState } from 'react';
import { router } from 'expo-router';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BadgeEtat } from '../../components/BadgeEtat';
import { Bouton } from '../../components/Bouton';
import { BouteilleGaz } from '../../components/BouteilleGaz';
import { Icone } from '../../components/icones';
import { useDialogue } from '../../data/DialogueContext';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterAutonomie } from '../../utils/niveau';
import { couleurPourFormat } from '../../utils/marque';
import type { Bouteille } from '../../api/types';

export default function EcranBouteilles() {
  const { siteActif, bouteilles, marques, activerBouteille } = useDonnees();
  const { alerter } = useDialogue();
  const [uuidEnCours, setUuidEnCours] = useState<string | null>(null);

  async function definirActive(bouteille: Bouteille) {
    setUuidEnCours(bouteille.uuid);
    try {
      await activerBouteille(bouteille.uuid);
    } catch {
      void alerter({
        titre: 'Action impossible',
        message: "Impossible de définir cette bouteille comme active pour l'instant.",
      });
    } finally {
      setUuidEnCours(null);
    }
  }

  return (
    <View style={styles.conteneur}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.titre}>Mes bouteilles</Text>
          <Text style={styles.sousTitre} numberOfLines={1}>
            {siteActif?.nom ?? 'Mon foyer'} - {bouteilles.length} bouteille{bouteilles.length > 1 ? 's' : ''}
          </Text>
        </SafeAreaView>
      </LinearGradient>

      <FlatList
        data={bouteilles}
        keyExtractor={(b) => b.uuid}
        contentContainerStyle={styles.liste}
        showsVerticalScrollIndicator={false}
        ListEmptyComponent={
          <View style={styles.vide}>
            <View style={styles.iconeVide}>
              <Icone nom="bouteille" taille={30} couleur={couleurs.rouge} />
            </View>
            <Text style={styles.titreVide}>Aucune bouteille</Text>
            <Text style={styles.texteVide}>
              Enregistrez votre première bouteille pour suivre son niveau et son autonomie.
            </Text>
          </View>
        }
        renderItem={({ item }) => {
          const couleurMarque = couleurPourFormat(item.format, marques);
          const actif = item.role_bouteille === 'active';
          const pct = Math.max(0, Math.min(100, item.niveau.niveau_pct));
          const gazKg = (item.niveau.gaz_g / 1000).toFixed(1).replace('.', ',');
          return (
            <View style={styles.carte}>
              <View style={styles.carteLigne}>
                <BouteilleGaz
                  couleur={couleurMarque}
                  code={item.format.code}
                  marqueNom={item.format.marque}
                  niveauPct={item.niveau.niveau_pct}
                  taille={96}
                  reflet={false}
                />
                <View style={styles.carteContenu}>
                  <View style={styles.ligneEntete}>
                    <View style={[styles.rolePill, actif ? styles.rolePillActive : styles.rolePillSecours]}>
                      {actif ? <View style={styles.pointActif} /> : null}
                      <Text style={[styles.roleTexte, actif && styles.roleTexteActif]}>
                        {actif ? 'Active' : 'Secours'}
                      </Text>
                    </View>
                    <BadgeEtat etat={item.niveau.etat} />
                  </View>

                  <View style={styles.chipMarque}>
                    <View style={[styles.pastille, { backgroundColor: couleurMarque }]} />
                    <Text style={styles.chipTexte} numberOfLines={1}>
                      {item.format.marque} - {item.format.code}
                    </Text>
                  </View>

                  <View style={styles.ligneNiveau}>
                    <Text style={styles.pourcent}>{item.niveau.niveau_pct}%</Text>
                    <Text style={styles.autonomie}>{formaterAutonomie(item.niveau.autonomie_heures)}</Text>
                  </View>

                  <View style={styles.barre}>
                    <LinearGradient
                      colors={[couleurs.degradeDebut, couleurs.rouge]}
                      start={{ x: 0, y: 0 }}
                      end={{ x: 1, y: 0 }}
                      style={[styles.barreRemplie, { width: `${pct}%` }]}
                    />
                  </View>

                  <View style={styles.ligneMeta}>
                    <Text style={styles.metaTexte}>Gaz restant : {gazKg} kg</Text>
                    {item.niveau.estimation ? (
                      <Text style={styles.badgeEstimation}>Estimation</Text>
                    ) : !item.niveau.frais ? (
                      <Text style={styles.badgeHorsLigne}>Hors ligne</Text>
                    ) : null}
                  </View>
                </View>
              </View>

              <View style={styles.actions}>
                {!actif ? (
                  <Bouton
                    titre="Définir comme active"
                    variante="contour"
                    enCours={uuidEnCours === item.uuid}
                    onPress={() => definirActive(item)}
                    style={styles.boutonAction}
                  />
                ) : null}
                <Bouton
                  titre="Détails"
                  variante="discret"
                  onPress={() => router.push(`/bouteille/${item.uuid}`)}
                  style={styles.boutonAction}
                />
              </View>
            </View>
          );
        }}
      />

      <View style={styles.piedDePage}>
        <Bouton titre="Enregistrer une bouteille" onPress={() => router.push('/enregistrer')} />
      </View>
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
    paddingBottom: espacements.lg,
    borderBottomLeftRadius: rayons.xl,
    borderBottomRightRadius: rayons.xl,
  },
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.blanc,
    marginTop: espacements.sm,
  },
  sousTitre: {
    fontSize: 14,
    color: couleurs.blanc,
    opacity: 0.9,
    marginTop: espacements.xs,
  },
  liste: {
    padding: espacements.lg,
    gap: espacements.md,
    flexGrow: 1,
  },
  vide: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: espacements.xxl,
  },
  iconeVide: {
    width: 72,
    height: 72,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: espacements.md,
  },
  titreVide: {
    fontSize: 18,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.xs,
  },
  texteVide: {
    color: couleurs.texteDoux,
    textAlign: 'center',
    maxWidth: 280,
    lineHeight: 20,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  carteLigne: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
  },
  carteContenu: {
    flex: 1,
  },
  ligneEntete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacements.sm,
  },
  rolePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: rayons.rond,
  },
  rolePillActive: {
    backgroundColor: couleurs.rougeClair,
  },
  rolePillSecours: {
    backgroundColor: couleurs.fond,
  },
  pointActif: {
    width: 7,
    height: 7,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rouge,
  },
  roleTexte: {
    fontSize: 12,
    fontWeight: '700',
    color: couleurs.texteDoux,
  },
  roleTexteActif: {
    color: couleurs.rouge,
  },
  chipMarque: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    marginTop: espacements.sm,
  },
  pastille: {
    width: 9,
    height: 9,
    borderRadius: rayons.rond,
  },
  chipTexte: {
    flex: 1,
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  ligneNiveau: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: espacements.sm,
    marginTop: espacements.xs,
  },
  pourcent: {
    fontSize: 28,
    fontWeight: '800',
    color: couleurs.texte,
    letterSpacing: -0.5,
  },
  autonomie: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  barre: {
    height: 8,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.fond,
    overflow: 'hidden',
    marginTop: espacements.sm,
  },
  barreRemplie: {
    height: '100%',
    borderRadius: rayons.rond,
  },
  ligneMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espacements.sm,
    marginTop: espacements.sm,
  },
  metaTexte: {
    fontSize: 12.5,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  badgeEstimation: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.ambre,
  },
  badgeHorsLigne: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.rouge,
  },
  actions: {
    gap: espacements.sm,
    marginTop: espacements.md,
  },
  boutonAction: {
    alignSelf: 'stretch',
  },
  piedDePage: {
    padding: espacements.lg,
    borderTopWidth: 1,
    borderTopColor: couleurs.bordure,
    backgroundColor: couleurs.fond,
  },
});
