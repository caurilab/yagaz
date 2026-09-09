import { useState } from 'react';
import * as Location from 'expo-location';
import { router } from 'expo-router';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { EnteteEcran } from '../../components/EnteteEcran';
import { Icone } from '../../components/icones';
import { useDialogue } from '../../data/DialogueContext';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

/** Création d'un lieu (site), avec position GPS optionnelle (UX §2 "Multi-sites"). */
export default function EcranNouveauLieu() {
  const { creerLieu } = useDonnees();
  const { alerter } = useDialogue();

  const [nom, setNom] = useState('');
  const [adresse, setAdresse] = useState('');
  const [lat, setLat] = useState<number | null>(null);
  const [lng, setLng] = useState<number | null>(null);
  const [recuperationPosition, setRecuperationPosition] = useState(false);
  const [creationEnCours, setCreationEnCours] = useState(false);

  async function utiliserPosition() {
    setRecuperationPosition(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        await alerter({
          titre: 'Localisation refusée',
          message: "Autorisez l'accès à la position dans les réglages de votre appareil pour utiliser cette fonction.",
        });
        return;
      }
      const position = await Location.getCurrentPositionAsync({});
      setLat(position.coords.latitude);
      setLng(position.coords.longitude);
    } catch {
      await alerter({
        titre: 'Position indisponible',
        message: "Impossible de récupérer votre position pour le moment. Réessayez.",
      });
    } finally {
      setRecuperationPosition(false);
    }
  }

  async function creer() {
    const nomEpure = nom.trim();
    if (!nomEpure) {
      await alerter({ titre: 'Nom requis', message: 'Donnez un nom à ce lieu, par exemple "Domicile" ou "Boutique".' });
      return;
    }

    setCreationEnCours(true);
    try {
      await creerLieu({
        nom: nomEpure,
        adresse: adresse.trim() || undefined,
        lat: lat ?? undefined,
        lng: lng ?? undefined,
      });
      router.back();
    } catch {
      await alerter({ titre: 'Erreur', message: "La création du lieu a échoué. Réessayez." });
    } finally {
      setCreationEnCours(false);
    }
  }

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Nouveau lieu" retour />
      <ScrollView contentContainerStyle={styles.contenu} keyboardShouldPersistTaps="handled">
        <Champ etiquette="Nom du lieu" valeur={nom} onChangeText={setNom} placeholder="Ex. Domicile, Boutique..." />
        <Champ
          etiquette="Adresse (optionnel)"
          valeur={adresse}
          onChangeText={setAdresse}
          placeholder="Ex. Sacré-Cœur, Dakar"
        />

        <Text style={styles.etapeTitre}>Position GPS</Text>
        <Bouton
          titre="Utiliser ma position"
          variante="contour"
          onPress={utiliserPosition}
          enCours={recuperationPosition}
        />

        {lat != null && lng != null ? (
          <View style={styles.cartePosition}>
            <Icone nom="localisation" taille={20} couleur={couleurs.vertOk} />
            <View style={styles.textesPosition}>
              <Text style={styles.positionTitre}>Position enregistrée</Text>
              <Text style={styles.positionCoords}>
                {lat.toFixed(4)}, {lng.toFixed(4)}
              </Text>
            </View>
          </View>
        ) : (
          <Text style={styles.texteRassurant}>
            Ajoutez votre position pour voir les dépôts proches. Le lieu peut aussi être créé sans
            position, et complété plus tard.
          </Text>
        )}

        <Bouton titre="Créer le lieu" onPress={creer} enCours={creationEnCours} style={styles.boutonValider} />
      </ScrollView>
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
  etapeTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  cartePosition: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    marginTop: espacements.md,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.md,
  },
  textesPosition: {
    flex: 1,
  },
  positionTitre: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  positionCoords: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  texteRassurant: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: espacements.sm,
    lineHeight: 18,
  },
  boutonValider: {
    marginTop: espacements.xl,
  },
});
