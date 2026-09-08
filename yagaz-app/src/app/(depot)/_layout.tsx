import { Tabs } from 'expo-router';

import { Icone } from '../../components/icones';
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
            tabBarIcon: ({ color }) => <Icone nom="depot" couleur={color as string} />,
          }}
        />
        <Tabs.Screen
          name="commandes"
          options={{
            title: 'Commandes',
            tabBarIcon: ({ color }) => <Icone nom="commande" couleur={color as string} />,
          }}
        />
        <Tabs.Screen
          name="propositions"
          options={{
            title: 'Propositions',
            tabBarIcon: ({ color }) => <Icone nom="annonce" couleur={color as string} />,
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
    </DepotProvider>
  );
}
