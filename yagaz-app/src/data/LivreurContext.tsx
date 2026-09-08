/**
 * Missions de l'espace livreur (contrat 10 §5) : liste des livraisons
 * affectées, et transitions de statut en gestes larges (en route -> livrée
 * -> vide récupéré), qui remontent à l'API. Même schéma que
 * `DonneesContext` : cache hors ligne, appel réel prioritaire, repli démo.
 */
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';

import * as api from '../api/endpoints';
import { MODE_DEMO, executerAvecSource, missionsLivreurDemo } from '../api/demo';
import type { MissionLivreur, StatutLivraison } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';
import type { StatutSync } from './DonneesContext';

interface ContexteLivreurValeur {
  missions: MissionLivreur[];
  chargementInitial: boolean;
  statutSync: StatutSync;
  rafraichir: () => Promise<void>;
  majStatut: (
    livraisonId: number,
    statut: Exclude<StatutLivraison, 'affectee'>,
    videsRecuperes?: number
  ) => Promise<void>;
}

const ContexteLivreur = createContext<ContexteLivreurValeur | null>(null);

export function LivreurProvider({ children }: { children: ReactNode }) {
  const { estConnecte } = useAuth();

  const [missions, setMissions] = useState<MissionLivreur[]>([]);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const dejaCharge = useRef(false);

  useEffect(() => {
    (async () => {
      const cache = await lireCache<MissionLivreur[]>(CLES_CACHE.livreurMissions);
      if (cache) {
        setMissions(cache);
        setChargementInitial(false);
      }
    })();
  }, []);

  const rafraichir = useCallback(async () => {
    setStatutSync((precedent) => (precedent === 'synchronise' ? precedent : 'chargement'));
    try {
      const { data, source } = await executerAvecSource(
        () => api.livreurMissions().then((r) => r.data),
        missionsLivreurDemo
      );
      setMissions(data);
      setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');
      await ecrireCache(CLES_CACHE.livreurMissions, data);
    } catch {
      setStatutSync('hors_ligne');
    } finally {
      setChargementInitial(false);
    }
  }, []);

  useEffect(() => {
    if (!estConnecte || dejaCharge.current) return;
    dejaCharge.current = true;
    rafraichir();
  }, [estConnecte, rafraichir]);

  useEffect(() => {
    if (estConnecte) return;
    dejaCharge.current = false;
  }, [estConnecte]);

  const majStatut = useCallback(
    async (livraisonId: number, statut: Exclude<StatutLivraison, 'affectee'>, videsRecuperes?: number) => {
      try {
        await api.majStatutLivraison(livraisonId, { statut, vides_recuperes: videsRecuperes });
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
      }
      setMissions((precedent) => {
        const suivant = precedent.map((m) =>
          m.livraison_id === livraisonId
            ? { ...m, statut, a_recuperer: videsRecuperes ?? m.a_recuperer }
            : m
        );
        ecrireCache(CLES_CACHE.livreurMissions, suivant);
        return suivant;
      });
    },
    []
  );

  const valeur = useMemo<ContexteLivreurValeur>(
    () => ({ missions, chargementInitial, statutSync, rafraichir, majStatut }),
    [missions, chargementInitial, statutSync, rafraichir, majStatut]
  );

  return <ContexteLivreur.Provider value={valeur}>{children}</ContexteLivreur.Provider>;
}

export function useLivreur(): ContexteLivreurValeur {
  const contexte = useContext(ContexteLivreur);
  if (!contexte) {
    throw new Error('useLivreur doit être utilisé sous LivreurProvider');
  }
  return contexte;
}
