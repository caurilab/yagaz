/**
 * Page dédiée aux astuces gaz (sécurité, économie, gestion), accessible depuis
 * la carte "Astuce" de l'accueil et depuis la modale de sécurité au démarrage.
 * Les astuces sont regroupées par catégorie, la sécurité en tête (priorité
 * produit : le gaz, c'est d'abord une question de sécurité).
 */
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { EnteteEcran } from '../../components/EnteteEcran';
import { IllustrationAstuce } from '../../components/illustrations/IllustrationAstuce';
import { ASTUCES, LIBELLES_CATEGORIE_ASTUCE, type Astuce, type CategorieAstuce } from '../../data/astuces';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

/** Ordre d'affichage des catégories : la sécurité d'abord. */
const ORDRE_CATEGORIES: CategorieAstuce[] = ['securite', 'economie', 'gestion'];

export default function EcranAstuces() {
  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Astuces" sousTitre="Sécurité et bons gestes au quotidien" retour />

      <ScrollView contentContainerStyle={styles.contenu} showsVerticalScrollIndicator={false}>
        {ORDRE_CATEGORIES.map((categorie) => {
          const astuces = ASTUCES.filter((a) => a.categorie === categorie);
          if (astuces.length === 0) return null;
          return (
            <View key={categorie} style={styles.section}>
              <Text style={styles.sectionTitre}>{LIBELLES_CATEGORIE_ASTUCE[categorie]}</Text>
              {astuces.map((astuce) => (
                <CarteAstuceListe key={astuce.id} astuce={astuce} />
              ))}
            </View>
          );
        })}
      </ScrollView>
    </View>
  );
}

function CarteAstuceListe({ astuce }: { astuce: Astuce }) {
  return (
    <View style={styles.carte}>
      <View style={styles.illustration}>
        <IllustrationAstuce categorie={astuce.categorie} taille={64} />
      </View>
      <View style={styles.texteZone}>
        <Text style={styles.titre}>{astuce.titre}</Text>
        <Text style={styles.message}>{astuce.message}</Text>
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
  section: {
    marginBottom: espacements.lg,
  },
  sectionTitre: {
    fontSize: 16,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.md,
  },
  carte: {
    flexDirection: 'row',
    alignItems: 'center',
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
  illustration: {
    width: 64,
    height: 64,
  },
  texteZone: {
    flex: 1,
    minWidth: 0,
  },
  titre: {
    fontSize: 15,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.xs,
  },
  message: {
    fontSize: 13.5,
    lineHeight: 20,
    color: couleurs.texteDoux,
  },
});
