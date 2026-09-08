/**
 * Encart température de la cuisine (ADR 0011, doc 13 §3/§4) : accueil et
 * détail bouteille. Sobre - un chiffre, un badge cuisson, une alerte visuelle
 * si la température est élevée.
 */
import { StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { couleurs, espacements, rayons } from '../../theme/couleurs';
import type { TemperatureSite } from '../api/types';

/** Seuil d'alerte visuelle - la sécurité (notification `temperature_elevee`) reste côté API. */
export const SEUIL_TEMPERATURE_ELEVEE_C = 60;

export function EncartTemperature({ temperature }: { temperature: TemperatureSite | null }) {
  if (!temperature) return null;

  const elevee = temperature.temp_courante_c >= SEUIL_TEMPERATURE_ELEVEE_C;

  return (
    <View style={[styles.carte, elevee && styles.carteAlerte]}>
      <View style={styles.ligneEntete}>
        <View style={styles.libelleAvecIcone}>
          <Ionicons name="thermometer-outline" size={18} color={elevee ? couleurs.danger : couleurs.rouge} />
          <Text style={styles.libelle}>Température cuisine</Text>
        </View>
        {temperature.cuisson_en_cours ? (
          <View style={styles.badgeCuisson}>
            <Ionicons name="flame" size={12} color={couleurs.blanc} />
            <Text style={styles.texteBadgeCuisson}>Cuisson en cours</Text>
          </View>
        ) : null}
      </View>

      <Text style={[styles.chiffre, elevee && styles.chiffreAlerte]}>{Math.round(temperature.temp_courante_c)} °C</Text>

      {elevee ? <Text style={styles.texteAlerte}>Température élevée - vérifiez la cuisine</Text> : null}
      {!temperature.frais ? <Text style={styles.texteObsolete}>Dernière valeur connue - hors ligne</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.md,
    gap: espacements.xs,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  carteAlerte: {
    backgroundColor: '#FDECEC',
  },
  ligneEntete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: espacements.sm,
  },
  libelleAvecIcone: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  libelle: {
    fontSize: 13,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  badgeCuisson: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: couleurs.rouge,
    borderRadius: rayons.rond,
    paddingHorizontal: espacements.sm,
    paddingVertical: 3,
  },
  texteBadgeCuisson: {
    fontSize: 11,
    fontWeight: '700',
    color: couleurs.blanc,
  },
  chiffre: {
    fontSize: 32,
    fontWeight: '800',
    color: couleurs.texte,
  },
  chiffreAlerte: {
    color: couleurs.danger,
  },
  texteAlerte: {
    fontSize: 12,
    fontWeight: '700',
    color: couleurs.danger,
  },
  texteObsolete: {
    fontSize: 12,
    color: couleurs.texteDoux,
  },
});
