import { router, Tabs } from 'expo-router';
import { Pressable, Text } from 'react-native';

import { IconeOnglet } from '../../components/IconeOnglet';
import { couleurs } from '../../../theme/couleurs';
import { styleBarreOnglets } from '../../components/styleBarreOnglets';

function BoutonRetour() {
  return (
    <Pressable onPress={() => router.back()} hitSlop={16} style={{ paddingHorizontal: 12, paddingVertical: 8 }}>
      <Text style={{ fontSize: 17, color: couleurs.rouge, fontWeight: '600' }}>Retour</Text>
    </Pressable>
  );
}

/**
 * Navigation foyer : 4 onglets principaux (accueil / bouteilles / alertes /
 * réglages) ; "enregistrer" et le détail bouteille sont des routes de la
 * même famille, sans onglet dédié (href: null), ouvertes avec un bouton
 * retour explicite.
 */
export default function LayoutApp() {
  return (
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
          title: 'Accueil',
          headerShown: false,
          tabBarLabel: 'Accueil',
          tabBarIcon: ({ color }) => <IconeOnglet nom="home" color={color} />,
        }}
      />
      <Tabs.Screen
        name="bouteilles"
        options={{
          title: 'Bouteilles',
          tabBarIcon: ({ color }) => <IconeOnglet lib="material-community" nom="propane-tank" color={color} />,
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
        name="alertes"
        options={{
          title: 'Alertes',
          tabBarIcon: ({ color }) => <IconeOnglet nom="notifications" color={color} />,
        }}
      />
      <Tabs.Screen
        name="historique"
        options={{
          title: 'Historique',
          tabBarIcon: ({ color }) => <IconeOnglet nom="time-outline" color={color} />,
        }}
      />
      <Tabs.Screen
        name="analyse"
        options={{
          title: 'Analyse',
          tabBarIcon: ({ color }) => <IconeOnglet nom="stats-chart-outline" color={color} />,
        }}
      />
      <Tabs.Screen
        name="reglages"
        options={{
          title: 'Réglages',
          tabBarIcon: ({ color }) => <IconeOnglet nom="settings" color={color} />,
        }}
      />
      <Tabs.Screen
        name="enregistrer"
        options={{
          href: null,
          title: 'Enregistrer une bouteille',
          headerLeft: () => <BoutonRetour />,
        }}
      />
      <Tabs.Screen
        name="bouteille/[uuid]"
        options={{
          href: null,
          title: 'Bouteille',
          headerLeft: () => <BoutonRetour />,
        }}
      />
      <Tabs.Screen
        name="nouvelle-commande"
        options={{
          href: null,
          title: 'Nouvelle commande',
          headerLeft: () => <BoutonRetour />,
        }}
      />
    </Tabs>
  );
}
