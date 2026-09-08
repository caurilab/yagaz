// Endpoints distributeur (docs/11 §2) : demande régionale agrégée, tensions par
// zone, volumes. Jamais de donnée individuelle de foyer - agrégats par zone.
import { api } from '../lib/api'
import { DEMO_MODE, withDemoDelay } from './demo'
import { DEMO_ZONES_TENSION, getDemandeFixture, getVolumesFixture } from './fixtures'
import { unwrap } from './http'
import type { DemandePoint, Granularite, VolumePoint, ZoneTension } from './types'

export interface DemandeParams {
  depuis?: string
  jusqua?: string
  pas: Granularite
}

export async function getDemande(orgUuid: string, params: DemandeParams): Promise<DemandePoint[]> {
  if (DEMO_MODE) return withDemoDelay(getDemandeFixture(params.pas))
  return unwrap(api.get(`/api/distributeurs/${orgUuid}/demande`, { params }))
}

export async function getZonesTension(orgUuid: string): Promise<ZoneTension[]> {
  if (DEMO_MODE) return withDemoDelay(DEMO_ZONES_TENSION)
  return unwrap(api.get(`/api/distributeurs/${orgUuid}/zones`))
}

export interface VolumesParams {
  depuis?: string
  jusqua?: string
  pas: Granularite
}

export async function getVolumes(orgUuid: string, params: VolumesParams): Promise<VolumePoint[]> {
  if (DEMO_MODE) return withDemoDelay(getVolumesFixture(params.pas))
  return unwrap(api.get(`/api/distributeurs/${orgUuid}/volumes`, { params }))
}
