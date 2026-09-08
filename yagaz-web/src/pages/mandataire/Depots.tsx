import { Fragment } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../auth/AuthContext'
import { getDepotsConsolides } from '../../api/mandataire'
import { DEMO_FORMATS } from '../../api/fixtures'
import { formatDateHeure, formatNombre } from '../../lib/format'
import './Depots.css'

export function MandataireDepots() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''

  const { data, isLoading } = useQuery({
    queryKey: ['mandataire', orgUuid, 'depots'],
    queryFn: () => getDepotsConsolides(orgUuid),
    enabled: orgUuid !== '',
  })

  const depots = data ?? []
  const formats = DEMO_FORMATS

  return (
    <div className="mandataire-depots">
      <header className="mandataire-depots__header">
        <h1>Dépôts</h1>
        <p>Vue consolidée du stock plein/vide par format - les tensions sont mises en évidence.</p>
      </header>

      <div className="mandataire-depots__table-wrap">
        {isLoading ? (
          <p className="mandataire-depots__vide">Chargement des dépôts...</p>
        ) : (
          <table className="mandataire-depots__table">
            <thead>
              <tr>
                <th rowSpan={2} className="mandataire-depots__th-depot">
                  Dépôt
                </th>
                <th rowSpan={2}>Zone</th>
                {formats.map((format) => (
                  <th key={format.id} colSpan={2} className="mandataire-depots__th-format">
                    {format.code}
                  </th>
                ))}
                <th rowSpan={2}>Dernière activité</th>
              </tr>
              <tr>
                {formats.map((format) => (
                  <Fragment key={format.id}>
                    <th className="mandataire-depots__sub-th">Pleines</th>
                    <th className="mandataire-depots__sub-th">Vides</th>
                  </Fragment>
                ))}
              </tr>
            </thead>
            <tbody>
              {depots.map((depot) => (
                <tr key={depot.uuid} className={depot.en_tension ? 'mandataire-depots__row--tension' : ''}>
                  <td className="mandataire-depots__nom">
                    {depot.nom}
                    {depot.en_tension ? <span className="mandataire-depots__badge">Tension</span> : null}
                  </td>
                  <td>{depot.zone ?? '-'}</td>
                  {formats.map((format) => {
                    const stock = depot.stocks.find((s) => s.format_id === format.id)
                    if (!stock) {
                      return (
                        <Fragment key={format.id}>
                          <td className="mandataire-depots__num">-</td>
                          <td className="mandataire-depots__num">-</td>
                        </Fragment>
                      )
                    }
                    return (
                      <Fragment key={format.id}>
                        <td
                          className={
                            stock.tension
                              ? 'mandataire-depots__num mandataire-depots__num--tension'
                              : 'mandataire-depots__num'
                          }
                        >
                          {formatNombre(stock.pleines)}
                        </td>
                        <td className="mandataire-depots__num">{formatNombre(stock.vides)}</td>
                      </Fragment>
                    )
                  })}
                  <td className="mandataire-depots__date">
                    {depot.derniere_activite_at ? formatDateHeure(depot.derniere_activite_at) : '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
