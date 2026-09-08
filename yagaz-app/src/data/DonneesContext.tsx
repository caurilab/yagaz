/**
 * Données foyer (sites, bouteilles, formats, alertes) : chargement, cache
 * hors ligne, sélection du site courant, et actions qui touchent l'API
 * (avec repli démo local quand l'API ne répond pas).
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
  alertesDemo,
  bouteillesDemo,
  executerAvecSource,
  formatsDemo,
  marquesDemo,
  sitesDemo,
} from '../api/demo';
import type {
  Alerte,
  Bouteille,
  CorpsCreationBouteille,
  CorpsMajBouteille,
  Format,
  Marque,
  RoleBouteille,
  Site,
} from '../api/types';
import { useAuth } from '../auth/AuthContext';
import { CLES_CACHE, ecrireCache, lireCache } from './cache';

export type StatutSync = 'chargement' | 'synchronise' | 'hors_ligne';

interface DonneesCache {
  sites: Site[];
  bouteillesParSite: Record<string, Bouteille[]>;
  formats: Format[];
  marques: Marque[];
  alertes: Alerte[];
}

interface ContexteDonneesValeur {
  sites: Site[];
  siteActifUuid: string | null;
  siteActif: Site | null;
  definirSiteActif: (uuid: string) => void;
  bouteilles: Bouteille[];
  bouteilleActive: Bouteille | undefined;
  formats: Format[];
  marques: Marque[];
  alertes: Alerte[];
  statutSync: StatutSync;
  derniereSyncAt: string | null;
  chargementInitial: boolean;
  rafraichir: () => Promise<void>;
  activerBouteille: (uuid: string) => Promise<void>;
  enregistrerBouteille: (corps: CorpsCreationBouteille) => Promise<Bouteille>;
  /** Édition libre d'une bouteille (rôle, seuil, tare, format/marque - contrat PATCH). */
  modifierBouteille: (uuid: string, corps: CorpsMajBouteille) => Promise<Bouteille>;
  /** Suppression d'une bouteille (contrat DELETE /bouteilles/{uuid}) - autorisation gérée côté API. */
  supprimerBouteille: (uuid: string) => Promise<void>;
  majAlerteStatut: (id: number, statut: 'vue' | 'resolue') => Promise<void>;
}

const ContexteDonnees = createContext<ContexteDonneesValeur | null>(null);

function permuterActive(bouteilles: Bouteille[], uuidNouvelleActive: string): Bouteille[] {
  return bouteilles.map((bouteille) => {
    if (bouteille.uuid === uuidNouvelleActive) {
      return { ...bouteille, role_bouteille: 'active' as RoleBouteille };
    }
    if (bouteille.role_bouteille === 'active') {
      return { ...bouteille, role_bouteille: 'secours' as RoleBouteille };
    }
    return bouteille;
  });
}

let compteurUuidDemo = 0;
function genererUuidLocal(prefixe: string): string {
  compteurUuidDemo += 1;
  return `${prefixe}-local-${Date.now()}-${compteurUuidDemo}`;
}

