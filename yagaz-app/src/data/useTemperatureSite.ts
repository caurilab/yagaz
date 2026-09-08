/**
 * Température de la cuisine d'un site (ADR 0011, doc 13 §3) : même schéma
 * hors ligne que les autres contextes - cache local, appel réel prioritaire,
 * repli démo. Un hook simple (pas un contexte) : consommé par l'accueil et
 * le détail bouteille pour un site donné.
 */
import { useCallback, useEffect, useState } from 'react';

import * as api from '../api/endpoints';
import { executerAvecSource, temperatureDemoParSite } from '../api/demo';
import type { TemperatureSite } from '../api/types';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';
import type { StatutSync } from './DonneesContext';

const TEMPERATURE_DEMO_REPLI: TemperatureSite = {
  temp_courante_c: 24,
  frais: true,
  cuisson_en_cours: false,
  debut_cuisson_at: null,
};

export function useTemperatureSite(siteUuid: string | null | undefined) {
  const [temperature, setTemperature] = useState<TemperatureSite | null>(null);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');

  useEffect(() => {
    if (!siteUuid) return;
    (async () => {
      const cache = await lireCache<TemperatureSite>(`${CLES_CACHE.temperature}_${siteUuid}`);
      if (cache) setTemperature(cache);
    })();
  }, [siteUuid]);

  const rafraichir = useCallback(async () => {
    if (!siteUuid) return;
    setStatutSync((precedent) => (precedent === 'synchronise' ? precedent : 'chargement'));
    try {
      const donneesDemo = temperatureDemoParSite[siteUuid] ?? TEMPERATURE_DEMO_REPLI;
      const { data, source } = await executerAvecSource(() => api.temperatureSite(siteUuid), donneesDemo);
      setTemperature(data);
      setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');
      await ecrireCache(`${CLES_CACHE.temperature}_${siteUuid}`, data);
    } catch {
      setStatutSync('hors_ligne');
    }
  }, [siteUuid]);

  useEffect(() => {
    rafraichir();
  }, [rafraichir]);

  return { temperature, statutSync, rafraichir };
}
