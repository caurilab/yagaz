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
 * Navigation foyer : barre du bas ultra-light, 4 onglets visibles
 * (accueil / commandes / historique / réglages). "bouteilles", "alertes" et
 * "analyse" restent des routes accessibles (href: null) mais sortent de la
 * barre - accès garantis depuis le haut de l'accueil (carte bouteille, accès
 * statistiques et cloche d'alertes). "enregistrer", le détail bouteille et
 * "nouvelle-commande" sont eux aussi des routes de la même famille, sans
 * onglet dédié (href: null), ouvertes avec un bouton retour explicite.
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
          href: null,
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
          href: null,
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
          href: null,
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
      <Tabs.Screen
        name="temperature/[uuid]"
        options={{
          href: null,
          title: 'Température',
          headerLeft: () => <BoutonRetour />,
        }}
      />
      <Tabs.Screen
        name="materiels"
        options={{
          href: null,
          title: 'Matériels',
          headerLeft: () => <BoutonRetour />,
        }}
      />
      <Tabs.Screen
        name="suivi/[uuid]"
        options={{
          href: null,
          title: 'Suivi de commande',
          headerLeft: () => <BoutonRetour />,
        }}
      />
      <Tabs.Screen
        name="recu/[uuid]"
        options={{
          href: null,
          title: 'Reçu',
          headerLeft: () => <BoutonRetour />,
        }}
      />
    </Tabs>
  );
}
