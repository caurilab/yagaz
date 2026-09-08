import { Text } from 'react-native';

/** Icône d'onglet partagée entre les navigations foyer/dépôt/livreur. */
export function IconeOnglet({ symbole, focus }: { symbole: string; focus: boolean }) {
  return <Text style={{ fontSize: 22, opacity: focus ? 1 : 0.5 }}>{symbole}</Text>;
}
