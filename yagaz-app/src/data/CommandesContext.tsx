/**
 * Commandes du foyer (contrat 10 §2-3) : suivi de statut jusqu'à la
 * livraison, et réponse à une proposition reçue (oui/non). Même schéma que
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
import { MODE_DEMO, commandesFoyerDemo, executerAvecSource } from '../api/demo';
import type { Commande, CorpsCreationCommande } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';
import type { StatutSync } from './DonneesContext';

interface ContexteCommandesValeur {
  commandes: Commande[];
  chargementInitial: boolean;
  statutSync: StatutSync;
  rafraichir: () => Promise<void>;
  creer: (corps: CorpsCreationCommande) => Promise<Commande>;
  repondre: (uuid: string, accepte: boolean) => Promise<void>;
}

const ContexteCommandes = createContext<ContexteCommandesValeur | null>(null);

let compteurUuidCommandeDemo = 0;
function genererUuidCommandeLocal(): string {
  compteurUuidCommandeDemo += 1;
  return `commande-locale-${Date.now()}-${compteurUuidCommandeDemo}`;
}

export function CommandesProvider({ children }: { children: ReactNode }) {
  const { estConnecte } = useAuth();

  const [commandes, setCommandes] = useState<Commande[]>([]);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const dejaCharge = useRef(false);

  useEffect(() => {
    (async () => {
      const cache = await lireCache<Commande[]>(CLES_CACHE.commandesFoyer);
      if (cache) {
        setCommandes(cache);
        setChargementInitial(false);
      }
    })();
  }, []);

  const rafraichir = useCallback(async () => {
    setStatutSync((precedent) => (precedent === 'synchronise' ? precedent : 'chargement'));
    try {
      const { data, source } = await executerAvecSource(
        () => api.listerCommandesFoyer().then((r) => r.data),
        commandesFoyerDemo
      );
      setCommandes(data);
      setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');
      await ecrireCache(CLES_CACHE.commandesFoyer, data);
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

  const creer = useCallback(async (corps: CorpsCreationCommande): Promise<Commande> => {
    try {
      const { data } = await api.creerCommande(corps);
      setCommandes((precedent) => {
        const suivant = [data, ...precedent];
        ecrireCache(CLES_CACHE.commandesFoyer, suivant);
        return suivant;
      });
      return data;
    } catch (erreur) {
      if (!MODE_DEMO) throw erreur;
      const nouvelle: Commande = {
        uuid: genererUuidCommandeLocal(),
        site_uuid: corps.site_uuid,
        format: { id: corps.format_id, code: '?', marque: '?', tare_nominale_g: 0, contenance_gaz_g: 0 },
        quantite: corps.quantite,
        depot_uuid: corps.depot_uuid,
        statut: 'confirmee',
        commission_g: 0,
        created_at: new Date().toISOString(),
        livraison: null,
      };
      setCommandes((precedent) => {
        const suivant = [nouvelle, ...precedent];
        ecrireCache(CLES_CACHE.commandesFoyer, suivant);
        return suivant;
      });
      return nouvelle;
    }
  }, []);

  const repondre = useCallback(async (uuid: string, accepte: boolean) => {
    try {
      const { data } = await api.repondreProposition(uuid, { accepte });
      setCommandes((precedent) => {
        const suivant = precedent.map((c) => (c.uuid === uuid ? data : c));
        ecrireCache(CLES_CACHE.commandesFoyer, suivant);
        return suivant;
      });
    } catch (erreur) {
      if (!MODE_DEMO) throw erreur;
      setCommandes((precedent) => {
        const suivant = precedent.map((c) =>
          c.uuid === uuid ? { ...c, statut: accepte ? ('confirmee' as const) : ('annulee' as const) } : c
        );
        ecrireCache(CLES_CACHE.commandesFoyer, suivant);
        return suivant;
      });
    }
  }, []);

  const valeur = useMemo<ContexteCommandesValeur>(
    () => ({ commandes, chargementInitial, statutSync, rafraichir, creer, repondre }),
    [commandes, chargementInitial, statutSync, rafraichir, creer, repondre]
  );

  return <ContexteCommandes.Provider value={valeur}>{children}</ContexteCommandes.Provider>;
}

export function useCommandes(): ContexteCommandesValeur {
  const contexte = useContext(ContexteCommandes);
  if (!contexte) {
    throw new Error('useCommandes doit être utilisé sous CommandesProvider');
  }
  return contexte;
}
