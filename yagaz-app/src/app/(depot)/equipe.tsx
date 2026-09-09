import { useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { EnteteEcran } from '../../components/EnteteEcran';
import { Icone } from '../../components/icones';
import { useDepot } from '../../data/DepotContext';
import { useDialogue } from '../../data/DialogueContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

/**
 * Équipe du dépôt (provisioning descendant, doc 11) : liste des livreurs
 * rattachés et ajout d'un livreur par téléphone. Si le compte est créé, un mot
 * de passe temporaire est renvoyé une fois - on l'affiche pour le communiquer.
 */
export default function EcranEquipeDepot() {
  const { livreurs, ajouterLivreur } = useDepot();
  const { alerter } = useDialogue();

  const [nom, setNom] = useState('');
  const [telephone, setTelephone] = useState('');
  const [enCours, setEnCours] = useState(false);

  async function ajouter() {
    const nomEpure = nom.trim();
    const telEpure = telephone.trim();
    if (!nomEpure || !telEpure) {
      await alerter({ titre: 'Champs requis', message: 'Renseignez le nom et le téléphone du livreur.' });
      return;
    }

    setEnCours(true);
    try {
      const resultat = await ajouterLivreur({ nom: nomEpure, telephone: telEpure });
      setNom('');
      setTelephone('');
      if (resultat.mot_de_passe_temporaire) {
        await alerter({
          titre: 'Livreur ajouté',
          message:
            `Le compte de ${nomEpure} a été créé.\n\n` +
            `Mot de passe temporaire à lui communiquer :\n${resultat.mot_de_passe_temporaire}\n\n` +
            `Il pourra le changer après sa première connexion.`,
        });
      } else if (!resultat.compte_cree) {
        await alerter({ titre: 'Livreur ajouté', message: `${nomEpure} (compte existant) a été rattaché à votre dépôt.` });
      } else {
        await alerter({ titre: 'Livreur ajouté', message: `${nomEpure} a été ajouté à votre équipe.` });
      }
    } catch {
      await alerter({ titre: 'Erreur', message: "L'ajout du livreur a échoué. Réessayez." });
    } finally {
      setEnCours(false);
    }
  }

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Mon équipe" sousTitre={`${livreurs.length} livreur${livreurs.length > 1 ? 's' : ''}`} retour />
      <ScrollView contentContainerStyle={styles.contenu} keyboardShouldPersistTaps="handled">
        <Text style={styles.sectionTitre}>Ajouter un livreur</Text>
        <View style={styles.carteForm}>
          <Champ etiquette="Nom du livreur" valeur={nom} onChangeText={setNom} placeholder="Ex. Moussa Ndiaye" />
          <Champ
            etiquette="Téléphone"
            valeur={telephone}
            onChangeText={setTelephone}
            clavier="phone-pad"
            placeholder="+225 07 00 00 00 00"
          />
          <Bouton titre="Ajouter le livreur" onPress={ajouter} enCours={enCours} style={styles.boutonAjouter} />
        </View>

        <Text style={styles.sectionTitre}>Livreurs rattachés</Text>
        {livreurs.length === 0 ? (
          <Text style={styles.texteVide}>Aucun livreur pour l'instant.</Text>
        ) : (
          livreurs.map((livreur) => (
            <View key={livreur.uuid} style={styles.ligneLivreur}>
              <View style={styles.pastille}>
                <Icone nom="livraison" taille={18} couleur={couleurs.rouge} />
              </View>
              <Text style={styles.nomLivreur}>{livreur.nom}</Text>
            </View>
          ))
        )}
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
    marginTop: espacements.md,
    marginBottom: espacements.sm,
  },
  carteForm: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    gap: espacements.sm,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  boutonAjouter: {
    marginTop: espacements.sm,
  },
  texteVide: {
    color: couleurs.texteDoux,
    paddingVertical: espacements.md,
  },
  ligneLivreur: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.md,
    marginBottom: espacements.sm,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  pastille: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
  },
  nomLivreur: {
    fontSize: 16,
    fontWeight: '600',
    color: couleurs.texte,
  },
});
