/**
 * Une fonction par endpoint du contrat (docs/09-contrat-api.md §Endpoints).
 * Aucune logique métier ici : uniquement la forme de la requête/réponse.
 */
import { requeteApi } from './client';
import type {
  Alerte,
  Bouteille,
  CorpsConnexion,
  CorpsCreationBouteille,
  CorpsCreationSite,
  CorpsInscription,
  CorpsMajAlerte,
  CorpsMajBouteille,
  CorpsMajSite,
  CorpsPartageSite,
  CorpsPlateau,
  CorpsReglagesAlertes,
  Depot,
  Format,
  PointMesure,
  ReponseAuth,
  Site,
  User,
} from './types';

// --- Authentification ---

export function inscription(corps: CorpsInscription) {
  return requeteApi<ReponseAuth>('/auth/register', { methode: 'POST', corps, requiertAuth: false });
}

export function connexion(corps: CorpsConnexion) {
  return requeteApi<ReponseAuth>('/auth/login', { methode: 'POST', corps, requiertAuth: false });
}

export function deconnexion() {
  return requeteApi<void>('/auth/logout', { methode: 'POST' });
}

export function moi() {
  return requeteApi<{ user: User }>('/me');
}

// --- Sites ---

export function listerSites() {
  return requeteApi<{ data: Site[] }>('/sites');
}

export function creerSite(corps: CorpsCreationSite) {
  return requeteApi<{ data: Site }>('/sites', { methode: 'POST', corps });
}

export function detailSite(uuid: string) {
  return requeteApi<{ data: Site }>(`/sites/${uuid}`);
}

export function majSite(uuid: string, corps: CorpsMajSite) {
  return requeteApi<{ data: Site }>(`/sites/${uuid}`, { methode: 'PATCH', corps });
}

export function partagerSite(uuid: string, corps: CorpsPartageSite) {
  return requeteApi<void>(`/sites/${uuid}/partages`, { methode: 'POST', corps });
}

export function retirerPartageSite(uuid: string, userUuid: string) {
  return requeteApi<void>(`/sites/${uuid}/partages/${userUuid}`, { methode: 'DELETE' });
}

// --- Formats ---

export function listerFormats() {
  return requeteApi<{ data: Format[] }>('/formats');
}

// --- Bouteilles ---

export function listerBouteilles(siteUuid: string) {
  return requeteApi<{ data: Bouteille[] }>(`/sites/${siteUuid}/bouteilles`);
}

export function creerBouteille(siteUuid: string, corps: CorpsCreationBouteille) {
  return requeteApi<{ data: Bouteille }>(`/sites/${siteUuid}/bouteilles`, { methode: 'POST', corps });
}

export function detailBouteille(uuid: string) {
  return requeteApi<{ data: Bouteille }>(`/bouteilles/${uuid}`);
}

export function patchBouteille(uuid: string, corps: CorpsMajBouteille) {
  return requeteApi<{ data: Bouteille }>(`/bouteilles/${uuid}`, { methode: 'PATCH', corps });
}

export function lierPlateau(uuid: string, corps: CorpsPlateau) {
  return requeteApi<void>(`/bouteilles/${uuid}/plateau`, { methode: 'POST', corps });
}

export function delierPlateau(uuid: string) {
  return requeteApi<void>(`/bouteilles/${uuid}/plateau`, { methode: 'DELETE' });
}

export function supprimerBouteille(uuid: string) {
  return requeteApi<void>(`/bouteilles/${uuid}`, { methode: 'DELETE' });
}

export function mesuresBouteille(uuid: string, depuis?: string, pas: 'heure' = 'heure') {
  const params = new URLSearchParams();
  if (depuis) params.set('depuis', depuis);
  params.set('pas', pas);
  return requeteApi<{ data: PointMesure[] }>(`/bouteilles/${uuid}/mesures?${params.toString()}`);
}

// --- Alertes ---

export function listerAlertes(statut?: 'emise') {
  const suffixe = statut ? `?statut=${statut}` : '';
  return requeteApi<{ data: Alerte[] }>(`/alertes${suffixe}`);
}

export function majAlerte(id: number, corps: CorpsMajAlerte) {
  return requeteApi<{ data: Alerte }>(`/alertes/${id}`, { methode: 'PATCH', corps });
}

export function majReglagesAlertes(corps: CorpsReglagesAlertes) {
  return requeteApi<void>('/me/reglages-alertes', { methode: 'PATCH', corps });
}

// --- Dépôts (lecture seule) ---

export function listerDepots(lat: number, lng: number, formatId: number) {
  const params = new URLSearchParams({
    lat: String(lat),
    lng: String(lng),
    format_id: String(formatId),
  });
  return requeteApi<{ data: Depot[] }>(`/depots?${params.toString()}`);
}
