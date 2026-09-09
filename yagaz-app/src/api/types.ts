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
  /**
   * Capacités dérivées des équipements actifs du site (ADR 0012 - multi-
   * équipements) : l'app grise le niveau de gaz / la température / l'écran
   * tant que la capacité correspondante est fausse (aucun équipement `actif`
   * de ce type affecté au site).
   */
  a_balance: boolean;
  a_temperature: boolean;
  a_ecran: boolean;
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
  /** Couleur hex de la marque (repli `#6B7280` côté client si absente). */
  couleur?: string;
}

// --- Marques (référentiel) ---

export interface Marque {
  id: number;
  nom: string;
  couleur: string;
}

// --- Pièces amovibles de bouteille (référentiel - tare ajustable) ---

export interface PieceBouteille {
  cle: string;
  libelle: string;
  delta_g: number;
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
  /** Clés des pièces amovibles cochées comme manquantes (tare ajustable). */
  pieces_manquantes?: string[];
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
  /** Tare ajustable : clés des pièces amovibles cochées comme manquantes. */
  pieces_manquantes?: string[];
}

export interface CorpsMajBouteille {
  format_id?: number;
  role_bouteille?: RoleBouteille;
  seuil_bas_pct?: number;
  tare_g?: number;
  tare_source?: SourceTare;
  /** Tare ajustable : clés des pièces amovibles cochées comme manquantes. */
  pieces_manquantes?: string[];
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
  /** Format (taille + marque) que ce dépôt fournira : à cibler pour la commande. */
  format_id: number;
  /** Marque réellement fournie par ce dépôt pour cette taille. */
  marque?: string | null;
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
  distributeurs?: RoleMandataire[];
}

export type EspaceType = 'foyer' | 'depot' | 'livreur' | 'mandataire' | 'distributeur';

// --- Commandes (contrat 10 §2-3) ---

export type StatutCommande =
  | 'proposee'
  | 'confirmee'
  | 'preparee'
  | 'en_livraison'
  | 'livree'
  | 'annulee';

/**
 * Type simple, sans logique de prix en v1 (décision produit) : `echange` =
 * on récupère la bouteille vide et on la remplace par une pleine ; `achat` =
 * livraison d'une bouteille neuve, sans reprise.
 */
export type TypeCommande = 'echange' | 'achat';

