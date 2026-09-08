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
import { MODE_DEMO, executerAvecSource, formatsDemo, missionsLivreurDemo, notificationsLivreurDemo } from '../api/demo';
import type { CorpsPropositionLivreur, Format, MissionLivreur, Notification, StatutLivraison } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';
import type { StatutSync } from './DonneesContext';

interface ContexteLivreurValeur {
  missions: MissionLivreur[];
  notifications: Notification[];
  /** Référentiel des formats, pour retrouver un `format_id` à partir du `format_code` d'une notification (ADR 0008 : la notification n'a que le code). */
  formats: Format[];
  chargementInitial: boolean;
  statutSync: StatutSync;
  rafraichir: () => Promise<void>;
  majStatut: (
    livraisonId: number,
    statut: Exclude<StatutLivraison, 'affectee'>,
    videsRecuperes?: number
  ) => Promise<void>;
  marquerNotificationVue: (id: number) => Promise<void>;
  proposerLivraison: (corps: CorpsPropositionLivreur) => Promise<void>;
}

const ContexteLivreur = createContext<ContexteLivreurValeur | null>(null);

export function LivreurProvider({ children }: { children: ReactNode }) {
  const { estConnecte } = useAuth();

  const [missions, setMissions] = useState<MissionLivreur[]>([]);
  const [notificationsLivreur, setNotificationsLivreur] = useState<Notification[]>([]);
  const [formats, setFormats] = useState<Format[]>([]);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const dejaCharge = useRef(false);

  useEffect(() => {
    (async () => {
      const [cacheMissions, cacheNotifications] = await Promise.all([
        lireCache<MissionLivreur[]>(CLES_CACHE.livreurMissions),
        lireCache<Notification[]>(CLES_CACHE.livreurNotifications),
      ]);
      if (cacheMissions) {
        setMissions(cacheMissions);
        setChargementInitial(false);
      }
      if (cacheNotifications) {
        setNotificationsLivreur(cacheNotifications);
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
      const { data: formatsRecus } = await executerAvecSource(
        () => api.listerFormats().then((r) => r.data),
        formatsDemo
      );
      setMissions(data);
      setNotificationsLivreur(notificationsRecues);
      setFormats(formatsRecus);
      setStatutSync(source === 'demo' ? 'hors_ligne' : 'synchronise');
      await ecrireCache(CLES_CACHE.livreurMissions, data);
      await ecrireCache(CLES_CACHE.livreurNotifications, notificationsRecues);
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
   * Propose une livraison depuis une notification de tension (ADR 0009 §C).
   * Ne manipule jamais de site_uuid (ADR 0008) : le serveur retrouve le foyer
   * visé via `notification_id`. Marque la notification comme vue une fois la
   * proposition envoyée.
   */
  const proposerLivraison = useCallback(
    async (corps: CorpsPropositionLivreur) => {
      try {
        await api.livreurProposition(corps);
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
      }
      if (corps.notification_id != null) {
        await marquerNotificationVue(corps.notification_id);
      }
    },
    [marquerNotificationVue]
  );

  const valeur = useMemo<ContexteLivreurValeur>(
    () => ({
      missions,
      notifications: notificationsLivreur,
      formats,
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
      formats,
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
