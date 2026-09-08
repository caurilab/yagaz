/**
 * Une fonction par endpoint du contrat (docs/09-contrat-api.md §Endpoints).
 * Aucune logique métier ici : uniquement la forme de la requête/réponse.
 */
import { requeteApi } from './client';
import type {
  Alerte,
  Bouteille,
  Commande,
  CommandeDepot,
  CorpsAffectationLivraison,
  CorpsAjustementStock,
  CorpsAjustementTournee,
  CorpsConnexion,
  CorpsCreationBouteille,
  CorpsCreationCommande,
  CorpsCreationProposition,
  CorpsCreationSite,
  CorpsCreationTournee,
  CorpsInscription,
  CorpsMajAlerte,
  CorpsMajBouteille,
  CorpsMajSite,
  CorpsMajStatutLivraison,
  CorpsPartageSite,
  CorpsPlateau,
  CorpsReglagesAlertes,
  CorpsReponseCommande,
  Depot,
  DepotConsolide,
  Format,
  Livraison,
  MembreLivreur,
  MesRoles,
  MissionLivreur,
  PointMesure,
  ReponseAuth,
  Site,
  StatutCommande,
  StatutLivraison,
  StockFormat,
  Tournee,
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

// --- Rôles et espaces (contrat 10 §1) ---

export function mesRoles() {
  return requeteApi<MesRoles>('/mes-roles');
}

// --- Commandes - foyer (contrat 10 §3) ---

export function creerCommande(corps: CorpsCreationCommande) {
  return requeteApi<{ data: Commande }>('/commandes', { methode: 'POST', corps });
}

export function listerCommandesFoyer() {
  return requeteApi<{ data: Commande[] }>('/commandes');
}

export function commande(uuid: string) {
  return requeteApi<{ data: Commande }>(`/commandes/${uuid}`);
}

export function repondreProposition(uuid: string, corps: CorpsReponseCommande) {
  return requeteApi<{ data: Commande }>(`/commandes/${uuid}/reponse`, { methode: 'POST', corps });
}

// --- Dépôt - stock et commandes (contrat 10 §4) ---

export function depotStocks(orgUuid: string) {
  return requeteApi<{ data: StockFormat[] }>(`/depots/${orgUuid}/stocks`);
}

export function ajusterStock(orgUuid: string, formatId: number, corps: CorpsAjustementStock) {
  return requeteApi<{ data: StockFormat }>(`/depots/${orgUuid}/stocks/${formatId}`, {
    methode: 'PATCH',
    corps,
  });
}

export function depotCommandes(orgUuid: string, statut?: StatutCommande) {
  const suffixe = statut ? `?statut=${statut}` : '';
  return requeteApi<{ data: CommandeDepot[] }>(`/depots/${orgUuid}/commandes${suffixe}`);
}

export function preparerCommande(uuid: string) {
  return requeteApi<{ data: Commande }>(`/commandes/${uuid}/preparer`, { methode: 'PATCH' });
}

export function affecterLivraison(uuid: string, corps: CorpsAffectationLivraison) {
  return requeteApi<{ data: Livraison }>(`/commandes/${uuid}/livraison`, { methode: 'POST', corps });
}

export function creerProposition(orgUuid: string, corps: CorpsCreationProposition) {
  return requeteApi<{ data: Commande }>(`/depots/${orgUuid}/propositions`, { methode: 'POST', corps });
}

/**
 * Non listé explicitement au contrat 10 : extension minimale, même
 * convention de route (`/depots/{orgUuid}/...`), pour peupler le
 * sélecteur de livreur à l'affectation (§4 "le livreur doit être rattaché
 * au dépôt"). À confirmer côté API.
 */
export function depotLivreurs(orgUuid: string) {
  return requeteApi<{ data: MembreLivreur[] }>(`/depots/${orgUuid}/livreurs`);
}

// --- Livreur - missions (contrat 10 §5) ---

export function livreurMissions(statut?: StatutLivraison) {
  const suffixe = statut ? `?statut=${statut}` : '';
  return requeteApi<{ data: MissionLivreur[] }>(`/livreur/missions${suffixe}`);
}

export function majStatutLivraison(id: number, corps: CorpsMajStatutLivraison) {
  return requeteApi<{ data: Livraison }>(`/livraisons/${id}/statut`, { methode: 'PATCH', corps });
}

// --- Mandataire - dépôts et tournées (doc 11 §1) ---

export function mandataireDepots(orgUuid: string) {
  return requeteApi<{ data: DepotConsolide[] }>(`/mandataires/${orgUuid}/depots`);
}

export function mandataireTournees(orgUuid: string) {
  return requeteApi<{ data: Tournee[] }>(`/mandataires/${orgUuid}/tournees`);
}

export function creerTournee(orgUuid: string, corps: CorpsCreationTournee) {
  return requeteApi<{ data: Tournee }>(`/mandataires/${orgUuid}/tournees`, { methode: 'POST', corps });
}

export function ajusterTournee(uuid: string, corps: CorpsAjustementTournee) {
  return requeteApi<{ data: Tournee }>(`/tournees/${uuid}`, { methode: 'PATCH', corps });
}
