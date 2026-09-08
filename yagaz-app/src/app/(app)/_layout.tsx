import { router, Tabs } from 'expo-router';
import { Pressable, Text } from 'react-native';

import { couleurs } from '../../../theme/couleurs';

function IconeOnglet({ symbole, focus }: { symbole: string; focus: boolean }) {
  return <Text style={{ fontSize: 22, opacity: focus ? 1 : 0.5 }}>{symbole}</Text>;
}

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
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'Accueil',
          headerShown: false,
          tabBarLabel: 'Accueil',
          tabBarIcon: ({ focused }) => <IconeOnglet symbole="🏠" focus={focused} />,
        }}
      />
      <Tabs.Screen
        name="bouteilles"
        options={{
          title: 'Bouteilles',
          tabBarIcon: ({ focused }) => <IconeOnglet symbole="🛢️" focus={focused} />,
        }}
      />
      <Tabs.Screen
        name="alertes"
        options={{
          title: 'Alertes',
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
    </Tabs>
  );
}
