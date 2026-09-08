/**
 * Types calqués sur le contrat d'API (docs/09-contrat-api.md).
 * Toute forme JSON échangée avec `yagaz-api` doit correspondre exactement
 * à ces types - ne pas improviser de champs supplémentaires.
 */

// --- Utilisateur & auth ---

export type Langue = 'fr';

export interface User {
  uuid: string;
  nom: string;
  telephone: string;
  langue: Langue;
}

export interface ReponseAuth {
  token: string;
  user: User;
}

export interface CorpsInscription {
  nom: string;
  telephone: string;
  mot_de_passe: string;
  langue?: Langue;
}

export interface CorpsConnexion {
  telephone: string;
  mot_de_passe: string;
}

// --- Erreurs API ---

export interface ErreurValidation {
  message: string;
  errors: Record<string, string[]>;
}

export class ErreurApi extends Error {
  statut: number;
  errors?: Record<string, string[]>;

  constructor(statut: number, message: string, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ErreurApi';
    this.statut = statut;
    this.errors = errors;
  }
}

// --- Niveau d'accès à un site ---

export type NiveauAcces = 'proprietaire' | 'gestionnaire' | 'lecture';

// --- Sites ---

export interface Site {
  uuid: string;
  nom: string;
  adresse: string | null;
  lat: number | null;
  lng: number | null;
  niveau_acces: NiveauAcces;
  nb_bouteilles: number;
  a_alerte_active: boolean;
}

export interface CorpsCreationSite {
  nom: string;
  adresse?: string;
  lat?: number;
  lng?: number;
}

export interface CorpsMajSite {
  nom?: string;
  adresse?: string;
  lat?: number;
  lng?: number;
}

export interface CorpsPartageSite {
  telephone: string;
  niveau: NiveauAcces;
}

// --- Formats (référentiel) ---

export interface Format {
  id: number;
  code: string;
  marque: string;
  tare_nominale_g: number;
  contenance_gaz_g: number;
}

// --- Niveau (objet embarqué dans une bouteille) ---

export type EtatNiveau = 'plein' | 'correct' | 'bas' | 'presque_vide' | 'inconnu';

export interface Niveau {
  gaz_g: number;
  niveau_pct: number;
  etat: EtatNiveau;
  autonomie_min: number;
  autonomie_heures: number;
  debit_g_par_h: number;
  tare_fiable: boolean;
  /** true tant que la tare n'est pas fiable ou que le débit est nominal */
  estimation: boolean;
  calcule_at: string;
  /** false si la dernière mesure est trop ancienne (hors ligne) */
  frais: boolean;
}

// --- Bouteilles ---

export type RoleBouteille = 'active' | 'secours';
export type SourceTare = 'nominale' | 'mesuree' | 'saisie';

export interface Bouteille {
  uuid: string;
  site_uuid: string;
  format: Format;
  role_bouteille: RoleBouteille;
  tare_g: number | null;
  tare_source: SourceTare;
  tare_fiable: boolean;
  seuil_bas_pct: number;
  plateau_uid: string | null;
  niveau: Niveau;
  created_at: string;
}

export interface CorpsCreationBouteille {
  format_id: number;
  tare_g?: number;
  tare_source?: SourceTare;
  role_bouteille?: RoleBouteille;
  plateau_uid?: string;
}

export interface CorpsMajBouteille {
  role_bouteille?: RoleBouteille;
  seuil_bas_pct?: number;
  tare_g?: number;
  tare_source?: SourceTare;
}

export interface CorpsPlateau {
  plateau_uid: string;
}

export interface PointMesure {
  at: string;
  niveau_pct: number;
  gaz_g: number;
}

// --- Alertes ---

export type TypeAlerte = 'seuil_bas' | 'proposition';
export type StatutAlerte = 'emise' | 'vue' | 'resolue';

export interface Alerte {
  id: number;
  site_uuid: string;
  bouteille_uuid: string | null;
  type: TypeAlerte;
  statut: StatutAlerte;
  message: string;
  created_at: string;
}

export interface CorpsMajAlerte {
  statut: 'vue' | 'resolue';
}

export type Canal = 'push' | 'sms' | 'whatsapp';

export interface CorpsReglagesAlertes {
  canaux: Canal[];
  livreur_habituel?: string;
}

// --- Notifications (doc 11 §3, ADR 0008/0009) ---

/**
 * Sous-ensemble minimal transmis au livreur habituel pour un foyer en
 * tension (ADR 0008) : jamais de niveau, d'autonomie, d'historique, ni
 * d'identifiant de site/bouteille - seulement de quoi apporter la bonne
 * recharge au bon endroit.
 */
