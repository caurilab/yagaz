// Endpoints mandataire (docs/11 §1) : vue consolidée des dépôts, réappros, tournées.
import { api } from '../lib/api'
import { DEMO_MODE, withDemoDelay } from './demo'
import { DEMO_DEPOTS_CONSOLIDES, DEMO_LIVREURS, DEMO_REAPPROS, DEMO_TOURNEES, depotNom, formatCode } from './fixtures'
import { unwrap } from './http'
import type {
  AjusterTourneePayload,
  CreerTourneePayload,
  DepotConsolide,
  Livreur,
  Reappro,
  Tournee,
  TourneeBrute,
} from './types'

export async function getDepotsConsolides(orgUuid: string): Promise<DepotConsolide[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_DEPOTS_CONSOLIDES)
  return unwrap(api.get(`/api/mandataires/${orgUuid}/depots`))
}

export interface CreerDepotPayload {
  nom: string
  zone?: string | null
}

export async function creerDepot(orgUuid: string, payload: CreerDepotPayload): Promise<DepotConsolide> {
  if (DEMO_MODE) {
    return withDemoDelay<DepotConsolide>({
      uuid: `demo-depot-${Date.now()}`,
      nom: payload.nom,
      zone: payload.zone ?? null,
      en_tension: false,
      vides_a_recuperer: 0,
      derniere_activite_at: null,
      stocks: [],
    })
  }
  return unwrap(api.post(`/api/mandataires/${orgUuid}/depots`, payload))
}

export async function getReappros(orgUuid: string): Promise<Reappro[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_REAPPROS)
  return unwrap(api.get(`/api/mandataires/${orgUuid}/reappros`))
}

/**
 * Convertit la forme imbriquée réelle (`TourneeResource`) vers le
 * view-model plat `Tournee` consommé par les composants.
 */
function versTourneeVM(brut: TourneeBrute): Tournee {
  return {
    uuid: brut.uuid,
    date: brut.date,
    statut: brut.statut,
    livreur_uuid: brut.livreur?.uuid ?? null,
    livreur_nom: brut.livreur?.nom ?? null,
    lignes: (brut.lignes ?? []).map((ligne) => ({
      depot_uuid: ligne.depot?.uuid ?? '',
      depot_nom: ligne.depot?.nom ?? '',
      format_id: ligne.format?.id ?? 0,
      format_code: ligne.format?.code ?? '',
      pleines: ligne.pleines,
      vides_a_recuperer: ligne.vides_a_recuperer,
    })),
  }
}

export async function getTournees(orgUuid: string): Promise<Tournee[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_TOURNEES)
  const brutes = await unwrap<TourneeBrute[]>(api.get(`/api/mandataires/${orgUuid}/tournees`))
  return brutes.map(versTourneeVM)
}

export async function getLivreurs(orgUuid: string): Promise<Livreur[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_LIVREURS)
  return unwrap(api.get(`/api/mandataires/${orgUuid}/livreurs`))
}

function ligneAvecLibelles(ligne: CreerTourneePayload['lignes'][number]) {
  return {
    depot_uuid: ligne.depot_uuid,
    depot_nom: depotNom(ligne.depot_uuid),
    format_id: ligne.format_id,
    format_code: formatCode(ligne.format_id),
    pleines: ligne.pleines,
    vides_a_recuperer: ligne.vides_a_recuperer,
  }
}

/**
 * La plateforme propose, le mandataire valide/ajuste : crée/valide une tournée
 * à partir des lignes de réappro sélectionnées.
 */
export async function creerTournee(orgUuid: string, payload: CreerTourneePayload): Promise<Tournee> {
  if (DEMO_MODE) {
    const livreur = DEMO_LIVREURS.find((l) => l.uuid === payload.livreur_user_id) ?? null
    return withDemoDelay(
      {
        uuid: `tournee-demo-${Date.now()}`,
        date: payload.date,
        statut: 'validee',
        livreur_uuid: livreur?.uuid ?? null,
        livreur_nom: livreur?.nom ?? null,
        lignes: payload.lignes.map(ligneAvecLibelles),
      },
      300,
    )
  }
  const brut = await unwrap<TourneeBrute>(api.post(`/api/mandataires/${orgUuid}/tournees`, payload))
  return versTourneeVM(brut)
}

export async function ajusterTournee(uuid: string, payload: AjusterTourneePayload): Promise<Tournee> {
  if (DEMO_MODE) {
    const existante = DEMO_TOURNEES.find((t) => t.uuid === uuid)
    const livreur = DEMO_LIVREURS.find((l) => l.uuid === payload.livreur_user_id)
    return withDemoDelay(
      {
        uuid,
        date: existante?.date ?? new Date().toISOString().slice(0, 10),
        statut: payload.statut ?? existante?.statut ?? 'validee',
        livreur_uuid: payload.livreur_user_id ?? existante?.livreur_uuid ?? null,
        livreur_nom: livreur?.nom ?? existante?.livreur_nom ?? null,
        lignes: payload.lignes ? payload.lignes.map(ligneAvecLibelles) : (existante?.lignes ?? []),
      },
      300,
    )
  }
  const brut = await unwrap<TourneeBrute>(api.patch(`/api/tournees/${uuid}`, payload))
  return versTourneeVM(brut)
}
