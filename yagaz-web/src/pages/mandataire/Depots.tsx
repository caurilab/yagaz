import { Fragment, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../../auth/AuthContext'
import { creerDepot, getDepotsConsolides } from '../../api/mandataire'
import { DEMO_FORMATS } from '../../api/fixtures'
import { formatDateHeure, formatNombre } from '../../lib/format'
import './Depots.css'

export function MandataireDepots() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['mandataire', orgUuid, 'depots'],
    queryFn: () => getDepotsConsolides(orgUuid),
    enabled: orgUuid !== '',
  })

  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [nom, setNom] = useState('')
  const [zone, setZone] = useState('')

  const creation = useMutation({
    mutationFn: () => creerDepot(orgUuid, { nom: nom.trim(), zone: zone.trim() || null }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['mandataire', orgUuid, 'depots'] })
      setNom('')
      setZone('')
      setFormulaireOuvert(false)
    },
  })

  const depots = data ?? []
  const formats = DEMO_FORMATS

  return (
    <div className="mandataire-depots">
      <header className="mandataire-depots__header">
        <div className="mandataire-depots__header-texte">
          <h1>Dépôts</h1>
          <p>Vue consolidée du stock plein/vide par format - les tensions sont mises en évidence.</p>
        </div>
        <button
          type="button"
          className="mandataire-depots__ajouter"
          onClick={() => setFormulaireOuvert((ouvert) => !ouvert)}
        >
          {formulaireOuvert ? 'Fermer' : '+ Ajouter un dépôt'}
        </button>
      </header>

      {formulaireOuvert ? (
        <form
          className="mandataire-depots__form"
          onSubmit={(e) => {
            e.preventDefault()
            if (nom.trim() !== '' && !creation.isPending) creation.mutate()
          }}
        >
          <div className="mandataire-depots__form-champs">
            <label className="mandataire-depots__field">
              <span>Nom du dépôt</span>
              <input
                type="text"
                value={nom}
                onChange={(e) => setNom(e.target.value)}
                placeholder="Ex. Dépôt Yopougon"
                autoFocus
                required
              />
            </label>
            <label className="mandataire-depots__field">
              <span>Zone (optionnel)</span>
              <input
                type="text"
                value={zone}
                onChange={(e) => setZone(e.target.value)}
                placeholder="Ex. Yopougon"
              />
            </label>
          </div>
          <div className="mandataire-depots__form-actions">
            <button type="submit" className="mandataire-depots__valider" disabled={nom.trim() === '' || creation.isPending}>
              {creation.isPending ? 'Création...' : 'Créer le dépôt'}
            </button>
            {creation.isError ? (
              <span className="mandataire-depots__erreur">La création a échoué. Réessayez.</span>
            ) : null}
          </div>
        </form>
      ) : null}

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
