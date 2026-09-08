import { Tabs } from 'expo-router';

import { IconeOnglet } from '../../components/IconeOnglet';
import { DepotProvider } from '../../data/DepotContext';
import { couleurs } from '../../../theme/couleurs';
import { styleBarreOnglets } from '../../components/styleBarreOnglets';

/**
 * Navigation dépôt : stock / commandes entrantes / propositions / réglages
 * (UX §3, contrat 10 §4). Outil de comptoir, onglets larges et peu nombreux.
 */
export default function LayoutDepot() {
  return (
    <DepotProvider>
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
            title: 'Stock',
            tabBarLabel: 'Stock',
            tabBarIcon: ({ color }) => <IconeOnglet nom="archive" color={color} />,
          }}
        />
        <Tabs.Screen
          name="commandes"
          options={{
            title: 'Commandes',
            tabBarIcon: ({ color }) => <IconeOnglet nom="cube" color={color} />,
          }}
        />
        <Tabs.Screen
          name="propositions"
          options={{
            title: 'Propositions',
            tabBarIcon: ({ color }) => <IconeOnglet nom="megaphone" color={color} />,
          }}
        />
        <Tabs.Screen
          name="reappros"
          options={{
            title: 'Réappros',
            tabBarIcon: ({ color }) => <IconeOnglet nom="repeat" color={color} />,
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
    </DepotProvider>
  );
}
