import { useState } from 'react';
import { Link } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { useAuth } from '../../auth/AuthContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

export default function EcranInscription() {
  const { inscrire, enCours } = useAuth();
  const [nom, setNom] = useState('');
  const [telephone, setTelephone] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  async function soumettre() {
    setErreur(null);
    if (!nom.trim() || !telephone.trim() || !motDePasse) {
      setErreur('Tous les champs sont requis.');
      return;
    }
    if (motDePasse.length < 6) {
      setErreur('Le mot de passe doit contenir au moins 6 caractères.');
      return;
    }
    try {
      await inscrire(nom.trim(), telephone.trim(), motDePasse);
    } catch {
      setErreur("Impossible de créer le compte. Vérifiez vos informations.");
    }
  }

  return (
    <KeyboardAvoidingView style={styles.conteneur} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.titre}>Créer un foyer</Text>
          <Text style={styles.sousTitre}>Un compte pour surveiller vos bouteilles de gaz.</Text>
        </SafeAreaView>
      </LinearGradient>

      <ScrollView contentContainerStyle={styles.corps} keyboardShouldPersistTaps="handled">
        <View style={styles.carte}>
          <Champ etiquette="Nom" valeur={nom} onChangeText={setNom} placeholder="Votre nom" />
          <Champ
            etiquette="Téléphone"
            valeur={telephone}
            onChangeText={setTelephone}
            clavier="phone-pad"
            placeholder="+221 77 123 45 67"
          />
          <Champ
            etiquette="Mot de passe"
            valeur={motDePasse}
            onChangeText={setMotDePasse}
            motDePasse
            placeholder="Au moins 6 caractères"
          />

          {erreur ? <Text style={styles.texteErreur}>{erreur}</Text> : null}

          <Bouton titre="Créer mon compte" onPress={soumettre} enCours={enCours} style={styles.bouton} />

          <View style={styles.lien}>
            <Link href="/(auth)/connexion" style={styles.texteLien}>
              Déjà un compte ? Se connecter
            </Link>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  entete: {
    paddingHorizontal: espacements.lg,
    paddingBottom: espacements.xxl,
    borderBottomLeftRadius: rayons.xl,
    borderBottomRightRadius: rayons.xl,
  },
  titre: {
    fontSize: 28,
    fontWeight: '800',
    color: couleurs.blanc,
    marginTop: espacements.md,
  },
  sousTitre: {
    fontSize: 15,
    color: couleurs.blanc,
    opacity: 0.9,
    marginTop: espacements.xs,
  },
  corps: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginTop: -espacements.xl,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 8 },
    elevation: 4,
  },
  texteErreur: {
    color: couleurs.rouge,
    fontSize: 14,
    marginBottom: espacements.md,
    textAlign: 'center',
  },
  bouton: {
    marginTop: espacements.sm,
  },
  lien: {
    marginTop: espacements.lg,
    alignSelf: 'center',
  },
  texteLien: {
    color: couleurs.rouge,
    fontWeight: '600',
    fontSize: 14,
  },
});
