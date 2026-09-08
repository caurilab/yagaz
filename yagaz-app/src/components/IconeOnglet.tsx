import type { ComponentProps } from 'react';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import type { ColorValue } from 'react-native';

type NomIonicons = ComponentProps<typeof Ionicons>['name'];
type NomMaterialCommunity = ComponentProps<typeof MaterialCommunityIcons>['name'];

type Props =
  | { lib?: 'ionicons'; nom: NomIonicons; color: ColorValue; taille?: number }
  | { lib: 'material-community'; nom: NomMaterialCommunity; color: ColorValue; taille?: number };

/** Icône plate (vectorielle) d'onglet, partagée entre les navigations foyer/dépôt/livreur/mandataire. */
export function IconeOnglet(props: Props) {
  const taille = props.taille ?? 24;
  if (props.lib === 'material-community') {
    return <MaterialCommunityIcons name={props.nom} size={taille} color={props.color} />;
  }
  return <Ionicons name={props.nom} size={taille} color={props.color} />;
}