export interface ContexteNotification {
  site_nom: string;
  zone: string;
  format_code: string;
}

export type StatutNotification = 'emise' | 'vue' | 'resolue';

export interface Notification {
  id: number;
  type: string;
  statut: StatutNotification;
  message: string;
  created_at: string;
  /** Présent uniquement pour les notifications de tension adressées à un livreur habituel (ADR 0008) - jamais de site_uuid/bouteille_uuid pour celles-ci. */
  contexte?: ContexteNotification;
}

export interface CorpsMajNotification {
  statut: 'vue';
}

// --- Dépôts (lecture seule, Phase 3) ---

export interface Depot {
  uuid: string;
  nom: string;
  adresse: string | null;
  distance_km: number;
  disponible: boolean;
}

// --- Rôles et espaces (Phase 4, contrat 10 §1) ---

export interface RoleDepot {
  uuid: string;
  nom: string;
}

/** Organisation `mandataire` dont l'utilisateur est membre (doc 11 §1). */
export interface RoleMandataire {
  uuid: string;
  nom: string;
}

export interface MesRoles {
  foyer: boolean;
  depots: RoleDepot[];
  livreur: boolean;
  mandataires: RoleMandataire[];
}

export type EspaceType = 'foyer' | 'depot' | 'livreur' | 'mandataire';

// --- Commandes (contrat 10 §2-3) ---

export type StatutCommande =
  | 'proposee'
  | 'confirmee'
  | 'preparee'
  | 'en_livraison'
  | 'livree'
  | 'annulee';

export interface Commande {
  uuid: string;
  site_uuid: string;
  format: Format;
  quantite: number;
  depot_uuid: string;
  statut: StatutCommande;
  commission_g: number;
  /** Défaut `a_la_livraison` (contrat §2 bis, paiement Mobile Money). */
  mode_paiement: ModePaiement;
  statut_paiement: StatutPaiement;
  created_at: string;
  /** Présent dès qu'une livraison a été affectée (contrat §2, §4). */
  livraison: Livraison | null;
}

// --- Paiement Mobile Money (contrat §2 bis) ---

export type ModePaiement = 'a_la_livraison' | 'mobile_money';

export type StatutPaiement = 'en_attente' | 'initie' | 'regle' | 'echoue' | 'expire';

/**
 * Paiement Mobile Money d'une commande : l'app initie et suit, mais le
 * paiement lui-même se fait chez l'opérateur (push USSD/lien) - jamais de
 * saisie de données de carte ou d'identifiants ici. `regle` arrive via un
 * webhook opérateur, hors app ; l'app poll `GET .../paiement`.
 */
export interface Paiement {
  reference: string;
  statut: StatutPaiement;
  montant: number;
  devise: string;
}

/**
 * Vue dépôt d'une commande : mêmes champs, avec le site du foyer demandeur
 * embarqué (le dépôt a besoin de l'adresse pour préparer/affecter, contrat §4).
 */
export interface CommandeDepot extends Commande {
  site: Pick<Site, 'uuid' | 'nom' | 'adresse'>;
}

export interface CorpsCreationCommande {
  site_uuid: string;
  format_id: number;
  quantite: number;
  depot_uuid: string;
}

export interface CorpsReponseCommande {
  accepte: boolean;
}

// --- Livraisons et livreurs (contrat 10 §5) ---

export type StatutLivraison = 'affectee' | 'en_route' | 'livree' | 'vide_recupere';

export interface Livraison {
  id: number;
  commande_uuid: string;
  livreur_user_id: string | null;
  livreur_nom: string | null;
  statut: StatutLivraison;
  vides_recuperes: number | null;
  created_at: string;
}

export interface CorpsAffectationLivraison {
  livreur_user_id?: string;
}

export interface CorpsMajStatutLivraison {
  statut: Exclude<StatutLivraison, 'affectee'>;
  vides_recuperes?: number;
}

/** Membre `livreur` d'un dépôt, pour la sélection à l'affectation (contrat §4/§6). */
export interface MembreLivreur {
  user_id: string;
  nom: string;
}

/** Une ligne de la liste "missions" du livreur (contrat §5, UX §6). */
export interface MissionLivreur {
  livraison_id: number;
  commande_uuid: string;
  statut: StatutLivraison;
  format: Format;
  quantite: number;
  site: Pick<Site, 'uuid' | 'nom' | 'adresse'>;
  /** Pleines à déposer chez le foyer. */
  a_deposer: number;
  /** Vides à récupérer (estimation avant passage, confirmée par `vides_recuperes`). */
  a_recuperer: number;
  created_at: string;
}

