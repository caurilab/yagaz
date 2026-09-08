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
            tabBarIcon: ({ color }) => <IconeOnglet lib="material-community" nom="moped" color={color} />,
          }}
        />
        <Tabs.Screen
          name="tension"
          options={{
            title: 'Foyers en tension',
            tabBarLabel: 'Tension',
            tabBarIcon: ({ color }) => <IconeOnglet lib="material-community" nom="gas-station" color={color} />,
          }}
        />
        <Tabs.Screen
          name="notifications"
          options={{
            title: 'Notifications',
            tabBarIcon: ({ color }) => <IconeOnglet nom="notifications" color={color} />,
          }}
        />
        <Tabs.Screen
          name="reglages"
          options={{
            title: 'Réglages',
            tabBarIcon: ({ color }) => <IconeOnglet nom="settings" color={color} />,
          }}
        />
      </Tabs>
    </LivreurProvider>
  );
}
