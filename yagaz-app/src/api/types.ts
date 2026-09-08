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

export interface MesRoles {
  foyer: boolean;
  depots: RoleDepot[];
  livreur: boolean;
}

export type EspaceType = 'foyer' | 'depot' | 'livreur';

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
  created_at: string;
  /** Présent dès qu'une livraison a été affectée (contrat §2, §4). */
  livraison: Livraison | null;
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