// --- Stock dépôt (contrat 10 §4, §6) ---

export interface StockFormat {
  format: Format;
  pleines: number;
  vides: number;
  seuil_plein_bas: number;
}

export interface CorpsAjustementStock {
  pleines?: number;
  vides?: number;
  seuil_plein_bas?: number;
}

export interface CorpsCreationProposition {
  site_uuid: string;
  format_id: number;
  quantite: number;
}

// --- Déclenchement automatique (ADR 0009) : file dépôt et réappros ---

/**
 * Entrée de la file dépôt (ADR 0009 §B) : foyer de la zone de desserte dont
 * une bouteille active est en tension, présentée de façon actionnable -
 * plus de saisie d'UUID à l'aveugle côté dépôt.
 */
export interface FoyerEnTension {
  site_uuid: string;
  nom: string;
  zone: string;
  format: Format;
  distance_km: number;
}

/**
 * Réappro préparé par la plateforme pour ce dépôt (origine=depot, ADR 0009
 * §D) : le dépôt confirme/ajuste la quantité avant qu'il n'apparaisse comme
 * réappro ferme au mandataire.
 */
export interface Reappro {
  uuid: string;
  format: Format;
  quantite: number;
  statut: StatutCommande;
  created_at: string;
}

export interface CorpsConfirmationReappro {
  quantite?: number;
}

/**
 * Entrée de la file actionnable du livreur habituel (ADR 0008, précision
 * « maillon C » ; contrat 10 §5, `GET /api/livreur/foyers-en-tension`) :
 * l'identité nécessaire pour proposer une livraison, et rien de plus - la
 * notification de seuil bas, elle, reste minimale et sans identifiant
 * (`Notification.contexte`).
 */
export interface FoyerEnTensionLivreur {
  site_uuid: string;
  nom: string;
  zone: string;
  format: Format;
}

/**
 * Proposition envoyée par un livreur habituel pour un de ses foyers
 * habituels en tension (ADR 0009 §C). `site_uuid` vient TOUJOURS de la file
 * actionnable (`GET /api/livreur/foyers-en-tension`), jamais de la
 * notification de seuil bas (qui ne le contient pas, ADR 0008). Le format
 * n'est pas saisi côté client : dérivé par le serveur de la bouteille en
 * tension du site.
 */
export interface CorpsPropositionLivreur {
  site_uuid: string;
  depot_uuid?: string;
  quantite?: number;
}

// --- Mandataire (doc 11 §1) ---

/** Vue consolidée d'un dépôt du mandataire : stock par format + tensions. */
export interface DepotConsolide {
  uuid: string;
  nom: string;
  stocks: StockFormat[];
  derniere_activite_at: string | null;
}

export type StatutTournee = 'proposee' | 'validee' | 'en_cours' | 'terminee';

/**
 * Avancement d'un arrêt de tournée pour un format donné (UX §4 : arrivé /
 * déposé / vides récupérés). `a_faire` est l'état initial d'une ligne
 * validée, avant le passage du livreur/mandataire sur le terrain.
 */
export type StatutLigneTournee = 'a_faire' | 'arrive' | 'depose' | 'vides_recuperes';

/** Dépôt embarqué dans une ligne de tournée (même principe que `CommandeDepot.site`). */
export interface DepotTournee {
  uuid: string;
  nom: string;
}

export interface TourneeLigne {
  id: number;
  depot: DepotTournee;
  format: Format;
  pleines: number;
  vides_a_recuperer: number;
  statut: StatutLigneTournee;
}

export interface Tournee {
  uuid: string;
  date: string;
  statut: StatutTournee;
  livreur_user_id: string | null;
  livreur_nom: string | null;
  lignes: TourneeLigne[];
  created_at: string;
}

export interface CorpsLigneTournee {
  depot_uuid: string;
  format_id: number;
  pleines: number;
  vides_a_recuperer: number;
}

export interface CorpsCreationTournee {
  date: string;
  livreur_user_id?: string;
  lignes: CorpsLigneTournee[];
}

/** Ajustement d'une ligne existante (identifiée par son `id`) dans un PATCH `/tournees/{uuid}`. */
export interface CorpsAjustementLigneTournee {
  id: number;
  statut?: StatutLigneTournee;
  pleines?: number;
  vides_a_recuperer?: number;
}

export interface CorpsAjustementTournee {
  statut?: StatutTournee;
  livreur_user_id?: string;
  lignes?: CorpsAjustementLigneTournee[];
}
