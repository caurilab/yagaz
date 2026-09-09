import { router, Tabs } from 'expo-router';
import { Pressable, Text } from 'react-native';

import { Icone } from '../../components/icones';
import { useCommandes } from '../../data/CommandesContext';
import { couleurs } from '../../../theme/couleurs';
import { styleBarreOnglets } from '../../components/styleBarreOnglets';

/** Une commande est "en cours" tant qu'elle n'est ni livrée ni annulée. */
const STATUTS_TERMINES = ['livree', 'annulee'];

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
  const { commandes } = useCommandes();
  const nbCommandesEnCours = commandes.filter((c) => !STATUTS_TERMINES.includes(c.statut)).length;

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
          tabBarIcon: ({ color }) => <Icone nom="accueil" couleur={color as string} />,
        }}
      />
      <Tabs.Screen
        name="bouteilles"
        options={{
          href: null,
          title: 'Bouteilles',
          headerShown: false,
          tabBarIcon: ({ color }) => <Icone nom="bouteille" couleur={color as string} />,
        }}
      />
      <Tabs.Screen
        name="commandes"
        options={{
          title: 'Commandes',
          headerShown: false,
          tabBarIcon: ({ color }) => <Icone nom="commande" couleur={color as string} />,
          // Indicateur de commande en cours, visible depuis toutes les pages.
          tabBarBadge: nbCommandesEnCours > 0 ? nbCommandesEnCours : undefined,
          tabBarBadgeStyle: { backgroundColor: couleurs.rouge, color: couleurs.blanc, fontSize: 11 },
        }}
      />
      <Tabs.Screen
        name="alertes"
        options={{
          href: null,
          title: 'Alertes',
          headerShown: false,
          tabBarIcon: ({ color }) => <Icone nom="cloche" couleur={color as string} />,
        }}
      />
      <Tabs.Screen
        name="historique"
        options={{
          title: 'Historique',
          headerShown: false,
          tabBarIcon: ({ color }) => <Icone nom="historique" couleur={color as string} />,
        }}
      />
      <Tabs.Screen
        name="analyse"
        options={{
          href: null,
          title: 'Analyse',
          headerShown: false,
          tabBarIcon: ({ color }) => <Icone nom="analyse" couleur={color as string} />,
        }}
      />
      <Tabs.Screen
        name="reglages"
        options={{
          title: 'Réglages',
          headerShown: false,
          tabBarIcon: ({ color }) => <Icone nom="reglages" couleur={color as string} />,
        }}
      />
      <Tabs.Screen
        name="astuces"
        options={{
          href: null,
          title: 'Astuces',
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="enregistrer"
        options={{
          href: null,
          title: 'Enregistrer une bouteille',
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="nouveau-lieu"
        options={{
          href: null,
          title: 'Nouveau lieu',
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="bouteille/[uuid]"
        options={{
          href: null,
          title: 'Bouteille',
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="nouvelle-commande"
        options={{
          href: null,
          title: 'Nouvelle commande',
          headerShown: false,
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
