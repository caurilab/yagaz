/**
 * Sélecteur de format 100 % visuel : bouteilles SVG alignées en ligne
 * scrollable, taille proportionnelle au format (B24 la plus grande, cf.
 * `BouteilleGaz`), code en appoint. Remplace les chips texte partout où l'on
 * choisit un format (nouvelle commande, enregistrement, édition) - on choisit
 * à l'œil, la marque se choisit à l'étape suivante. Sélection nette (halo
 * orange), jamais de fond plein criard.
 */
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { couleurs, espacements, rayons } from '../../theme/couleurs';
import { BouteilleGaz } from './BouteilleGaz';

const HAUTEUR_REFERENCE_BOUTEILLE = 108;
const LARGEUR_CARTE = 96;
const HAUTEUR_CARTE = 148;

interface Props {
  codes: string[];
  codeChoisi: string | null;
  onChoisir: (code: string) => void;
}

export function SelecteurFormat({ codes, codeChoisi, onChoisir }: Props) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rangee}>
      {codes.map((code) => {
        const actif = codeChoisi === code;
        return (
          <Pressable
            key={code}
            style={[styles.carte, actif && styles.carteActive]}
            onPress={() => onChoisir(code)}
            accessibilityRole="button"
            accessibilityState={{ selected: actif }}
            accessibilityLabel={`Format ${code}`}>
            <View style={styles.zoneBouteille}>
              <BouteilleGaz code={code} niveauPct={100} taille={HAUTEUR_REFERENCE_BOUTEILLE} reflet={false} />
            </View>
            <Text style={[styles.code, actif && styles.codeActif]}>{code}</Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  rangee: {
    flexDirection: 'row',
    gap: espacements.md,
    paddingVertical: espacements.xs,
    paddingHorizontal: 2,
  },
  carte: {
    width: LARGEUR_CARTE,
    height: HAUTEUR_CARTE,
    borderRadius: rayons.lg,
    borderWidth: 2,
    borderColor: 'transparent',
    backgroundColor: couleurs.carte,
    alignItems: 'center',
    justifyContent: 'flex-end',
    paddingBottom: espacements.sm,
    gap: espacements.xs,
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
