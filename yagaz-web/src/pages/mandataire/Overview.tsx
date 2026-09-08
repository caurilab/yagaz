import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { KpiCard } from '../../components/KpiCard'
import { useAuth } from '../../auth/AuthContext'
import { getDepotsConsolides, getTournees } from '../../api/mandataire'
import { formatNombre } from '../../lib/format'
import './Overview.css'

const AUJOURDHUI = '2026-09-08'

export function MandataireOverview() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''

  const depotsQuery = useQuery({
    queryKey: ['mandataire', orgUuid, 'depots'],
    queryFn: () => getDepotsConsolides(orgUuid),
    enabled: orgUuid !== '',
  })

  const tourneesQuery = useQuery({
    queryKey: ['mandataire', orgUuid, 'tournees'],
    queryFn: () => getTournees(orgUuid),
    enabled: orgUuid !== '',
  })

  const depots = depotsQuery.data ?? []
  const tournees = tourneesQuery.data ?? []

  const depotsEnTension = depots.filter((d) => d.en_tension)
  const videsARecuperer = depots.reduce((total, d) => total + d.vides_a_recuperer, 0)
  const tourneesDuJour = tournees.filter((t) => t.date === AUJOURDHUI)
  const tourneesTerminees = tournees.filter((t) => t.statut === 'terminee')
  const tauxATemps =
    tournees.length > 0 ? Math.round((tourneesTerminees.length / tournees.length) * 100) : 0

  const isLoading = depotsQuery.isLoading || tourneesQuery.isLoading

  return (
    <div className="overview">
      <header className="overview__header">
        <h1>Tableau de bord - Mandataire</h1>
        <p>Vue consolidée des dépôts et des tournées en cours.</p>
      </header>

      <section className="overview__kpis">
        <KpiCard
          label="Dépôts en tension"
          value={isLoading ? '-' : depotsEnTension.length}
          suffix={isLoading ? undefined : `/ ${depots.length}`}
          tone="accent"
        />
        <KpiCard
          label="Vides à récupérer"
          value={isLoading ? '-' : formatNombre(videsARecuperer)}
          trend={`Sur ${new Set(depots.map((d) => d.zone)).size || 0} zones`}
        />
        <KpiCard
          label="Tournées du jour"
          value={isLoading ? '-' : tourneesDuJour.length}
          trend={`${tournees.filter((t) => t.statut === 'en_cours').length} en cours`}
        />
        <KpiCard
          label="Livraisons à temps"
          value={isLoading ? '-' : tauxATemps}
          suffix={isLoading ? undefined : '%'}
          tone="success"
        />
      </section>

      <section className="overview__panel">
        <div className="overview__panel-header">
          <h2>Dépôts en tension</h2>
          <Link className="overview__panel-link" to="/mandataire/depots">
            Voir tous les dépôts
          </Link>
        </div>

        {isLoading ? (
          <p className="overview__vide">Chargement...</p>
        ) : depotsEnTension.length === 0 ? (
          <p className="overview__vide">Aucun dépôt en tension actuellement.</p>
        ) : (
          <ul className="overview__list">
            {depotsEnTension.slice(0, 6).map((depot) => (
              <li key={depot.uuid} className="overview__list-item">
                <span className="overview__list-name">{depot.nom}</span>
                <span className="overview__list-niveau">{depot.zone}</span>
                <span className="overview__list-valeur">{depot.vides_a_recuperer} vides</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
