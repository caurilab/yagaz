import { Tabs } from 'expo-router';

import { Icone } from '../../components/icones';
import { LivreurProvider } from '../../data/LivreurContext';
import { couleurs } from '../../../theme/couleurs';
import { styleBarreOnglets } from '../../components/styleBarreOnglets';

/**
 * Navigation livreur : missions / réglages (UX §6). Application la plus
 * dépouillée, faite pour être utilisée en mouvement, d'une main.
 */
export default function LayoutLivreur() {
  return (
    <LivreurProvider>
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
            title: 'Mes missions',
            tabBarLabel: 'Missions',
            tabBarIcon: ({ color }) => <Icone nom="livraison" couleur={color as string} />,
          }}
        />
        <Tabs.Screen
          name="tension"
          options={{
            title: 'Foyers en tension',
            tabBarLabel: 'Tension',
            tabBarIcon: ({ color }) => <Icone nom="depot" couleur={color as string} />,
          }}
        />
        <Tabs.Screen
          name="notifications"
          options={{
            title: 'Notifications',
            tabBarIcon: ({ color }) => <Icone nom="cloche" couleur={color as string} />,
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
    </LivreurProvider>
  );
}
