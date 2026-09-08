/**
 * Sélection d'espace (foyer / dépôt / livreur) après connexion, contrat 10
 * §1 : `GET /mes-roles` -> { foyer, depots: [{uuid, nom}], livreur }.
 *
 * Un foyer simple (aucun rôle dépôt/livreur) est mis en espace "foyer"
 * automatiquement - comportement actuel inchangé. Un compte multi-rôle voit
 * un sélecteur d'espace (écran `choisir-espace`) ; le choix est mémorisé
 * (hors ligne compris) pour les prochaines ouvertures, et modifiable à tout
 * moment depuis les réglages de chaque espace.
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
import { avecRepliDemo, rolesDemo } from '../api/demo';
import type { EspaceType, MesRoles } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import { CLES_CACHE, ecrireCache, lireCache } from '../data/cache';

export interface OptionEspace {
  type: EspaceType;
  libelle: string;
}

interface ContexteEspaceValeur {
  roles: MesRoles | null;
  chargement: boolean;
  espaceActif: EspaceType | null;
  depotOrgActifUuid: string | null;
  mandataireOrgActifUuid: string | null;
  /** Espaces que ce compte peut ouvrir (toujours au moins "foyer"). */
  espacesDisponibles: OptionEspace[];
  definirEspace: (espace: EspaceType, orgUuid?: string) => void;
  definirDepotOrgActif: (orgUuid: string) => void;
  definirMandataireOrgActif: (orgUuid: string) => void;
  /** Réaffiche le sélecteur, pour "changer d'espace" depuis les réglages. */
  reinitialiserChoix: () => void;
}

const ContexteEspace = createContext<ContexteEspaceValeur | null>(null);

function libelleEspace(type: EspaceType): string {
  if (type === 'depot') return 'Dépôt';
  if (type === 'livreur') return 'Livreur';
  if (type === 'mandataire') return 'Mandataire';
  return 'Foyer';
}

export function EspaceProvider({ children }: { children: ReactNode }) {
  const { estConnecte } = useAuth();

  const [roles, setRoles] = useState<MesRoles | null>(null);
  const [chargement, setChargement] = useState(true);
  const [espaceActif, setEspaceActif] = useState<EspaceType | null>(null);
  const [depotOrgActifUuid, setDepotOrgActifUuid] = useState<string | null>(null);
  const [mandataireOrgActifUuid, setMandataireOrgActifUuid] = useState<string | null>(null);

  const dejaCharge = useRef(false);

  useEffect(() => {
    if (!estConnecte) {
      dejaCharge.current = false;
      setRoles(null);
      setEspaceActif(null);
      setDepotOrgActifUuid(null);
      setMandataireOrgActifUuid(null);
      setChargement(true);
      return;
    }
    if (dejaCharge.current) return;
    dejaCharge.current = true;

    (async () => {
      const [rolesBruts, espaceCache, orgCache, orgMandataireCache] = await Promise.all([
        avecRepliDemo(() => api.mesRoles(), rolesDemo),
        lireCache<EspaceType>(CLES_CACHE.espaceActif),
        lireCache<string>(CLES_CACHE.depotOrgActif),
        lireCache<string>(CLES_CACHE.mandataireOrgActif),
      ]);
      // Normalisation défensive : garantir que les listes existent toujours,
      // même si l'API (ou une version plus ancienne) omet un champ.
      const rolesRecus: MesRoles = {
        foyer: rolesBruts?.foyer ?? false,
        depots: rolesBruts?.depots ?? [],
        mandataires: rolesBruts?.mandataires ?? [],
        distributeurs: rolesBruts?.distributeurs ?? [],
        livreur: rolesBruts?.livreur ?? false,
      };
      setRoles(rolesRecus);

      const disponibles = calculerEspacesDisponibles(rolesRecus);
      const choixValide = espaceCache && disponibles.some((e) => e.type === espaceCache);

      if (disponibles.length === 1) {
        setEspaceActif(disponibles[0].type);
      } else if (choixValide) {
        setEspaceActif(espaceCache);
      } else {
        setEspaceActif(null);
      }

      if (orgCache && rolesRecus.depots.some((d) => d.uuid === orgCache)) {
        setDepotOrgActifUuid(orgCache);
      } else {
        setDepotOrgActifUuid(rolesRecus.depots[0]?.uuid ?? null);
      }

      if (orgMandataireCache && rolesRecus.mandataires.some((m) => m.uuid === orgMandataireCache)) {
        setMandataireOrgActifUuid(orgMandataireCache);
      } else {
        setMandataireOrgActifUuid(rolesRecus.mandataires[0]?.uuid ?? null);
      }

      setChargement(false);
    })();
  }, [estConnecte]);

  const definirDepotOrgActif = useCallback((orgUuid: string) => {
    setDepotOrgActifUuid(orgUuid);
    ecrireCache(CLES_CACHE.depotOrgActif, orgUuid);
  }, []);

  const definirMandataireOrgActif = useCallback((orgUuid: string) => {
    setMandataireOrgActifUuid(orgUuid);
    ecrireCache(CLES_CACHE.mandataireOrgActif, orgUuid);
  }, []);

  const definirEspace = useCallback(
    (espace: EspaceType, orgUuid?: string) => {
      setEspaceActif(espace);
      ecrireCache(CLES_CACHE.espaceActif, espace);
      if (espace === 'depot' && orgUuid) {
        definirDepotOrgActif(orgUuid);
      }
      if (espace === 'mandataire' && orgUuid) {
        definirMandataireOrgActif(orgUuid);
      }
    },
    [definirDepotOrgActif, definirMandataireOrgActif]
  );

  const reinitialiserChoix = useCallback(() => {
    setEspaceActif(null);
  }, []);

  const espacesDisponibles = useMemo(() => calculerEspacesDisponibles(roles), [roles]);

  const valeur = useMemo<ContexteEspaceValeur>(
    () => ({
      roles,
      chargement,
      espaceActif,
      depotOrgActifUuid,
      mandataireOrgActifUuid,
      espacesDisponibles,
      definirEspace,
      definirDepotOrgActif,
      definirMandataireOrgActif,
      reinitialiserChoix,
    }),
    [
      roles,
      chargement,
      espaceActif,
      depotOrgActifUuid,
      mandataireOrgActifUuid,
      espacesDisponibles,
      definirEspace,
      definirDepotOrgActif,
      definirMandataireOrgActif,
      reinitialiserChoix,
    ]
  );

  return <ContexteEspace.Provider value={valeur}>{children}</ContexteEspace.Provider>;
}

function calculerEspacesDisponibles(roles: MesRoles | null): OptionEspace[] {
  if (!roles) return [{ type: 'foyer', libelle: libelleEspace('foyer') }];
  const options: OptionEspace[] = [];
  if (roles.foyer) options.push({ type: 'foyer', libelle: libelleEspace('foyer') });
  if ((roles.depots?.length ?? 0) > 0) options.push({ type: 'depot', libelle: libelleEspace('depot') });
  if (roles.livreur) options.push({ type: 'livreur', libelle: libelleEspace('livreur') });
  if ((roles.mandataires?.length ?? 0) > 0) options.push({ type: 'mandataire', libelle: libelleEspace('mandataire') });
  return options.length > 0 ? options : [{ type: 'foyer', libelle: libelleEspace('foyer') }];
}

export function useEspace(): ContexteEspaceValeur {
  const contexte = useContext(ContexteEspace);
  if (!contexte) {
    throw new Error('useEspace doit être utilisé sous EspaceProvider');
  }
  return contexte;
}
