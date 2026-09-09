import { useState } from 'react';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { EnteteEcran } from '../../components/EnteteEcran';
import { Icone } from '../../components/icones';
import { useAuth } from '../../auth/AuthContext';
import { majReglagesAlertes } from '../../api/endpoints';
import { MODE_DEMO } from '../../api/demo';
import { useDialogue } from '../../data/DialogueContext';
import { useEspace } from '../../espace/EspaceContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Canal } from '../../api/types';

const CANAUX: { valeur: Canal; libelle: string }[] = [
  { valeur: 'push', libelle: 'Notification push' },
  { valeur: 'sms', libelle: 'SMS' },
  { valeur: 'whatsapp', libelle: 'WhatsApp' },
];

export default function EcranReglages() {
  const { user, deconnecter } = useAuth();
  const { espacesDisponibles, reinitialiserChoix } = useEspace();
  const { alerter, confirmer } = useDialogue();
  const [canaux, setCanaux] = useState<Canal[]>(['push']);
  const [livreurHabituel, setLivreurHabituel] = useState('');
  const [enCours, setEnCours] = useState(false);

  function basculerCanal(canal: Canal) {
    setCanaux((precedent) =>
      precedent.includes(canal) ? precedent.filter((c) => c !== canal) : [...precedent, canal]
    );
  }

  async function enregistrer() {
    setEnCours(true);
    try {
      await majReglagesAlertes({
        canaux,
        livreur_habituel: livreurHabituel.trim() || undefined,
      });
      void alerter({ titre: 'Réglages enregistrés', message: 'Vos préférences ont été mises à jour.' });
    } catch {
      if (!MODE_DEMO) {
        void alerter({ titre: 'Erreur', message: "Impossible d'enregistrer les réglages pour l'instant." });
      } else {
        void alerter({ titre: 'Réglages enregistrés', message: 'Vos préférences ont été mises à jour (mode démo).' });
      }
    } finally {
      setEnCours(false);
    }
  }

  async function confirmerDeconnexion() {
    if (
      await confirmer({
        titre: 'Se déconnecter',
        message: 'Voulez-vous vraiment vous déconnecter ?',
        texteConfirmer: 'Se déconnecter',
        destructif: true,
      })
    ) {
      deconnecter();
    }
  }

  return (
    <View style={styles.conteneur}>
      <EnteteEcran titre="Réglages" />
      <ScrollView contentContainerStyle={styles.contenu}>
        {user ? (
          <View style={styles.carteCompte}>
            <Text style={styles.nomUtilisateur}>{user.nom}</Text>
            <Text style={styles.telephoneUtilisateur}>{user.telephone}</Text>
          </View>
        ) : null}

        <Text style={styles.sectionTitre}>Canaux d'alerte</Text>
        <View style={styles.carte}>
          {CANAUX.map((canal) => (
            <Pressable key={canal.valeur} style={styles.ligneCanal} onPress={() => basculerCanal(canal.valeur)}>
              <Text style={styles.libelleCanal}>{canal.libelle}</Text>
              <Switch
                value={canaux.includes(canal.valeur)}
                onValueChange={() => basculerCanal(canal.valeur)}
                trackColor={{ true: couleurs.rouge, false: couleurs.bordure }}
              />
            </Pressable>
          ))}
        </View>

        <Text style={styles.sectionTitre}>Matériel connecté</Text>
        <Pressable style={styles.carteLien} onPress={() => router.push('/materiels')}>
          <View style={styles.ligneLien}>
            <Icone nom="ecran" taille={20} couleur={couleurs.rouge} />
            <Text style={styles.texteLien}>Matériels (balance, capteur, écran)</Text>
          </View>
          <Icone nom="chevron" taille={18} couleur={couleurs.texteDoux} />
        </Pressable>

        <Text style={styles.sectionTitre}>Livreur habituel</Text>
        <View style={styles.carte}>
          <Champ
            etiquette="Téléphone du livreur (optionnel)"
            valeur={livreurHabituel}
            onChangeText={setLivreurHabituel}
            clavier="phone-pad"
            placeholder="+221 77 000 00 00"
            aide="Il sera notifié automatiquement en cas de seuil bas."
          />
        </View>

        <Bouton titre="Enregistrer les réglages" onPress={enregistrer} enCours={enCours} style={styles.boutonEnregistrer} />

        {espacesDisponibles.length > 1 ? (
          <Bouton
            titre="Changer d'espace"
            variante="contour"
            onPress={reinitialiserChoix}
            style={styles.boutonDeconnexion}
          />
        ) : null}

        <Bouton titre="Se déconnecter" variante="discret" onPress={confirmerDeconnexion} style={styles.boutonDeconnexion} />
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
  carteCompte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    marginBottom: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  nomUtilisateur: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
  },
  telephoneUtilisateur: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginTop: espacements.xs,
  },
  sectionTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    marginBottom: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  ligneCanal: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: espacements.sm,
  },
  libelleCanal: {
    fontSize: 15,
    color: couleurs.texte,
  },
  carteLien: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    marginBottom: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  ligneLien: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    flexShrink: 1,
  },
  texteLien: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.texte,
    flexShrink: 1,
  },
  boutonEnregistrer: {
    marginTop: espacements.sm,
  },
  boutonDeconnexion: {
    marginTop: espacements.lg,
  },
});
