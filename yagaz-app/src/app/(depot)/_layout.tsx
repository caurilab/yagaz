import { Tabs } from 'expo-router';

import { IconeOnglet } from '../../components/IconeOnglet';
import { DepotProvider } from '../../data/DepotContext';
import { couleurs } from '../../../theme/couleurs';

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
        }}>
        <Tabs.Screen
          name="index"
          options={{
            title: 'Stock',
            tabBarLabel: 'Stock',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="🧯" focus={focused} />,
          }}
        />
        <Tabs.Screen
          name="commandes"
          options={{
            title: 'Commandes',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="📦" focus={focused} />,
          }}
        />
        <Tabs.Screen
          name="propositions"
          options={{
            title: 'Propositions',
            tabBarIcon: ({ focused }) => <IconeOnglet symbole="📣" focus={focused} />,
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
    </DepotProvider>
  );
}