export function DonneesProvider({ children }: { children: ReactNode }) {
  const { estConnecte } = useAuth();

  const [sites, setSites] = useState<Site[]>([]);
  const [siteActifUuid, setSiteActifUuid] = useState<string | null>(null);
  const [bouteillesParSite, setBouteillesParSite] = useState<Record<string, Bouteille[]>>({});
  const [formats, setFormats] = useState<Format[]>([]);
  const [marques, setMarques] = useState<Marque[]>([]);
  const [alertes, setAlertes] = useState<Alerte[]>([]);
  const [statutSync, setStatutSync] = useState<StatutSync>('chargement');
  const [derniereSyncAt, setDerniereSyncAt] = useState<string | null>(null);
  const [chargementInitial, setChargementInitial] = useState(true);

  const dejaCharge = useRef(false);

  // 1) Lecture immédiate du cache local (jamais de page blanche hors ligne).
  useEffect(() => {
    (async () => {
      const cache = await lireCache<DonneesCache>(CLES_CACHE.sites);
      const siteActifCache = await lireCache<string>(CLES_CACHE.siteActif);
      if (cache) {
        setSites(cache.sites);
        setBouteillesParSite(cache.bouteillesParSite);
        setFormats(cache.formats);
        setMarques(cache.marques ?? []);
        setAlertes(cache.alertes);
        setSiteActifUuid(siteActifCache ?? cache.sites[0]?.uuid ?? null);
        setChargementInitial(false);
      }
    })();
  }, []);

  const rafraichir = useCallback(async () => {
    setStatutSync((precedent) => (precedent === 'synchronise' ? precedent : 'chargement'));
    try {
      const { data: sitesRecus, source: sourceSites } = await executerAvecSource(
        () => api.listerSites().then((r) => r.data),
        sitesDemo
      );

      const { data: formatsRecus, source: sourceFormats } = await executerAvecSource(
        () => api.listerFormats().then((r) => r.data),
        formatsDemo
      );

      const { data: marquesRecues, source: sourceMarques } = await executerAvecSource(
        () => api.listerMarques().then((r) => r.data),
        marquesDemo
      );

      const bouteillesEntrees = await Promise.all(
        sitesRecus.map(async (site) => {
          const { data, source } = await executerAvecSource(
            () => api.listerBouteilles(site.uuid).then((r) => r.data),
            bouteillesDemo[site.uuid] ?? []
          );
          return { uuid: site.uuid, data, source };
        })
      );
      const bouteillesRecues: Record<string, Bouteille[]> = {};
      let uneSourceDemo = sourceSites === 'demo' || sourceFormats === 'demo' || sourceMarques === 'demo';
      for (const entree of bouteillesEntrees) {
        bouteillesRecues[entree.uuid] = entree.data;
        if (entree.source === 'demo') uneSourceDemo = true;
      }

      const { data: alertesRecues, source: sourceAlertes } = await executerAvecSource(
        () => api.listerAlertes().then((r) => r.data),
        alertesDemo
      );
      if (sourceAlertes === 'demo') uneSourceDemo = true;

      setSites(sitesRecus);
      setFormats(formatsRecus);
      setMarques(marquesRecues);
      setBouteillesParSite(bouteillesRecues);
      setAlertes(alertesRecues);
      setSiteActifUuid((precedent) => precedent ?? sitesRecus[0]?.uuid ?? null);

      const maintenant = new Date().toISOString();
      setDerniereSyncAt(maintenant);
      setStatutSync(uneSourceDemo ? 'hors_ligne' : 'synchronise');

      await ecrireCache<DonneesCache>(CLES_CACHE.sites, {
        sites: sitesRecus,
        bouteillesParSite: bouteillesRecues,
        formats: formatsRecus,
        marques: marquesRecues,
        alertes: alertesRecues,
      });
    } catch {
      // Ni l'API ni le repli démo n'ont répondu (MODE_DEMO désactivé) :
      // on conserve la dernière valeur connue déjà en état/cache.
      setStatutSync('hors_ligne');
    } finally {
      setChargementInitial(false);
    }
  }, []);

  // 2) Synchronisation réseau (ou démo) une fois authentifié.
  useEffect(() => {
    if (!estConnecte || dejaCharge.current) return;
    dejaCharge.current = true;
    rafraichir();
  }, [estConnecte, rafraichir]);

  // Réinitialisation à la déconnexion.
  useEffect(() => {
    if (estConnecte) return;
    dejaCharge.current = false;
  }, [estConnecte]);

  const definirSiteActif = useCallback((uuid: string) => {
    setSiteActifUuid(uuid);
    ecrireCache(CLES_CACHE.siteActif, uuid);
  }, []);

  const appliquerBouteillesSite = useCallback(
    (siteUuid: string, valeur: Bouteille[]) => {
      setBouteillesParSite((precedent) => {
        const suivant = { ...precedent, [siteUuid]: valeur };
        ecrireCache<DonneesCache>(CLES_CACHE.sites, {
          sites,
          bouteillesParSite: suivant,
          formats,
          marques,
          alertes,
        });
        return suivant;
      });
    },
    [sites, formats, marques, alertes]
  );

  const activerBouteille = useCallback(
    async (uuid: string) => {
      if (!siteActifUuid) return;
      const actuelles = bouteillesParSite[siteActifUuid] ?? [];
      try {
        const { data: bouteille } = await api.patchBouteille(uuid, { role_bouteille: 'active' });
        const misesAJour = actuelles.map((b) => (b.uuid === bouteille.uuid ? bouteille : b));
        appliquerBouteillesSite(siteActifUuid, permuterActive(misesAJour, bouteille.uuid));
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        appliquerBouteillesSite(siteActifUuid, permuterActive(actuelles, uuid));
      }
    },
    [siteActifUuid, bouteillesParSite, appliquerBouteillesSite]
  );

  const enregistrerBouteille = useCallback(
    async (corps: CorpsCreationBouteille): Promise<Bouteille> => {
      if (!siteActifUuid) throw new Error('Aucun site actif.');
      const actuelles = bouteillesParSite[siteActifUuid] ?? [];
      try {
        const { data: bouteille } = await api.creerBouteille(siteActifUuid, corps);
        const roleFinal = bouteille.role_bouteille;
        const nouvelleListe =
          roleFinal === 'active'
            ? permuterActive([...actuelles, bouteille], bouteille.uuid)
            : [...actuelles, bouteille];
        appliquerBouteillesSite(siteActifUuid, nouvelleListe);
        return bouteille;
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        const format = formats.find((f) => f.id === corps.format_id) ?? formats[0];
        const tareFiable = corps.tare_g != null && corps.tare_source !== 'nominale';
        const roleFinal: RoleBouteille = corps.role_bouteille ?? (actuelles.length === 0 ? 'active' : 'secours');
        const gazG = format?.contenance_gaz_g ?? 12500;
        const debit = 150;
        const nouvelleBouteille: Bouteille = {
          uuid: genererUuidLocal('bouteille'),
          site_uuid: siteActifUuid,
          format: format ?? { id: 0, code: '?', marque: '?', tare_nominale_g: 0, contenance_gaz_g: gazG },
          role_bouteille: roleFinal,
          tare_g: corps.tare_g ?? format?.tare_nominale_g ?? null,
          tare_source: corps.tare_source ?? (corps.tare_g != null ? 'saisie' : 'nominale'),
          tare_fiable: tareFiable,
          seuil_bas_pct: 20,
          plateau_uid: corps.plateau_uid ?? null,
          created_at: new Date().toISOString(),
          niveau: {
            gaz_g: gazG,
            niveau_pct: 100,
            etat: 'plein',
            autonomie_min: Math.round((gazG / debit) * 60),
            autonomie_heures: Math.round(gazG / debit),
            debit_g_par_h: debit,
            tare_fiable: tareFiable,
            estimation: !tareFiable,
            calcule_at: new Date().toISOString(),
            frais: true,
          },
        };
        const nouvelleListe =
          roleFinal === 'active'
            ? permuterActive([...actuelles, nouvelleBouteille], nouvelleBouteille.uuid)
            : [...actuelles, nouvelleBouteille];
        appliquerBouteillesSite(siteActifUuid, nouvelleListe);
        return nouvelleBouteille;
      }
    },
    [siteActifUuid, bouteillesParSite, formats, appliquerBouteillesSite]
  );

  const modifierBouteille = useCallback(
    async (uuid: string, corps: CorpsMajBouteille): Promise<Bouteille> => {
      if (!siteActifUuid) throw new Error('Aucun site actif.');
      const actuelles = bouteillesParSite[siteActifUuid] ?? [];
      try {
        const { data: bouteille } = await api.patchBouteille(uuid, corps);
        const misesAJour = actuelles.map((b) => (b.uuid === bouteille.uuid ? bouteille : b));
        appliquerBouteillesSite(
          siteActifUuid,
          corps.role_bouteille === 'active' ? permuterActive(misesAJour, bouteille.uuid) : misesAJour
        );
        return bouteille;
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
        const formatChoisi = corps.format_id != null ? formats.find((f) => f.id === corps.format_id) : undefined;
        const misesAJour = actuelles.map((b) => {
          if (b.uuid !== uuid) return b;
          return {
            ...b,
            ...(corps.role_bouteille !== undefined ? { role_bouteille: corps.role_bouteille } : {}),
            ...(corps.seuil_bas_pct !== undefined ? { seuil_bas_pct: corps.seuil_bas_pct } : {}),
            ...(corps.tare_g !== undefined ? { tare_g: corps.tare_g } : {}),
            ...(corps.tare_source !== undefined ? { tare_source: corps.tare_source } : {}),
            ...(formatChoisi ? { format: formatChoisi } : {}),
          };
        });
        const finale = corps.role_bouteille === 'active' ? permuterActive(misesAJour, uuid) : misesAJour;
        appliquerBouteillesSite(siteActifUuid, finale);
        const bouteilleMaj = finale.find((b) => b.uuid === uuid);
        if (!bouteilleMaj) throw erreur;
        return bouteilleMaj;
      }
    },
    [siteActifUuid, bouteillesParSite, formats, appliquerBouteillesSite]
  );

  const supprimerBouteille = useCallback(
    async (uuid: string) => {
      if (!siteActifUuid) return;
      const actuelles = bouteillesParSite[siteActifUuid] ?? [];
      try {
        await api.supprimerBouteille(uuid);
      } catch (erreur) {
        if (!MODE_DEMO) throw erreur;
      }
      appliquerBouteillesSite(
        siteActifUuid,
        actuelles.filter((b) => b.uuid !== uuid)
      );
    },
    [siteActifUuid, bouteillesParSite, appliquerBouteillesSite]
  );

  const majAlerteStatut = useCallback(async (id: number, statut: 'vue' | 'resolue') => {
    try {
      await api.majAlerte(id, { statut });
    } catch (erreur) {
      if (!MODE_DEMO) throw erreur;
    }
    setAlertes((precedent) => precedent.map((a) => (a.id === id ? { ...a, statut } : a)));
  }, []);

  const siteActif = useMemo(() => sites.find((s) => s.uuid === siteActifUuid) ?? null, [sites, siteActifUuid]);
  const bouteilles = useMemo(
    () => (siteActifUuid ? bouteillesParSite[siteActifUuid] ?? [] : []),
    [siteActifUuid, bouteillesParSite]
  );
  const bouteilleActive = useMemo(() => bouteilles.find((b) => b.role_bouteille === 'active'), [bouteilles]);

  const valeur = useMemo<ContexteDonneesValeur>(
    () => ({
      sites,
      siteActifUuid,
      siteActif,
      definirSiteActif,
      bouteilles,
      bouteilleActive,
      formats,
      marques,
      alertes,
      statutSync,
      derniereSyncAt,
      chargementInitial,
      rafraichir,
      activerBouteille,
      enregistrerBouteille,
      modifierBouteille,
      supprimerBouteille,
      majAlerteStatut,
    }),
    [
      sites,
      siteActifUuid,
      siteActif,
      definirSiteActif,
      bouteilles,
      bouteilleActive,
      formats,
      marques,
      alertes,
      statutSync,
      derniereSyncAt,
      chargementInitial,
      rafraichir,
      activerBouteille,
      enregistrerBouteille,
      modifierBouteille,
      supprimerBouteille,
      majAlerteStatut,
    ]
  );

  return <ContexteDonnees.Provider value={valeur}>{children}</ContexteDonnees.Provider>;
}

export function useDonnees(): ContexteDonneesValeur {
  const contexte = useContext(ContexteDonnees);
  if (!contexte) {
    throw new Error('useDonnees doit être utilisé sous DonneesProvider');
  }
  return contexte;
}
