// Types reflétant les shapes du contrat d'API (docs/09, docs/10, docs/11).

export interface Format {
  id: number
  code: string
  marque: string
  tare_nominale_g: number
  contenance_gaz_g: number
}

export type OrganisationType = 'depot' | 'mandataire' | 'distributeur'

export interface OrganisationRef {
  uuid: string
  nom: string
  type: OrganisationType
  zone?: string | null
}

export type RoleMetier = 'gerant_depot' | 'mandataire' | 'distributeur' | 'livreur'

export interface RoleMembership {
  role: RoleMetier
  organisation: OrganisationRef
}

export interface User {
  uuid: string
  nom: string
  telephone: string
  email?: string | null
  langue: string
  roles: RoleMembership[]
}

export interface AuthResponse {
  token: string
  user: User
}

// ---- Mandataire : vue consolidée des dépôts ----

export interface DepotStockLigne {
  format_id: number
  format_code: string
  format_marque: string
  pleines: number
  vides: number
  seuil_plein_bas: number
  tension: boolean
}

export interface DepotConsolide {
  uuid: string
  nom: string
  zone: string | null
  en_tension: boolean
  vides_a_recuperer: number
  derniere_activite_at: string | null
  stocks: DepotStockLigne[]
}

// ---- Commande / réappro ----

export type StatutCommande =
  | 'proposee'
  | 'confirmee'
  | 'preparee'
  | 'en_livraison'
  | 'livree'
  | 'annulee'

export interface ReapproDepot {
  uuid: string
  nom: string
  zone: string | null
}

export interface Reappro {
  uuid: string
  depot: ReapproDepot
  format: Format
  quantite: number
  statut: StatutCommande
  created_at: string
}

// ---- Tournées ----

export type StatutTournee = 'proposee' | 'validee' | 'en_cours' | 'terminee'

export interface TourneeLigne {
  depot_uuid: string
  depot_nom: string
  format_id: number
  format_code: string
  pleines: number
  vides_a_recuperer: number
}

export interface Tournee {
  uuid: string
  date: string
  statut: StatutTournee
  livreur_user_id: number | null
  livreur_nom: string | null
  lignes: TourneeLigne[]
}

export interface CreerTourneeLignePayload {
  depot_uuid: string
  format_id: number
  pleines: number
  vides_a_recuperer: number
}

export interface CreerTourneePayload {
  date: string
  livreur_user_id?: number
  lignes: CreerTourneeLignePayload[]
}

export interface AjusterTourneePayload {
  statut?: StatutTournee
  livreur_user_id?: number
  lignes?: CreerTourneeLignePayload[]
}

export interface Livreur {
  user_id: number
  nom: string
}

// ---- Distributeur : agrégats régionaux (jamais de donnée foyer) ----

export type Granularite = 'jour' | 'semaine' | 'mois'

export interface DemandePoint {
  periode: string
  zone: string
  format_code: string
  volume: number
}

export type NiveauTension = 'critique' | 'eleve' | 'modere' | 'faible'

export interface ZoneTension {
  zone: string
  depots_en_rupture: number
  depots_total: number
  vides_accumules: number
  niveau: NiveauTension
}

export interface VolumePoint {
  periode: string
  zone: string
  volume: number
}
