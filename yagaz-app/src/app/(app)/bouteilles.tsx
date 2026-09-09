import { useState } from 'react';
import { router } from 'expo-router';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { BadgeEtat } from '../../components/BadgeEtat';
import { BarreProgression } from '../../components/BarreProgression';
import { Bouton } from '../../components/Bouton';
import { BouteilleGaz } from '../../components/BouteilleGaz';
import { EnteteEcran } from '../../components/EnteteEcran';
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
      <EnteteEcran
        titre="Mes bouteilles"
        sousTitre={siteActif?.nom}
        bouton={{ titre: 'Ajouter une bouteille', icone: 'plus', onPress: () => router.push('/enregistrer') }}
      />

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
            <Bouton titre="Enregistrer une bouteille" onPress={() => router.push('/enregistrer')} style={styles.boutonVide} />
          </View>
        }
        renderItem={({ item }) => {
          const couleurMarque = couleurPourFormat(item.format, marques);
          const actif = item.role_bouteille === 'active';
          const pct = Math.max(0, Math.min(100, item.niveau.niveau_pct));
          return (
            <Pressable style={styles.carte} onPress={() => router.push(`/bouteille/${item.uuid}`)}>
              <BouteilleGaz
                couleur={couleurMarque}
                code={item.format.code}
                marqueNom={item.format.marque}
                niveauPct={item.niveau.niveau_pct}
                taille={74}
                reflet={false}
              />
              <View style={styles.contenu}>
                <View style={styles.ligneHaut}>
                  <View style={[styles.rolePill, actif ? styles.rolePillActive : styles.rolePillSecours]}>
                    {actif ? <View style={styles.pointActif} /> : null}
                    <Text style={[styles.roleTexte, actif && styles.roleTexteActif]}>
                      {actif ? 'Active' : 'Secours'}
                    </Text>
                  </View>
                  {actif ? (
                    <Icone nom="chevron" taille={18} couleur={couleurs.grisNeutre} />
                  ) : (
                    <Pressable
                      style={styles.boutonActiver}
                      onPress={() => definirActive(item)}
                      hitSlop={8}
                      accessibilityRole="button">
                      {uuidEnCours === item.uuid ? (
                        <ActivityIndicator size="small" color={couleurs.rouge} />
                      ) : (
                        <Text style={styles.texteActiver}>Activer</Text>
                      )}
                    </Pressable>
                  )}
                </View>

                <Text style={styles.marque} numberOfLines={1}>
                  {item.format.marque} - {item.format.code}
                </Text>

                <View style={styles.ligneNiveau}>
                  <Text style={styles.pourcent}>{item.niveau.niveau_pct}%</Text>
                  <BadgeEtat etat={item.niveau.etat} />
                  <Text style={styles.autonomie} numberOfLines={1}>
                    {formaterAutonomie(item.niveau.autonomie_heures)}
                  </Text>
                </View>

                <View style={styles.barreConteneur}>
                  <BarreProgression pct={pct} hauteur={8} />
                </View>
              </View>
            </Pressable>
          );
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
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
    marginBottom: espacements.lg,
  },
  boutonVide: {
    alignSelf: 'stretch',
    marginHorizontal: espacements.lg,
  },
  carte: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    paddingVertical: espacements.md,
    paddingHorizontal: espacements.md,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  contenu: {
    flex: 1,
  },
  ligneHaut: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espacements.sm,
  },
  rolePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 9,
    paddingVertical: 3,
    borderRadius: rayons.rond,
  },
  rolePillActive: {
    backgroundColor: couleurs.rougeClair,
  },
  rolePillSecours: {
    backgroundColor: couleurs.fond,
  },
  pointActif: {
    width: 6,
    height: 6,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rouge,
  },
  roleTexte: {
    fontSize: 11.5,
    fontWeight: '700',
    color: couleurs.texteDoux,
  },
  roleTexteActif: {
    color: couleurs.rouge,
  },
  boutonActiver: {
    paddingHorizontal: espacements.sm,
    paddingVertical: 5,
    borderRadius: rayons.rond,
    borderWidth: 1.5,
    borderColor: couleurs.rouge,
    minWidth: 66,
    alignItems: 'center',
  },
  texteActiver: {
    fontSize: 12.5,
    fontWeight: '700',
    color: couleurs.rouge,
  },
  marque: {
    fontSize: 12.5,
    fontWeight: '600',
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  ligneNiveau: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    marginTop: 2,
  },
  pourcent: {
    fontSize: 34,
    fontWeight: '800',
    color: couleurs.texte,
    letterSpacing: -1,
  },
  autonomie: {
    flex: 1,
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  barreConteneur: {
    marginTop: espacements.sm,
  },
});
