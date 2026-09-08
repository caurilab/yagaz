import { Tabs } from 'expo-router';

import { IconeOnglet } from '../../components/IconeOnglet';
import { LivreurProvider } from '../../data/LivreurContext';
import { couleurs } from '../../../theme/couleurs';

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
        }}>
        <Tabs.Screen
          name="index"
          options={{
            title: 'Mes missions',
            tabBarLabel: 'Missions',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="🛵" focus={focused} />,
          }}
        />
        <Tabs.Screen
          name="notifications"
          options={{
            title: 'Notifications',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="🔔" focus={focused} />,
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
    </LivreurProvider>
  );
}
