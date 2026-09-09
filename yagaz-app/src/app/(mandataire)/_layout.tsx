import { Tabs } from 'expo-router';

import { Icone } from '../../components/icones';
import { MandataireProvider } from '../../data/MandataireContext';
import { couleurs } from '../../../theme/couleurs';
import { styleBarreOnglets } from '../../components/styleBarreOnglets';

/**
 * Navigation mandataire : tournée du jour / dépôts / réglages (doc 11 §1,
 * §4 ; UX §4). Le pilotage riche (préparation des tournées) reste sur le
 * web - ici, l'exécution terrain de la tournée déjà validée.
 */
export default function LayoutMandataire() {
  return (
    <MandataireProvider>
      <Tabs
        screenOptions={{
          headerTintColor: couleurs.texte,
          tabBarActiveTintColor: couleurs.rouge,
          tabBarInactiveTintColor: couleurs.texteDoux,
          ...styleBarreOnglets,
        }}>
        <Tabs.Screen
          name="index"
          options={{
            title: 'Tournée du jour',
            tabBarLabel: 'Tournée',
            tabBarIcon: ({ color }) => <Icone nom="livraison" couleur={color as string} />,
          }}
        />
        <Tabs.Screen
          name="depots"
          options={{
            title: 'Mes dépôts',
            tabBarLabel: 'Dépôts',
            tabBarIcon: ({ color }) => <Icone nom="depot" couleur={color as string} />,
          }}
        />
        <Tabs.Screen
          name="reappros"
          options={{
            title: 'Réappros',
            tabBarIcon: ({ color }) => <Icone nom="repeat" couleur={color as string} />,
          }}
        />
        <Tabs.Screen
          name="reglages"
          options={{
            title: 'Réglages',
            tabBarIcon: ({ color }) => <Icone nom="reglages" couleur={color as string} />,
          }}
        />
      </Tabs>
    </MandataireProvider>
  );
}