export interface Commande {
  uuid: string;
  site_uuid: string;
  format: Format;
  quantite: number;
  depot_uuid: string;
  type: TypeCommande;
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
  /** Optionnel : défaut `echange` côté API. */
  type?: TypeCommande;
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
 * Vue mandataire d'un réappro (`GET /mandataires/{uuid}/reappros`) : le
 * pendant de `Reappro` côté dépôt, avec le dépôt embarqué puisqu'un
 * mandataire suit plusieurs dépôts (doc 11 §1).
 */
export interface ReapproMandataire {
  uuid: string;
  depot: { uuid: string; nom: string; zone: string | null };
  format: Format;
  quantite: number;
  statut: StatutCommande;
  created_at: string;
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

/** Ligne de stock à plat de la vue consolidée mandataire (`GET /mandataires/{uuid}/depots`). */
export interface DepotStockConsolide {
  format_id: number;
  format_code: string;
  format_marque: string;
  pleines: number;
  vides: number;
  seuil_plein_bas: number;
  tension: boolean;
}

/** Vue consolidée d'un dépôt du mandataire : stock par format + tensions. */
export interface DepotConsolide {
  uuid: string;
  nom: string;
  zone: string | null;
  en_tension: boolean;
  vides_a_recuperer: number;
  derniere_activite_at: string | null;
  stocks: DepotStockConsolide[];
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

// --- Historique unifié (foyer, doc 13 §1) ---

/**
 * Slug d'icône plate de l'événement - c'est aussi la valeur utilisée par le
 * filtre `type` de `GET /api/historique` côté client (le contrat ne détaille
 * pas les valeurs possibles de `type` au-delà de ce slug).
 */
export type IconeEvenementHistorique = 'commande' | 'paiement' | 'alerte' | 'cuisson';

export interface EvenementHistorique {
  type: string;
  date: string;
  titre: string;
  detail: string;
  montant?: number;
  statut?: string;
  icone: IconeEvenementHistorique;
}

export interface Pagination {
  page: number;
  par_page: number;
  total: number;
  total_pages: number;
}

export interface CorpsHistorique {
  site_uuid?: string;
  depuis?: string;
  type?: IconeEvenementHistorique;
  page?: number;
  par_page?: number;
}

// --- Analyses (tableau de bord foyer, doc 13 §2) ---

export type PeriodeAnalyse = 'mois' | 'semaine' | 'annee';

export interface RepartitionEntree {
  libelle: string;
  valeur: number;
  couleur: string;
}

export interface RepartitionAnalyse {
  par_bouteille: RepartitionEntree[];
  par_site: RepartitionEntree[];
}

export interface RechargesAnalyse {
  nombre: number;
  cout_moyen_fcfa: number;
  frequence_jours: number;
}

export interface JourCuissonSerie {
  date: string;
  sessions: number;
  duree_min: number;
}

export interface JoursCuisineAnalyse {
  nombre: number;
  serie_journaliere: JourCuissonSerie[];
}

export interface PointSerieConsommation {
  /** Libellé du seau temporel (jour `YYYY-MM-DD` ou mois `YYYY-MM`). */
  periode: string;
  consommation_kg: number;
}

export interface Analyse {
  consommation_kg: number;
  consommation_tendance_pct: number;
  depense_fcfa: number;
  depense_tendance_pct: number;
  recharges: RechargesAnalyse;
  repartition: RepartitionAnalyse;
  jours_cuisine: JoursCuisineAnalyse;
  autonomie_moyenne_h: number;
  projection_prochaine_recharge_jours: number;
  serie_consommation: PointSerieConsommation[];
}

export interface CorpsAnalyse {
  site_uuid?: string;
  periode?: PeriodeAnalyse;
}

// --- Température & cuisson (ADR 0011, doc 13 §3) ---

export interface TemperatureSite {
  temp_courante_c: number;
  /** false si la dernière mesure est trop ancienne (hors ligne) - même convention que `Niveau.frais`. */
  frais: boolean;
  cuisson_en_cours: boolean;
  debut_cuisson_at: string | null;
}

// --- Analyse détaillée de température (drill-down, ADR 0011, doc 13 §3) ---

export type PeriodeTemperature = 'jour' | 'semaine' | 'mois';

/** Point de la courbe horaire : température moyenne observée pour cette heure de la journée (0-23). */
export interface PointTemperatureHoraire {
  heure: number;
  /** Température moyenne de l'heure, `null` si aucun relevé sur ce créneau. */
  temp_moyenne_c: number | null;
}

/** Point de l'histogramme des cuissons : nombre de sessions de cuisson démarrées à cette heure (0-23). */
export interface PointCuissonHoraire {
  heure: number;
  sessions: number;
}

export interface FrequenceCuisson {
  jours_cuisine: number;
  sessions_par_jour: number;
  duree_moyenne_min: number;
}

/**
 * Analyse de température (drill-down, endpoint `/sites/{uuid}/temperature/
 * analyse`). Aligné sur `AgregationTemperature` : l'état COURANT
 * (temp_courante_c, cuisson_en_cours) vient de l'endpoint séparé
 * `/sites/{uuid}/temperature` (`TemperatureSite`), pas d'ici.
 */
export interface TemperatureAnalyse {
  courbe_horaire: PointTemperatureHoraire[];
  cuisson_par_heure: PointCuissonHoraire[];
  heure_pointe_cuisson: number | null;
  /** Plage dominante : 'matin' | 'midi' | 'apres_midi' | 'soir', ou `null`. */
  periode_dominante: string | null;
  frequence: FrequenceCuisson;
}

export interface CorpsTemperatureAnalyse {
  periode?: PeriodeTemperature;
}

// --- Équipements (registre unifié foyer, ADR 0012) ---

export type TypeEquipement = 'balance' | 'temperature' | 'ecran';
export type StatutEquipement = 'a_connecter' | 'actif' | 'hors_service';

export interface Equipement {
  uuid: string;
  type: TypeEquipement;
  reference: string;
  statut: StatutEquipement;
  site: { uuid: string; nom: string } | null;
  dernier_vu_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface CorpsCreationEquipement {
  type: TypeEquipement;
  reference: string;
  site_uuid?: string;
}

export interface CorpsMajEquipement {
  /** `null` pour retirer l'affectation au site. */
  site_uuid?: string | null;
  statut?: StatutEquipement;
}

// --- Suivi de commande (contrat API, `GET /commandes/{uuid}/suivi`) ---

export type CleEtapeSuivi = 'passee' | 'confirmee' | 'preparee' | 'en_livraison' | 'livree';

export interface EtapeSuivi {
  cle: CleEtapeSuivi;
  libelle: string;
  atteinte: boolean;
  date: string | null;
  courante: boolean;
}

export interface SuiviLivraisonCommande {
  statut: StatutLivraison | null;
  livreur: string | null;
}

export interface SuiviCommande {
  etapes: EtapeSuivi[];
  statut_courant: StatutCommande;
  livraison: SuiviLivraisonCommande;
  /** Contact du dépôt cible (appel/WhatsApp) - `telephone` nullable côté `organisations`. */
  depot: { nom: string; telephone: string | null };
  /** Distance à vol d'oiseau dépôt <-> site - jamais de position GPS live. */
  distance_km: number | null;
  /** Estimation grossière (vitesse urbaine moyenne), non nulle seulement en `en_livraison`. */
  eta_minutes: number | null;
}

// --- Reçu de paiement (contrat API, `GET /commandes/{uuid}/recu`) ---

export interface RecuCommandeDetail {
  uuid: string;
  format: { code: string; marque: string } | null;
  quantite: number;
  depot: { nom: string } | null;
}

export interface RecuPaiement {
  reference: string | null;
  montant: number;
  devise: string;
  statut_paiement: StatutPaiement;
  mode_paiement: ModePaiement;
  date: string | null;
  commande: RecuCommandeDetail;
  site: { nom: string } | null;
}
