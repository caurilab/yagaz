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
} from './types'

export async function getDepotsConsolides(orgUuid: string): Promise<DepotConsolide[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_DEPOTS_CONSOLIDES)
  return unwrap(api.get(`/api/mandataires/${orgUuid}/depots`))
}

export async function getReappros(orgUuid: string): Promise<Reappro[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_REAPPROS)
  return unwrap(api.get(`/api/mandataires/${orgUuid}/reappros`))
}

export async function getTournees(orgUuid: string): Promise<Tournee[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_TOURNEES)
  return unwrap(api.get(`/api/mandataires/${orgUuid}/tournees`))
}

export async function getLivreurs(orgUuid: string): Promise<Livreur[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_LIVREURS)
  return unwrap(api.get(`/api/depots/${orgUuid}/livreurs`))
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
    const livreur = DEMO_LIVREURS.find((l) => l.user_id === payload.livreur_user_id) ?? null
    return withDemoDelay(
      {
        uuid: `tournee-demo-${Date.now()}`,
        date: payload.date,
        statut: 'validee',
        livreur_user_id: livreur?.user_id ?? null,
        livreur_nom: livreur?.nom ?? null,
        lignes: payload.lignes.map(ligneAvecLibelles),
      },
      300,
    )
  }
  return unwrap(api.post(`/api/mandataires/${orgUuid}/tournees`, payload))
}

export async function ajusterTournee(uuid: string, payload: AjusterTourneePayload): Promise<Tournee> {
  if (DEMO_MODE) {
    const existante = DEMO_TOURNEES.find((t) => t.uuid === uuid)
    const livreur = DEMO_LIVREURS.find((l) => l.user_id === payload.livreur_user_id)
    return withDemoDelay(
      {
        uuid,
        date: existante?.date ?? new Date().toISOString().slice(0, 10),
        statut: payload.statut ?? existante?.statut ?? 'validee',
        livreur_user_id: payload.livreur_user_id ?? existante?.livreur_user_id ?? null,
        livreur_nom: livreur?.nom ?? existante?.livreur_nom ?? null,
        lignes: payload.lignes ? payload.lignes.map(ligneAvecLibelles) : (existante?.lignes ?? []),
      },
      300,
    )
  }
  return unwrap(api.patch(`/api/tournees/${uuid}`, payload))
}
