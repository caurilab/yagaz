/**
 * Données de l'espace mandataire (doc 11 §1) : vue consolidée des dépôts
 * (lecture, pilotage riche laissé au web) et tournées, avec l'avancement des
 * arrêts de la tournée du jour en gestes larges (UX §4). Même schéma que
 * `DepotContext` : cache hors ligne, appel réel prioritaire, repli démo.
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
import { MODE_DEMO, executerAvecSource, mandataireDepotsDemo, mandataireTourneesDemo } from '../api/demo';
import type { CorpsAjustementLigneTournee, DepotConsolide, StatutLigneTournee, Tournee } from '../api/types';
import { useEspace } from '../espace/EspaceContext';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';
import type { StatutSync } from './DonneesContext';

interface DonneesMandataireCache {
  depots: DepotConsolide[];
  tournees: Tournee[];
}

interface ContexteMandataireValeur {
  orgUuid: string | null;
  orgNom: string | null;
  depots: DepotConsolide[];
  tournees: Tournee[];
  /** Tournée du jour à exécuter : validée/en cours en priorité, sinon la plus récente du jour. */
  tourneeDuJour: Tournee | null;
  chargementInitial: boolean;
  statutSync: StatutSync;
  rafraichir: () => Promise<void>;
  /** Fait avancer d'un même geste toutes les lignes d'un arrêt (un dépôt) vers `statut`. */
  avancerArret: (tourneeUuid: string, ligneIds: number[], statut: StatutLigneTournee) => Promise<void>;
}

const ContexteMandataire = createContext<ContexteMandataireValeur | null>(null);

function estAujourdhui(date: string): boolean {
  return date === new Date().toISOString().slice(0, 10);
}

function choisirTourneeDuJour(tournees: Tournee[]): Tournee | null {
  const duJour = tournees.filter((t) => estAujourdhui(t.date));
  if (duJour.length === 0) return null;
  const active = duJour.find((t) => t.statut === 'en_cours' || t.statut === 'validee');
  return active ?? duJour[0];
}

export function MandataireProvider({ children }: { children: ReactNode }) {
  const { mandataireOrgActifUuid, roles } = useEspace();
  const orgUuid = mandataireOrgActifUuid;
  const orgNom = roles?.mandataires.find((m) => m.uuid === orgUuid)?.nom ?? null;

  const [depots, setDepots] = useState<DepotConsolide[]>([]);
  const [tournees, setTournees] = useState<Tournee[]>([]);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const dernierOrgCharge = useRef<string | null>(null);

  useEffect(() => {
    (async () => {
      const [depotsCache, tourneesCache] = await Promise.all([
        lireCache<DepotConsolide[]>(CLES_CACHE.mandataireDepots),
        lireCache<Tournee[]>(CLES_CACHE.mandataireTournees),
      ]);
      if (depotsCache || tourneesCache) {
        setDepots(depotsCache ?? []);
        setTournees(tourneesCache ?? []);
        setChargementInitial(false);
      }
    })();
  }, []);

  const rafraichir = useCallback(async () => {
    if (!orgUuid) return;
    setStatutSync((precedent) => (precedent === 'synchronise' ? precedent : 'chargement'));
    try {
      const { data: depotsRecus, source: sourceDepots } = await executerAvecSource(
        () => api.mandataireDepots(orgUuid).then((r) => r.data),
        mandataireDepotsDemo
      );
      const { data: tourneesRecues, source: sourceTournees } = await executerAvecSource(
        () => api.mandataireTournees(orgUuid).then((r) => r.data),
        mandataireTourneesDemo
      );

      setDepots(depotsRecus);
      setTournees(tourneesRecues);
      setStatutSync(sourceDepots === 'demo' || sourceTournees === 'demo' ? 'hors_ligne' : 'synchronise');
      await ecrireCache(CLES_CACHE.mandataireDepots, depotsRecus);
      await ecrireCache(CLES_CACHE.mandataireTournees, tourneesRecues);
    } catch {
      setStatutSync('hors_ligne');
    } finally {
      setChargementInitial(false);
    }
  }, [orgUuid]);

  useEffect(() => {
    if (!orgUuid || dernierOrgCharge.current === orgUuid) return;
    dernierOrgCharge.current = orgUuid;
    rafraichir();
  }, [orgUuid, rafraichir]);

  const avancerArret = useCallback(
    async (tourneeUuid: string, ligneIds: number[], statut: StatutLigneTournee) => {
      const lignesCorps: CorpsAjustementLigneTournee[] = ligneIds.map((id) => ({ id, statut }));
      try {
        const { data } = await api.ajusterTournee(tourneeUuid, { lignes: lignesCorps });
        setTournees((precedent) => {
          const suivant = precedent.map((t) => (t.uuid === tourneeUuid ? { ...t, ...data } : t));
          ecrireCache(CLES_CACHE.mandataireTournees, suivant);
          return suivant;
        });
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        setTournees((precedent) => {
          const suivant = precedent.map((t) =>
            t.uuid === tourneeUuid
              ? { ...t, lignes: t.lignes.map((l) => (ligneIds.includes(l.id) ? { ...l, statut } : l)) }
              : t
          );
          ecrireCache(CLES_CACHE.mandataireTournees, suivant);
          return suivant;
        });
      }
    },
    []
  );

  const tourneeDuJour = useMemo(() => choisirTourneeDuJour(tournees), [tournees]);

  const valeur = useMemo<ContexteMandataireValeur>(
    () => ({
      orgUuid,
      orgNom,
      depots,
      tournees,
      tourneeDuJour,
      chargementInitial,
      statutSync,
      rafraichir,
      avancerArret,
    }),
    [orgUuid, orgNom, depots, tournees, tourneeDuJour, chargementInitial, statutSync, rafraichir, avancerArret]
  );

  return <ContexteMandataire.Provider value={valeur}>{children}</ContexteMandataire.Provider>;
}

export function useMandataire(): ContexteMandataireValeur {
  const contexte = useContext(ContexteMandataire);
  if (!contexte) {
    throw new Error('useMandataire doit être utilisé sous MandataireProvider');
  }
  return contexte;
}
