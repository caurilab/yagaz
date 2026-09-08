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
