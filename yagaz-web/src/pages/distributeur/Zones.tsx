import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../auth/AuthContext'
import { getZonesTension } from '../../api/distributeur'
import { KpiCard } from '../../components/KpiCard'
import { formatNombre } from '../../lib/format'
import type { NiveauTension } from '../../api/types'
import './Zones.css'

const NIVEAU_LABEL: Record<NiveauTension, string> = {
  critique: 'Critique',
  eleve: 'Élevé',
  modere: 'Modéré',
  faible: 'Faible',
}

const NIVEAU_ORDRE: Record<NiveauTension, number> = { critique: 0, eleve: 1, modere: 2, faible: 3 }

export function DistributeurZones() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''

  const { data, isLoading } = useQuery({
    queryKey: ['distributeur', orgUuid, 'zones'],
    queryFn: () => getZonesTension(orgUuid),
    enabled: orgUuid !== '',
  })

  const zones = [...(data ?? [])].sort((a, b) => NIVEAU_ORDRE[a.niveau] - NIVEAU_ORDRE[b.niveau])
  const zonesCritiques = zones.filter((z) => z.niveau === 'critique' || z.niveau === 'eleve')
  const totalRupture = zones.reduce((sum, z) => sum + z.depots_en_rupture, 0)
  const totalVides = zones.reduce((sum, z) => sum + z.vides_accumules, 0)
  const maxVides = Math.max(1, ...zones.map((z) => z.vides_accumules))

  return (
    <div className="zones">
      <header className="zones__header">
        <h1>Tensions par zone</h1>
        <p>Dépôts en rupture et vides accumulés, par zone - vue de décision pour orienter l'allocation.</p>
      </header>

      <section className="zones__kpis">
        <KpiCard
          label="Zones en tension"
          value={isLoading ? '-' : zonesCritiques.length}
          suffix={isLoading ? undefined : `/ ${zones.length}`}
          tone="accent"
        />
        <KpiCard label="Dépôts en rupture" value={isLoading ? '-' : totalRupture} />
        <KpiCard label="Vides accumulés" value={isLoading ? '-' : formatNombre(totalVides)} tone="success" />
      </section>

      <section className="zones__panel">
        <div className="zones__panel-header">
          <h2>Carte de chaleur - vides accumulés</h2>
        </div>

        {isLoading ? (
          <p className="zones__vide">Chargement...</p>
        ) : (
          <ul className="zones__liste">
            {zones.map((zone) => (
              <li key={zone.zone} className="zones__item">
                <div className="zones__item-info">
                  <span className={`zones__niveau zones__niveau--${zone.niveau}`}>{NIVEAU_LABEL[zone.niveau]}</span>
                  <span className="zones__nom">{zone.zone}</span>
                  <span className="zones__detail">
                    {zone.depots_en_rupture} / {zone.depots_total} dépôts en rupture
                  </span>
                </div>
                <div className="zones__barre-wrap">
                  <div
                    className={`zones__barre zones__barre--${zone.niveau}`}
                    style={{ width: `${Math.round((zone.vides_accumules / maxVides) * 100)}%` }}
                  />
                </div>
                <span className="zones__valeur">{formatNombre(zone.vides_accumules)} vides</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
