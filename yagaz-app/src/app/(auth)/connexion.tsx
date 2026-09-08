import { useState } from 'react';
import { Link } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { useAuth } from '../../auth/AuthContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';

export default function EcranConnexion() {
  const { connecter, enCours } = useAuth();
  const [telephone, setTelephone] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  async function soumettre() {
    setErreur(null);
    if (!telephone.trim() || !motDePasse) {
      setErreur('Renseignez le téléphone et le mot de passe.');
      return;
    }
    try {
      await connecter(telephone.trim(), motDePasse);
    } catch {
      setErreur('Téléphone ou mot de passe incorrect.');
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.conteneur}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <LinearGradient
        colors={[couleurs.degradeDebut, couleurs.degradeFin]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.entete}>
        <SafeAreaView edges={['top']}>
          <Text style={styles.titre}>Yagaz</Text>
          <Text style={styles.sousTitre}>Suivez l'autonomie de votre gaz, à la maison.</Text>
        </SafeAreaView>
      </LinearGradient>

      <ScrollView contentContainerStyle={styles.corps} keyboardShouldPersistTaps="handled">
        <View style={styles.carte}>
          <Text style={styles.titreCarte}>Connexion</Text>

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
            placeholder="••••••••"
          />

          {erreur ? <Text style={styles.texteErreur}>{erreur}</Text> : null}

          <Bouton titre="Se connecter" onPress={soumettre} enCours={enCours} style={styles.bouton} />

          <View style={styles.lien}>
            <Link href="/(auth)/inscription" style={styles.texteLien}>
              Pas encore de compte ? Créer un foyer
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
    fontSize: 34,
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
  titreCarte: {
    fontSize: 20,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.lg,
  },
  texteErreur: {
    color: couleurs.danger,
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
