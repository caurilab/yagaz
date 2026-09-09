/**
 * Données de l'espace dépôt (contrat 10 §4) : stock par format, file des
 * commandes entrantes, membres livreurs du dépôt, et actions (préparer,
 * affecter, ajuster le stock, créer une proposition). Même schéma que
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
  depotCommandesDemo,
  depotFoyersEnTensionDemo,
  depotLivreursDemo,
  depotReapprosDemo,
  depotStocksDemo,
  executerAvecSource,
} from '../api/demo';
import type {
  CommandeDepot,
  CorpsAjustementStock,
  CorpsConfirmationReappro,
  CorpsCreationProposition,
  FoyerEnTension,
  MembreLivreur,
  Reappro,
  StockFormat,
} from '../api/types';
import { useEspace } from '../espace/EspaceContext';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';
import type { StatutSync } from './DonneesContext';

interface DonneesDepotCache {
  stocks: StockFormat[];
  commandes: CommandeDepot[];
  foyersEnTension: FoyerEnTension[];
  reappros: Reappro[];
}

interface ContexteDepotValeur {
  orgUuid: string | null;
  orgNom: string | null;
  stocks: StockFormat[];
  commandes: CommandeDepot[];
  livreurs: MembreLivreur[];
  foyersEnTension: FoyerEnTension[];
  reappros: Reappro[];
  chargementInitial: boolean;
  statutSync: StatutSync;
  rafraichir: () => Promise<void>;
  ajusterStock: (formatId: number, corps: CorpsAjustementStock) => Promise<void>;
  preparerCommande: (uuid: string) => Promise<void>;
  affecterLivraison: (uuid: string, livreurUserId?: string) => Promise<void>;
  creerProposition: (corps: CorpsCreationProposition) => Promise<void>;
  proposerDepuisTension: (foyer: FoyerEnTension) => Promise<void>;
  confirmerReappro: (uuid: string, corps?: CorpsConfirmationReappro) => Promise<void>;
}

const ContexteDepot = createContext<ContexteDepotValeur | null>(null);

export function DepotProvider({ children }: { children: ReactNode }) {
  const { depotOrgActifUuid, roles } = useEspace();
  const orgUuid = depotOrgActifUuid;
  const orgNom = roles?.depots.find((d) => d.uuid === orgUuid)?.nom ?? null;

  const [stocks, setStocks] = useState<StockFormat[]>([]);
  const [commandes, setCommandes] = useState<CommandeDepot[]>([]);
  const [livreurs, setLivreurs] = useState<MembreLivreur[]>([]);
  const [foyersEnTension, setFoyersEnTension] = useState<FoyerEnTension[]>([]);
  const [reappros, setReappros] = useState<Reappro[]>([]);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [chargementInitial, setChargementInitial] = useState(true);
  const dernierOrgCharge = useRef<string | null>(null);

  useEffect(() => {
    (async () => {
      const cache = await lireCache<DonneesDepotCache>(CLES_CACHE.depotCommandes);
      if (cache) {
        setStocks(cache.stocks);
        setCommandes(cache.commandes);
        setFoyersEnTension(cache.foyersEnTension ?? []);
        setReappros(cache.reappros ?? []);
        setChargementInitial(false);
      }
    })();
  }, []);

  const rafraichir = useCallback(async () => {
    if (!orgUuid) return;
    setStatutSync((precedent) => (precedent === 'synchronise' ? precedent : 'chargement'));
    try {
      const { data: stocksRecus, source: sourceStocks } = await executerAvecSource(
        () => api.depotStocks(orgUuid).then((r) => r.data),
        depotStocksDemo
      );
      const { data: commandesRecues, source: sourceCommandes } = await executerAvecSource(
        () => api.depotCommandes(orgUuid).then((r) => r.data),
        depotCommandesDemo
      );
      const { data: livreursRecus } = await executerAvecSource(
        () => api.depotLivreurs(orgUuid).then((r) => r.data),
        depotLivreursDemo
      );
      const { data: foyersRecus } = await executerAvecSource(
        () => api.depotFoyersEnTension(orgUuid).then((r) => r.data),
        depotFoyersEnTensionDemo
      );
      const { data: reapprosRecus } = await executerAvecSource(
        () => api.depotReappros(orgUuid, 'proposee').then((r) => r.data),
        depotReapprosDemo
      );

      setStocks(stocksRecus);
      setCommandes(commandesRecues);
      setLivreurs(livreursRecus);
      setFoyersEnTension(foyersRecus);
      setReappros(reapprosRecus);
      setStatutSync(sourceStocks === 'demo' || sourceCommandes === 'demo' ? 'hors_ligne' : 'synchronise');
      await ecrireCache<DonneesDepotCache>(CLES_CACHE.depotCommandes, {
        stocks: stocksRecus,
        commandes: commandesRecues,
        foyersEnTension: foyersRecus,
        reappros: reapprosRecus,
      });
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

  const ajusterStock = useCallback(
    async (formatId: number, corps: CorpsAjustementStock) => {
      if (!orgUuid) return;
      try {
        const { data } = await api.ajusterStock(orgUuid, formatId, corps);
        setStocks((precedent) => precedent.map((s) => (s.format.id === formatId ? data : s)));
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        setStocks((precedent) =>
          precedent.map((s) =>
            s.format.id === formatId
              ? {
                  ...s,
                  pleines: corps.pleines ?? s.pleines,
                  vides: corps.vides ?? s.vides,
                  seuil_plein_bas: corps.seuil_plein_bas ?? s.seuil_plein_bas,
                }
              : s
          )
        );
      }
    },
    [orgUuid]
  );

  const preparerCommande = useCallback(async (uuid: string) => {
    try {
      const { data } = await api.preparerCommande(uuid);
      setCommandes((precedent) => precedent.map((c) => (c.uuid === uuid ? { ...c, ...data } : c)));
    } catch (erreur) {
      if (!MODE_DEMO) throw erreur;
      setCommandes((precedent) => {
        const cible = precedent.find((c) => c.uuid === uuid);
        if (cible) {
          setStocks((stocksPrecedents) =>
            stocksPrecedents.map((s) =>
              s.format.id === cible.format.id ? { ...s, pleines: Math.max(0, s.pleines - cible.quantite) } : s
            )
          );
        }
        return precedent.map((c) => (c.uuid === uuid ? { ...c, statut: 'preparee' } : c));
      });
    }
  }, []);

  const affecterLivraison = useCallback(
    async (uuid: string, livreurUserId?: string) => {
      try {
        const { data } = await api.affecterLivraison(uuid, { livreur_user_id: livreurUserId });
        setCommandes((precedent) => precedent.map((c) => (c.uuid === uuid ? { ...c, livraison: data } : c)));
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        const livreurChoisi = livreurs.find((l) => l.user_id === livreurUserId) ?? null;
        setCommandes((precedent) =>
          precedent.map((c) =>
            c.uuid === uuid
              ? {
                  ...c,
                  livraison: {
                    id: Date.now(),
                    commande_uuid: uuid,
                    livreur_user_id: livreurChoisi?.user_id ?? null,
                    livreur_nom: livreurChoisi?.nom ?? null,
                    statut: 'affectee',
                    vides_recuperes: null,
                    created_at: new Date().toISOString(),
                  },
                }
              : c
          )
        );
      }
    },
    [livreurs]
  );

  const creerProposition = useCallback(
    async (corps: CorpsCreationProposition) => {
      if (!orgUuid) return;
      try {
        const { data } = await api.creerProposition(orgUuid, corps);
        setCommandes((precedent) => [
          { ...data, site: { uuid: corps.site_uuid, nom: 'Foyer visé', adresse: null } },
          ...precedent,
        ]);
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        const format = stocks.find((s) => s.format.id === corps.format_id)?.format ?? {
          id: corps.format_id,
          code: '?',
          marque: '?',
          tare_nominale_g: 0,
          contenance_gaz_g: 0,
        };
        setCommandes((precedent) => [
          {
            uuid: `proposition-locale-${Date.now()}`,
            site_uuid: corps.site_uuid,
            site: { uuid: corps.site_uuid, nom: 'Foyer visé', adresse: null },
            format,
            quantite: corps.quantite,
            depot_uuid: orgUuid,
            type: 'echange',
            statut: 'proposee',
            commission_g: 0,
            mode_paiement: 'a_la_livraison',
            statut_paiement: 'en_attente',
            created_at: new Date().toISOString(),
            livraison: null,
          },
          ...precedent,
        ]);
      }
    },
    [orgUuid, stocks]
  );

  /**
   * Propose une livraison depuis la file des foyers en tension (ADR 0009
   * §B/§C) : le site_uuid vient de la file elle-même, jamais d'une saisie
   * manuelle. Retire l'entrée de la file dès l'envoi (anti-doublon local).
   */
  const proposerDepuisTension = useCallback(
    async (foyer: FoyerEnTension) => {
      if (!orgUuid) return;
      const corps: CorpsCreationProposition = { site_uuid: foyer.site_uuid, format_id: foyer.format.id, quantite: 1 };
      try {
        const { data } = await api.creerProposition(orgUuid, corps);
        setCommandes((precedent) => [
          { ...data, site: { uuid: foyer.site_uuid, nom: foyer.nom, adresse: null } },
          ...precedent,
        ]);
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        setCommandes((precedent) => [
          {
            uuid: `proposition-locale-${Date.now()}`,
            site_uuid: foyer.site_uuid,
            site: { uuid: foyer.site_uuid, nom: foyer.nom, adresse: null },
            format: foyer.format,
            quantite: 1,
            depot_uuid: orgUuid,
            type: 'echange',
            statut: 'proposee',
            commission_g: 0,
            mode_paiement: 'a_la_livraison',
            statut_paiement: 'en_attente',
            created_at: new Date().toISOString(),
            livraison: null,
          },
          ...precedent,
        ]);
      }
      setFoyersEnTension((precedent) => precedent.filter((f) => f.site_uuid !== foyer.site_uuid));
    },
    [orgUuid]
  );

  /** Confirme (et ajuste au besoin) un réappro proposé par la plateforme (ADR 0009 §D). */
  const confirmerReappro = useCallback(async (uuid: string, corps: CorpsConfirmationReappro = {}) => {
    try {
      await api.confirmerReappro(uuid, corps);
    } catch (erreur) {
      if (!MODE_DEMO) throw erreur;
    }
    setReappros((precedent) => precedent.filter((r) => r.uuid !== uuid));
  }, []);

  const valeur = useMemo<ContexteDepotValeur>(
    () => ({
      orgUuid,
      orgNom,
      stocks,
      commandes,
      livreurs,
      foyersEnTension,
      reappros,
      chargementInitial,
      statutSync,
      rafraichir,
      ajusterStock,
      preparerCommande,
      affecterLivraison,
      creerProposition,
      proposerDepuisTension,
      confirmerReappro,
    }),
    [
      orgUuid,
      orgNom,
      stocks,
      commandes,
      livreurs,
      foyersEnTension,
      reappros,
      chargementInitial,
      statutSync,
      rafraichir,
      ajusterStock,
      preparerCommande,
      affecterLivraison,
      creerProposition,
      proposerDepuisTension,
      confirmerReappro,
    ]
  );

  return <ContexteDepot.Provider value={valeur}>{children}</ContexteDepot.Provider>;
}

export function useDepot(): ContexteDepotValeur {
  const contexte = useContext(ContexteDepot);
  if (!contexte) {
    throw new Error('useDepot doit être utilisé sous DepotProvider');
  }
  return contexte;
}
