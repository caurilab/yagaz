/**
 * Sélecteur de format 100 % visuel : bouteilles SVG à taille proportionnelle
 * au format (B35 la plus grande -> B6 la plus petite, cf. `BouteilleGaz`),
 * code en appoint. On choisit à l'œil ; la marque se choisit à l'étape
 * suivante. Sélection nette (halo orange), jamais de fond plein criard.
 *
 * Mise en page responsive : toutes les bouteilles sont visibles d'un coup
 * (pas de défilement horizontal). Elles tiennent sur UNE ligne quand la
 * largeur le permet, sinon se répartissent en rangées équilibrées (ex. 2+2),
 * et la taille des cartes/bouteilles s'ajuste à la largeur disponible.
 */
import { useState } from 'react';
import { type LayoutChangeEvent, Pressable, StyleSheet, Text, View } from 'react-native';

import { couleurs, rayons } from '../../theme/couleurs';
import { BouteilleGaz } from './BouteilleGaz';

const ESPACE = 10;
const LARGEUR_CARTE_MIN = 72;
const LARGEUR_CARTE_MAX = 116;
const HAUTEUR_BOUTEILLE_MIN = 66;
const HAUTEUR_BOUTEILLE_MAX = 116;

interface Props {
  codes: string[];
  codeChoisi: string | null;
  onChoisir: (code: string) => void;
}

const borne = (valeur: number, min: number, max: number) => Math.max(min, Math.min(max, valeur));

/** Nombre max de cartes par rangée tenant dans `largeur` sans passer sous la largeur mini. */
function maxParRangee(largeur: number, total: number): number {
  for (let k = total; k > 1; k -= 1) {
    if ((largeur - ESPACE * (k - 1)) / k >= LARGEUR_CARTE_MIN) {
      return k;
    }
  }
  return 1;
}

export function SelecteurFormat({ codes, codeChoisi, onChoisir }: Props) {
  const [largeur, setLargeur] = useState(0);
  const total = codes.length;

  const mesurer = (e: LayoutChangeEvent) => {
    const l = e.nativeEvent.layout.width;
    if (l > 0 && Math.abs(l - largeur) > 0.5) {
      setLargeur(l);
    }
  };

  // Rangées équilibrées : on répartit `total` en assez de rangées pour que
  // chaque carte respecte la largeur mini (4 formats -> 1 ligne large, ou 2+2
  // sur écran étroit), plutôt qu'une rangée pleine suivie d'une carte seule.
  const capacite = largeur > 0 ? maxParRangee(largeur, total) : total;
  const nbRangees = Math.max(1, Math.ceil(total / Math.max(1, capacite)));
  const parRangee = Math.ceil(total / nbRangees);
  const largeurCarte = largeur > 0
    ? borne(Math.floor((largeur - ESPACE * (parRangee - 1)) / parRangee), LARGEUR_CARTE_MIN, LARGEUR_CARTE_MAX)
    : LARGEUR_CARTE_MIN;
  const hauteurBouteille = borne(Math.round(largeurCarte * 1.02), HAUTEUR_BOUTEILLE_MIN, HAUTEUR_BOUTEILLE_MAX);
  const hauteurCarte = hauteurBouteille + 40;

  return (
    <View style={styles.grille} onLayout={mesurer}>
      {largeur > 0 &&
        codes.map((code) => {
          const actif = codeChoisi === code;
          return (
            <Pressable
              key={code}
              style={[styles.carte, { width: largeurCarte, height: hauteurCarte }, actif && styles.carteActive]}
              onPress={() => onChoisir(code)}
              accessibilityRole="button"
              accessibilityState={{ selected: actif }}
              accessibilityLabel={`Format ${code}`}>
              <View style={styles.zoneBouteille}>
                <BouteilleGaz code={code} niveauPct={100} taille={hauteurBouteille} reflet={false} />
              </View>
              <Text style={[styles.code, actif && styles.codeActif]}>{code}</Text>
            </Pressable>
          );
        })}
    </View>
  );
}

const styles = StyleSheet.create({
  grille: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    alignItems: 'flex-end',
    rowGap: ESPACE,
    columnGap: ESPACE,
    minHeight: LARGEUR_CARTE_MIN + 40,
  },
  carte: {
    borderRadius: rayons.lg,
    borderWidth: 2,
    borderColor: 'transparent',
    backgroundColor: couleurs.carte,
    alignItems: 'center',
    justifyContent: 'flex-end',
    paddingBottom: 8,
    gap: 4,
  },
  carteActive: {
    borderColor: couleurs.rouge,
    shadowColor: couleurs.rouge,
    shadowOpacity: 0.35,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 0 },
    elevation: 3,
  },
  zoneBouteille: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  code: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.texteDoux,
  },
  codeActif: {
    color: couleurs.rouge,
  },
});
