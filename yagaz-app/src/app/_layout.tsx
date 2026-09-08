import { useEffect } from 'react';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider, useAuth } from '../auth/AuthContext';
import { DonneesProvider } from '../data/DonneesContext';

SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <DonneesProvider>
          <StatusBar style="light" />
          <NavigationRacine />
        </DonneesProvider>
      </AuthProvider>
    </SafeAreaProvider>
  );
}

/**
 * Bascule (auth) / (app) selon l'état de connexion. Le splash natif reste
 * affiché tant que le jeton persistant n'a pas été relu (jamais de flash
 * d'écran de connexion pour un utilisateur déjà connecté).
 */
function NavigationRacine() {
  const { estConnecte, chargementInitial } = useAuth();

  useEffect(() => {
    if (!chargementInitial) {
      SplashScreen.hideAsync();
    }
  }, [chargementInitial]);

  if (chargementInitial) {
    return null;
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Protected guard={estConnecte}>
        <Stack.Screen name="(app)" />
      </Stack.Protected>
      <Stack.Protected guard={!estConnecte}>
        <Stack.Screen name="(auth)" />
      </Stack.Protected>
    </Stack>
  );
}
