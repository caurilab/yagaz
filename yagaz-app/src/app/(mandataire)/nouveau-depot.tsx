import { useState } from 'react';
import { router } from 'expo-router';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { EnteteEcran } from '../../components/EnteteEcran';
import { useDialogue } from '../../data/DialogueContext';
import { useMandataire } from '../../data/MandataireContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

/**
 * Création d'un dépôt par le mandataire (provisioning descendant, doc 11) :
 * nom + zone, et optionnellement le gérant (nom + téléphone). Si le compte du
 * gérant est créé, un mot de passe temporaire est renvoyé une seule fois - on
 * l'affiche pour que le mandataire le communique au gérant.
 */
export default function EcranNouveauDepot() {
  const { creerDepot } = useMandataire();
  const { alerter } = useDialogue();

  const [nom, setNom] = useState('');
  const [zone, setZone] = useState('');
  const [gerantNom, setGerantNom] = useState('');
  const [gerantTelephone, setGerantTelephone] = useState('');
  const [enCours, setEnCours] = useState(false);

  async function creer() {
    const nomEpure = nom.trim();
    if (!nomEpure) {
      await alerter({ titre: 'Nom requis', message: 'Donnez un nom à ce dépôt (ex. « Dépôt Abobo »).' });
      return;
    }
    // Le gérant est optionnel, mais nom et téléphone vont de pair.
    const gNom = gerantNom.trim();
    const gTel = gerantTelephone.trim();
    if ((gNom && !gTel) || (!gNom && gTel)) {
      await alerter({ titre: 'Gérant incomplet', message: 'Renseignez le nom ET le téléphone du gérant, ou laissez les deux vides.' });
      return;
    }

    setEnCours(true);
    try {
      const gerant = await creerDepot({
        nom: nomEpure,
        zone: zone.trim() || undefined,
        gerant_nom: gNom || undefined,
        gerant_telephone: gTel || undefined,
      });

      if (gerant?.mot_de_passe_temporaire) {
        await alerter({
          titre: 'Dépôt créé',
          message:
            `Le compte du gérant ${gerant.nom} (${gerant.telephone}) a été créé.\n\n` +
            `Mot de passe temporaire à lui communiquer :\n${gerant.mot_de_passe_temporaire}\n\n` +
            `Il pourra le changer après sa première connexion.`,
        });
      } else if (gerant && !gerant.compte_cree) {
        await alerter({
          titre: 'Dépôt créé',
          message: `Le dépôt est créé et rattaché au gérant existant ${gerant.nom} (${gerant.telephone}).`,
        });
      } else {
        await alerter({ titre: 'Dépôt créé', message: 'Le dépôt a été ajouté à votre réseau.' });
      }
      router.back();
    } catch {
      await alerter({ titre: 'Erreur', message: "La création du dépôt a échoué. Réessayez." });
    } finally {
      setEnCours(false);
    }
  }

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Nouveau dépôt" retour />
      <ScrollView contentContainerStyle={styles.contenu} keyboardShouldPersistTaps="handled">
        <Text style={styles.sectionTitre}>Le dépôt</Text>
        <Champ etiquette="Nom du dépôt" valeur={nom} onChangeText={setNom} placeholder="Ex. Dépôt Abobo" />
        <Champ etiquette="Zone (optionnel)" valeur={zone} onChangeText={setZone} placeholder="Ex. Abobo" />

        <Text style={styles.sectionTitre}>Le gérant (optionnel)</Text>
        <Text style={styles.aide}>
          Ajoutez le gérant maintenant pour qu'il puisse gérer le stock et les commandes du dépôt. Vous
          pourrez aussi le faire plus tard.
        </Text>
        <Champ etiquette="Nom du gérant" valeur={gerantNom} onChangeText={setGerantNom} placeholder="Ex. Awa Koné" />
        <Champ
          etiquette="Téléphone du gérant"
          valeur={gerantTelephone}
          onChangeText={setGerantTelephone}
          clavier="phone-pad"
          placeholder="+225 07 00 00 00 00"
        />

        <Bouton titre="Créer le dépôt" onPress={creer} enCours={enCours} style={styles.boutonValider} />
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
  sectionTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginTop: espacements.lg,
    marginBottom: espacements.sm,
  },
  aide: {
    fontSize: 13,
    color: couleurs.texteDoux,
    lineHeight: 18,
    marginBottom: espacements.md,
  },
  boutonValider: {
    marginTop: espacements.xl,
    borderRadius: rayons.lg,
  },
});
