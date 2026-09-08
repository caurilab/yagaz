import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { useAuth } from '../../auth/AuthContext';
import { majReglagesAlertes } from '../../api/endpoints';
import { MODE_DEMO } from '../../api/demo';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import type { Canal } from '../../api/types';

const CANAUX: { valeur: Canal; libelle: string }[] = [
  { valeur: 'push', libelle: 'Notification push' },
  { valeur: 'sms', libelle: 'SMS' },
  { valeur: 'whatsapp', libelle: 'WhatsApp' },
];

export default function EcranReglages() {
  const { user, deconnecter } = useAuth();
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
      Alert.alert('Réglages enregistrés', 'Vos préférences ont été mises à jour.');
    } catch {
      if (!MODE_DEMO) {
        Alert.alert('Erreur', "Impossible d'enregistrer les réglages pour l'instant.");
      } else {
        Alert.alert('Réglages enregistrés', 'Vos préférences ont été mises à jour (mode démo).');
      }
    } finally {
      setEnCours(false);
    }
  }

  function confirmerDeconnexion() {
    Alert.alert('Se déconnecter', 'Voulez-vous vraiment vous déconnecter ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Se déconnecter', style: 'destructive', onPress: () => deconnecter() },
    ]);
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['top', 'bottom']}>
      <ScrollView contentContainerStyle={styles.contenu}>
        <Text style={styles.titre}>Réglages</Text>

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

        <Bouton titre="Se déconnecter" variante="discret" onPress={confirmerDeconnexion} style={styles.boutonDeconnexion} />
      </ScrollView>
    </SafeAreaView>
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
  titre: {
    fontSize: 26,
    fontWeight: '800',
    color: couleurs.texte,
    marginBottom: espacements.lg,
  },
  carteCompte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginBottom: espacements.lg,
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
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginBottom: espacements.lg,
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
  boutonEnregistrer: {
    marginTop: espacements.sm,
  },
  boutonDeconnexion: {
    marginTop: espacements.lg,
  },
});
