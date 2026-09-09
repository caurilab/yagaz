/**
 * Une fonction par endpoint du contrat (docs/09-contrat-api.md §Endpoints).
 * Aucune logique métier ici : uniquement la forme de la requête/réponse.
 */
import { requeteApi } from './client';
import type {
  Alerte,
  Analyse,
  Bouteille,
  Commande,
  CommandeDepot,
  CorpsAffectationLivraison,
  CorpsAjustementStock,
  CorpsAjustementTournee,
  CorpsAnalyse,
  CorpsConnexion,
  CorpsCreationBouteille,
  CorpsCreationCommande,
  CorpsCreationEquipement,
  CorpsCreationProposition,
  CorpsCreationSite,
  CorpsCreationTournee,
  CorpsHistorique,
  CorpsInscription,
  CorpsConfirmationReappro,
  CorpsMajAlerte,
  CorpsMajBouteille,
  CorpsMajEquipement,
  CorpsMajNotification,
  CorpsMajSite,
  CorpsMajStatutLivraison,
  CorpsPartageSite,
  CorpsPlateau,
  CorpsPropositionLivreur,
  CorpsReglagesAlertes,
  CorpsReponseCommande,
  Depot,
  DepotConsolide,
  Equipement,
  EvenementHistorique,
  Format,
  FoyerEnTension,
  FoyerEnTensionLivreur,
  Livraison,
  Marque,
  MembreLivreur,
  MesRoles,
  MissionLivreur,
  Notification,
  Paiement,
  Pagination,
  PeriodeTemperature,
  PointMesure,
  Reappro,
  ReapproMandataire,
  RecuPaiement,
  ReponseAuth,
  Site,
  StatutCommande,
  StatutLivraison,
  StatutNotification,
  StockFormat,
  SuiviCommande,
  TemperatureAnalyse,
  TemperatureSite,
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

// --- Marques (référentiel) ---

export function listerMarques() {
  return requeteApi<{ data: Marque[] }>('/marques');
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

// --- Équipements (registre unifié foyer, ADR 0012) ---

export function listerEquipements() {
  return requeteApi<{ data: Equipement[] }>('/equipements');
}

export function creerEquipement(corps: CorpsCreationEquipement) {
  return requeteApi<{ data: Equipement }>('/equipements', { methode: 'POST', corps });
}

export function majEquipement(uuid: string, corps: CorpsMajEquipement) {
  return requeteApi<{ data: Equipement }>(`/equipements/${uuid}`, { methode: 'PATCH', corps });
}

export function supprimerEquipement(uuid: string) {
  return requeteApi<void>(`/equipements/${uuid}`, { methode: 'DELETE' });
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

// --- Notifications (doc 11 §3) ---

export function notifications(statut?: StatutNotification) {
  const suffixe = statut ? `?statut=${statut}` : '';
  return requeteApi<{ data: Notification[] }>(`/notifications${suffixe}`);
}

export function marquerNotificationVue(id: number) {
  const corps: CorpsMajNotification = { statut: 'vue' };
  return requeteApi<{ data: Notification }>(`/notifications/${id}`, { methode: 'PATCH', corps });
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

/** Initie un paiement Mobile Money pour une commande `confirmee` du foyer. */
export function initierPaiement(uuid: string) {
  return requeteApi<{ data: Paiement }>(`/commandes/${uuid}/paiement`, { methode: 'POST' });
}

/** Statut courant du paiement d'une commande (à poller jusqu'à `regle`/`echoue`/`expire`). */
export function statutPaiement(uuid: string) {
  return requeteApi<{ data: Paiement }>(`/commandes/${uuid}/paiement`);
}

/** Timeline d'étapes + ETA estimée d'une commande jusqu'à la livraison. */
export function suiviCommande(uuid: string) {
  return requeteApi<{ data: SuiviCommande }>(`/commandes/${uuid}/suivi`);
}

/** Reçu du paiement d'une commande (réglé, ou estimé si encore « à la livraison »). */
export function recuCommande(uuid: string) {
  return requeteApi<{ data: RecuPaiement }>(`/commandes/${uuid}/recu`);
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

/** File des foyers de la zone de desserte du dépôt en tension (ADR 0009 §B). */
export function depotFoyersEnTension(orgUuid: string) {
  return requeteApi<{ data: FoyerEnTension[] }>(`/depots/${orgUuid}/foyers-en-tension`);
}

/** Réappros préparés par la plateforme pour ce dépôt, à confirmer/ajuster (ADR 0009 §D). */
export function depotReappros(orgUuid: string, statut?: StatutCommande) {
  const suffixe = statut ? `?statut=${statut}` : '';
  return requeteApi<{ data: Reappro[] }>(`/depots/${orgUuid}/reappros${suffixe}`);
}

export function confirmerReappro(uuid: string, corps: CorpsConfirmationReappro = {}) {
  return requeteApi<{ data: Reappro }>(`/commandes/${uuid}/confirmer-reappro`, { methode: 'POST', corps });
}

/**
 * Livreurs rattachés au dépôt (contrat doc 10, §4), pour peupler le sélecteur
 * de livreur à l'affectation d'une livraison. Réservé au gérant du dépôt.
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

/**
 * File actionnable des foyers habituels en tension de l'utilisateur courant
 * (ADR 0008, précision « maillon C »). C'est la source de `site_uuid` pour
 * `livreurProposition` - jamais la notification de seuil bas, minimale.
 */
export function livreurFoyersEnTension() {
  return requeteApi<{ data: FoyerEnTensionLivreur[] }>('/livreur/foyers-en-tension');
}

/** Proposition d'un livreur habituel pour un de ses foyers habituels en tension (ADR 0009 §C). */
export function livreurProposition(corps: CorpsPropositionLivreur) {
  return requeteApi<{ data: Commande }>('/livreur/propositions', { methode: 'POST', corps });
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

/** Réappros des dépôts du mandataire (parité web, doc 11 §1). */
export function mandataireReappros(orgUuid: string, statut?: StatutCommande) {
  const suffixe = statut ? `?statut=${statut}` : '';
  return requeteApi<{ data: ReapproMandataire[] }>(`/mandataires/${orgUuid}/reappros${suffixe}`);
}

// --- Historique unifié (foyer, doc 13 §1) ---

export function historique(params: CorpsHistorique = {}) {
  const p = new URLSearchParams();
  if (params.site_uuid) p.set('site_uuid', params.site_uuid);
  if (params.depuis) p.set('depuis', params.depuis);
  if (params.type) p.set('type', params.type);
  if (params.page) p.set('page', String(params.page));
  if (params.par_page) p.set('par_page', String(params.par_page));
  const suffixe = p.toString() ? `?${p.toString()}` : '';
  return requeteApi<{ data: EvenementHistorique[]; pagination: Pagination }>(`/historique${suffixe}`);
}

// --- Analyses (tableau de bord foyer, doc 13 §2) ---

export function analyse(params: CorpsAnalyse = {}) {
  const p = new URLSearchParams();
  if (params.site_uuid) p.set('site_uuid', params.site_uuid);
  if (params.periode) p.set('periode', params.periode);
  const suffixe = p.toString() ? `?${p.toString()}` : '';
  return requeteApi<{ data: Analyse }>(`/analyse${suffixe}`);
}

// --- Température & cuisson (ADR 0011, doc 13 §3) ---

/** Pas d'enveloppe `data` pour cet endpoint (contrat doc 13 §3). */
export function temperatureSite(uuid: string) {
  return requeteApi<TemperatureSite>(`/sites/${uuid}/temperature`);
}

/**
 * Analyse détaillée (drill-down) de la température du site : courbe horaire,
 * cuissons par heure, heure de pointe, période dominante, fréquence
 * (doc 13 §3). Réponse enveloppée dans `data` (contrairement à
 * `temperatureSite`) - on la déballe ici pour renvoyer directement l'analyse.
 */
export async function temperatureAnalyse(uuid: string, periode?: PeriodeTemperature): Promise<TemperatureAnalyse> {
  const suffixe = periode ? `?periode=${periode}` : '';
  const reponse = await requeteApi<{ data: TemperatureAnalyse }>(`/sites/${uuid}/temperature/analyse${suffixe}`);
  return reponse.data;
}
