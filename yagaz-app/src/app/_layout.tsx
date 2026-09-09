import { useEffect } from 'react';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider, useAuth } from '../auth/AuthContext';
import { DialogueProvider } from '../data/DialogueContext';
import { DonneesProvider } from '../data/DonneesContext';
import { CommandesProvider } from '../data/CommandesContext';
import { EspaceProvider, useEspace } from '../espace/EspaceContext';

SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <DialogueProvider>
        <AuthProvider>
          <EspaceProvider>
            <DonneesProvider>
              <CommandesProvider>
                <StatusBar style="light" />
                <NavigationRacine />
              </CommandesProvider>
            </DonneesProvider>
          </EspaceProvider>
        </AuthProvider>
      </DialogueProvider>
    </SafeAreaProvider>
  );
}

/**
 * Bascule (auth) / (app) / (depot) / (livreur) / (mandataire) / choisir-espace
 * selon l'état de connexion et l'espace choisi (contrat 10 §1, doc 11 §1
 * pour le mandataire). Le splash natif
 * reste affiché tant que le jeton persistant et les rôles n'ont pas été
 * relus (jamais de flash d'écran de connexion pour un utilisateur déjà
 * connecté, ni de flash du sélecteur d'espace pour un foyer simple).
 */
function NavigationRacine() {
  const { estConnecte, chargementInitial } = useAuth();
  const { espaceActif, chargement: chargementEspace } = useEspace();

  const pret = !chargementInitial && (!estConnecte || !chargementEspace);

  useEffect(() => {
    if (pret) {
      SplashScreen.hideAsync();
    }
  }, [pret]);

  if (!pret) {
    return null;
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Protected guard={estConnecte && espaceActif === 'foyer'}>
        <Stack.Screen name="(app)" />
      </Stack.Protected>
      <Stack.Protected guard={estConnecte && espaceActif === 'depot'}>
        <Stack.Screen name="(depot)" />
      </Stack.Protected>
      <Stack.Protected guard={estConnecte && espaceActif === 'livreur'}>
        <Stack.Screen name="(livreur)" />
      </Stack.Protected>
      <Stack.Protected guard={estConnecte && espaceActif === 'mandataire'}>
        <Stack.Screen name="(mandataire)" />
      </Stack.Protected>
      <Stack.Protected guard={estConnecte && espaceActif === 'distributeur'}>
        <Stack.Screen name="(distributeur)" />
      </Stack.Protected>
      <Stack.Protected guard={estConnecte && espaceActif === null}>
        <Stack.Screen name="choisir-espace" />
      </Stack.Protected>
      <Stack.Protected guard={!estConnecte}>
        <Stack.Screen name="(auth)" />
      </Stack.Protected>
    </Stack>
  );
}
