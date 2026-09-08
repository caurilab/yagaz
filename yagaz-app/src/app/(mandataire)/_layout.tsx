import { Tabs } from 'expo-router';

import { IconeOnglet } from '../../components/IconeOnglet';
import { MandataireProvider } from '../../data/MandataireContext';
import { couleurs } from '../../../theme/couleurs';

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
        }}>
        <Tabs.Screen
          name="index"
          options={{
            title: 'Tournée du jour',
            tabBarLabel: 'Tournée',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="🚚" focus={focused} />,
          }}
        />
        <Tabs.Screen
          name="depots"
          options={{
            title: 'Mes dépôts',
            tabBarLabel: 'Dépôts',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="🏬" focus={focused} />,
          }}
        />
        <Tabs.Screen
          name="reglages"
          options={{
            title: 'Réglages',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="⚙️" focus={focused} />,
          }}
        />
      </Tabs>
    </MandataireProvider>
  );
}
