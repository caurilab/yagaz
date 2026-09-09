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
import {
  MODE_DEMO,
  executerAvecSource,
  livreurFoyersEnTensionDemo,
  missionsLivreurDemo,
  notificationsLivreurDemo,
} from '../api/demo';
import type { FoyerEnTensionLivreur, MissionLivreur, Notification, StatutLivraison } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';
import type { StatutSync } from './DonneesContext';

interface ContexteLivreurValeur {
  missions: MissionLivreur[];
  notifications: Notification[];
  /** File actionnable des foyers habituels en tension (ADR 0008, précision « maillon C ») - source de `site_uuid` pour proposer, jamais la notification. */
  foyersEnTension: FoyerEnTensionLivreur[];
  chargementInitial: boolean;
  statutSync: StatutSync;
  rafraichir: () => Promise<void>;
  majStatut: (
    livraisonId: number,
    statut: Exclude<StatutLivraison, 'affectee'>,
    videsRecuperes?: number
  ) => Promise<void>;
  marquerNotificationVue: (id: number) => Promise<void>;
  proposerLivraison: (foyer: FoyerEnTensionLivreur, quantite?: number) => Promise<void>;
}

const ContexteLivreur = createContext<ContexteLivreurValeur | null>(null);

export function LivreurProvider({ children }: { children: ReactNode }) {
  const { estConnecte } = useAuth();

  const [missions, setMissions] = useState<MissionLivreur[]>([]);
  const [notificationsLivreur, setNotificationsLivreur] = useState<Notification[]>([]);
  const [foyersEnTension, setFoyersEnTension] = useState<FoyerEnTensionLivreur[]>([]);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const dejaCharge = useRef(false);

  useEffect(() => {
    (async () => {
      const [cacheMissions, cacheNotifications, cacheFoyersEnTension] = await Promise.all([
        lireCache<MissionLivreur[]>(CLES_CACHE.livreurMissions),
        lireCache<Notification[]>(CLES_CACHE.livreurNotifications),
        lireCache<FoyerEnTensionLivreur[]>(CLES_CACHE.livreurFoyersEnTension),
      ]);
      if (cacheMissions) {
        setMissions(cacheMissions);
        setChargementInitial(false);
      }
      if (cacheNotifications) {
        setNotificationsLivreur(cacheNotifications);
      }
      if (cacheFoyersEnTension) {
        setFoyersEnTension(cacheFoyersEnTension);
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
      const { data: notificationsRecues } = await executerAvecSource(
        () => api.notifications().then((r) => r.data),
        notificationsLivreurDemo
      );
      const { data: foyersRecus } = await executerAvecSource(
        () => api.livreurFoyersEnTension().then((r) => r.data),
        livreurFoyersEnTensionDemo
      );
      setMissions(data);
      setNotificationsLivreur(notificationsRecues);
      setFoyersEnTension(foyersRecus);
      setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');
      await ecrireCache(CLES_CACHE.livreurMissions, data);
      await ecrireCache(CLES_CACHE.livreurNotifications, notificationsRecues);
      await ecrireCache(CLES_CACHE.livreurFoyersEnTension, foyersRecus);
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
          m.id === livraisonId
            ? { ...m, statut, vides_recuperes: videsRecuperes ?? m.vides_recuperes }
            : m
        );
        ecrireCache(CLES_CACHE.livreurMissions, suivant);
        return suivant;
      });
    },
    []
  );

  const marquerNotificationVue = useCallback(async (id: number) => {
    try {
      await api.marquerNotificationVue(id);
    } catch (erreur) {
      if (!MODE_DEMO) throw erreur;
    }
    setNotificationsLivreur((precedent) => {
      const suivant = precedent.map((n) => (n.id === id ? { ...n, statut: 'vue' as const } : n));
      ecrireCache(CLES_CACHE.livreurNotifications, suivant);
      return suivant;
    });
  }, []);

  /**
   * Propose une livraison pour un foyer de la file actionnable (ADR 0008,
   * précision « maillon C » ; ADR 0009 §C) : `site_uuid` vient TOUJOURS du
   * foyer choisi dans `foyersEnTension`, jamais d'une notification (qui ne le
   * contient pas). Retire l'entrée de la file dès l'envoi (anti-doublon
   * local), comme côté dépôt.
   */
  const proposerLivraison = useCallback(async (foyer: FoyerEnTensionLivreur, quantite = 1) => {
    try {
      await api.livreurProposition({ site_uuid: foyer.site_uuid, quantite });
    } catch (erreur) {
      if (!MODE_DEMO) throw erreur;
    }
    setFoyersEnTension((precedent) => precedent.filter((f) => f.site_uuid !== foyer.site_uuid));
  }, []);

  const valeur = useMemo<ContexteLivreurValeur>(
    () => ({
      missions,
      notifications: notificationsLivreur,
      foyersEnTension,
      chargementInitial,
      statutSync,
      rafraichir,
      majStatut,
      marquerNotificationVue,
      proposerLivraison,
    }),
    [
      missions,
      notificationsLivreur,
      foyersEnTension,
      chargementInitial,
      statutSync,
      rafraichir,
      majStatut,
      marquerNotificationVue,
      proposerLivraison,
    ]
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
