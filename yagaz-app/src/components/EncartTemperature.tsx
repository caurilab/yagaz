/**
 * Encart température de la cuisine (ADR 0011, doc 13 §3/§4) : accueil et
 * détail bouteille. Sobre - un chiffre, un badge cuisson, une alerte visuelle
 * si la température est élevée. Tappable dès qu'un `siteUuid` est fourni :
 * ouvre l'écran d'analyse détaillée (`/temperature/{uuid}`, doc 13 §3).
 */
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { couleurs, espacements, rayons } from '../../theme/couleurs';
import type { TemperatureSite } from '../api/types';
import { EncartConnecterMateriel } from './EncartConnecterMateriel';

/** Seuil d'alerte visuelle - la sécurité (notification `temperature_elevee`) reste côté API. */
export const SEUIL_TEMPERATURE_ELEVEE_C = 60;

export function EncartTemperature({
  temperature,
  siteUuid,
  connecte = true,
}: {
  temperature: TemperatureSite | null;
  /** Site dont dépend cette température - présent, rend l'encart tappable vers le détail. */
  siteUuid?: string | null;
  /** `a_temperature` du site (gating ADR 0012) : `false` grise l'encart avec un CTA vers Matériels. */
  connecte?: boolean;
}) {
  if (!connecte) {
    return (
      <View style={styles.carte}>
        <View style={styles.libelleAvecIcone}>
          <Ionicons name="thermometer-outline" size={18} color={couleurs.grisNeutre} />
          <Text style={styles.libelle}>Température cuisine</Text>
        </View>
        <Text style={styles.chiffreDesactive}>-- °C</Text>
        <EncartConnecterMateriel texte="Connectez votre capteur de température" />
      </View>
    );
  }

  if (!temperature) return null;

  const elevee = temperature.temp_courante_c >= SEUIL_TEMPERATURE_ELEVEE_C;

  const contenu = (
    <>
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

      {siteUuid ? (
        <View style={styles.ligneVoirPlus}>
          <Text style={styles.texteVoirPlus}>Voir le détail</Text>
          <Ionicons name="chevron-forward" size={14} color={couleurs.texteDoux} />
        </View>
      ) : null}
    </>
  );

  if (!siteUuid) {
    return <View style={[styles.carte, elevee && styles.carteAlerte]}>{contenu}</View>;
  }

  return (
    <Pressable
      style={({ pressed }) => [styles.carte, elevee && styles.carteAlerte, pressed && styles.cartePressee]}
      onPress={() => router.push(`/temperature/${siteUuid}`)}
      accessibilityRole="button"
      accessibilityLabel="Voir le détail de la température">
      {contenu}
    </Pressable>
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
  chiffreDesactive: {
    fontSize: 32,
    fontWeight: '800',
    color: couleurs.grisNeutre,
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
  cartePressee: {
    opacity: 0.9,
  },
  ligneVoirPlus: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: 2,
    marginTop: espacements.xs,
  },
  texteVoirPlus: {
    fontSize: 12,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
});
