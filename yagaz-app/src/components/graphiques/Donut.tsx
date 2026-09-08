/**
 * Donut de répartition (analyse §2 : par bouteille / par site), léger -
 * `react-native-svg` uniquement, sans lib de graphiques. Technique du cercle
 * à trous multiples (stroke-dasharray + rotation par segment).
 */
import { StyleSheet, Text, View } from 'react-native';
import Svg, { Circle } from 'react-native-svg';

import { couleurs, espacements } from '../../../theme/couleurs';
import type { RepartitionEntree } from '../../api/types';

interface Props {
  donnees: RepartitionEntree[];
  taille?: number;
  epaisseur?: number;
  /** Texte affiché au centre (ex. "13,6 kg"). */
  libelleCentre?: string;
  sousLibelleCentre?: string;
}

export function Donut({ donnees, taille = 152, epaisseur = 20, libelleCentre, sousLibelleCentre }: Props) {
  const total = donnees.reduce((somme, entree) => somme + entree.valeur, 0);
  const rayon = (taille - epaisseur) / 2;
  const circonference = 2 * Math.PI * rayon;
  const centre = taille / 2;

  let cumulFraction = 0;
  const segments = donnees
    .filter((entree) => entree.valeur > 0)
    .map((entree) => {
      const fraction = total > 0 ? entree.valeur / total : 0;
      const longueur = fraction * circonference;
      const segment = {
        couleur: entree.couleur,
        dasharray: `${longueur} ${circonference - longueur}`,
        rotation: cumulFraction * 360 - 90,
      };
      cumulFraction += fraction;
      return segment;
    });

  return (
    <View style={[styles.conteneur, { width: taille, height: taille }]}>
      <Svg width={taille} height={taille}>
        <Circle cx={centre} cy={centre} r={rayon} stroke={couleurs.bordure} strokeWidth={epaisseur} fill="none" />
        {segments.map((segment, index) => (
          <Circle
            key={index}
            cx={centre}
            cy={centre}
            r={rayon}
            stroke={segment.couleur}
            strokeWidth={epaisseur}
            fill="none"
            strokeDasharray={segment.dasharray}
            origin={`${centre}, ${centre}`}
            rotation={segment.rotation}
          />
        ))}
      </Svg>
      {libelleCentre ? (
        <View style={styles.centre} pointerEvents="none">
          <Text style={styles.chiffreCentre} numberOfLines={1}>
            {libelleCentre}
          </Text>
          {sousLibelleCentre ? (
            <Text style={styles.sousLibelleCentre} numberOfLines={1}>
              {sousLibelleCentre}
            </Text>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

/** Légende associée au donut (pastille + libellé + valeur), en colonne. */
export function LegendeDonut({ donnees, formaterValeur }: { donnees: RepartitionEntree[]; formaterValeur?: (v: number) => string }) {
  return (
    <View style={styles.legende}>
      {donnees.map((entree, index) => (
        <View key={index} style={styles.ligneLegende}>
          <View style={[styles.pastille, { backgroundColor: entree.couleur }]} />
          <Text style={styles.libelleLegende} numberOfLines={1}>
            {entree.libelle}
          </Text>
          <Text style={styles.valeurLegende}>{formaterValeur ? formaterValeur(entree.valeur) : entree.valeur}</Text>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  centre: {
    position: 'absolute',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: espacements.sm,
  },
  chiffreCentre: {
    fontSize: 20,
    fontWeight: '800',
    color: couleurs.texte,
  },
  sousLibelleCentre: {
    fontSize: 11,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  legende: {
    gap: espacements.sm,
    flexShrink: 1,
  },
  ligneLegende: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  pastille: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  libelleLegende: {
    flex: 1,
    fontSize: 13,
    color: couleurs.texte,
    fontWeight: '600',
  },
  valeurLegende: {
    fontSize: 13,
    color: couleurs.texteDoux,
  },
});
