import { Stack } from 'expo-router';

/**
 * Espace distributeur sur mobile. Le pilotage distributeur (demande
 * régionale, tensions par zone, volumes) se fait sur le tableau de bord web ;
 * l'app mobile affiche un écran de renvoi plutôt que l'interface foyer (un
 * compte distributeur ne doit jamais retomber sur l'espace foyer).
 */
export default function DistributeurLayout() {
  return <Stack screenOptions={{ headerShown: false }} />;
}
