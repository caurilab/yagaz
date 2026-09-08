/**
 * Persistance locale (AsyncStorage) de la dernière réponse sites/bouteilles,
 * pour ne jamais afficher de page blanche hors ligne (contrat §Hors ligne,
 * UX §1 "Réseau faible assumé").
 */
import AsyncStorage from '@react-native-async-storage/async-storage';

const PREFIXE = 'yagaz.cache.';

export async function lireCache<T>(cle: string): Promise<T | null> {
  try {
    const brut = await AsyncStorage.getItem(PREFIXE + cle);
    return brut ? (JSON.parse(brut) as T) : null;
  } catch {
    return null;
  }
}

export async function ecrireCache<T>(cle: string, valeur: T): Promise<void> {
  try {
    await AsyncStorage.setItem(PREFIXE + cle, JSON.stringify(valeur));
  } catch {
    // Le cache est un confort hors ligne, pas une exigence bloquante.
  }
}

export async function viderCache(cle: string): Promise<void> {
  try {
    await AsyncStorage.removeItem(PREFIXE + cle);
  } catch {
    // ignoré
  }
}

export const CLES_CACHE = {
  sites: 'sites',
  bouteilles: 'bouteilles',
  formats: 'formats',
  alertes: 'alertes',
  siteActif: 'site_actif',
  commandesFoyer: 'commandes_foyer',
  espaceActif: 'espace_actif',
  depotOrgActif: 'depot_org_actif',
  depotStock: 'depot_stock',
  depotCommandes: 'depot_commandes',
  livreurMissions: 'livreur_missions',
} as const;
