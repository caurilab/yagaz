import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { getTournees } from '../../api/mandataire'
import { formatDate, formatNombre } from '../../lib/format'
import type { StatutTournee } from '../../api/types'
import './Tournees.css'

const STATUT_LABEL: Record<StatutTournee, string> = {
  proposee: 'Proposée',
  validee: 'Validée',
  en_cours: 'En cours',
  terminee: 'Terminée',
}

export function MandataireTournees() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''

  const { data, isLoading } = useQuery({
    queryKey: ['mandataire', orgUuid, 'tournees'],
    queryFn: () => getTournees(orgUuid),
    enabled: orgUuid !== '',
  })

  const tournees = data ?? []
  const proposees = tournees.filter((t) => t.statut === 'proposee')

  function totalPleines(lignes: (typeof tournees)[number]['lignes']) {
    return lignes.reduce((sum, l) => sum + l.pleines, 0)
  }

  function totalVides(lignes: (typeof tournees)[number]['lignes']) {
    return lignes.reduce((sum, l) => sum + l.vides_a_recuperer, 0)
  }

  return (
    <div className="mandataire-tournees">
      <header className="mandataire-tournees__header">
        <div>
          <h1>Tournées</h1>
          <p>La plateforme propose une tournée à partir des réappros ; vous validez et ajustez.</p>
        </div>
        {proposees.length > 0 ? (
          <Link to={`/mandataire/tournees/${proposees[0].uuid}`} className="mandataire-tournees__cta">
            Préparer une tournée proposée
          </Link>
        ) : null}
      </header>

      {isLoading ? (
        <p className="mandataire-tournees__vide">Chargement des tournées...</p>
      ) : tournees.length === 0 ? (
        <p className="mandataire-tournees__vide">Aucune tournée pour le moment.</p>
      ) : (
        <div className="mandataire-tournees__liste">
          {tournees.map((tournee) => (
            <Link
              key={tournee.uuid}
              to={`/mandataire/tournees/${tournee.uuid}`}
              className="mandataire-tournees__carte"
            >
              <div className="mandataire-tournees__carte-top">
                <span
                  className={`mandataire-tournees__statut mandataire-tournees__statut--${tournee.statut}`}
                >
                  {STATUT_LABEL[tournee.statut]}
                </span>
                <span className="mandataire-tournees__date">{formatDate(tournee.date)}</span>
              </div>

              <p className="mandataire-tournees__depots">
                {new Set(tournee.lignes.map((l) => l.depot_uuid)).size} dépôt(s) -{' '}
                {tournee.livreur_nom ?? 'Livreur non affecté'}
              </p>

              <div className="mandataire-tournees__chiffres">
                <div>
                  <span className="mandataire-tournees__chiffre">{formatNombre(totalPleines(tournee.lignes))}</span>
                  <span className="mandataire-tournees__chiffre-label">Pleines à déposer</span>
                </div>
                <div>
                  <span className="mandataire-tournees__chiffre mandataire-tournees__chiffre--vides">
                    {formatNombre(totalVides(tournee.lignes))}
                  </span>
                  <span className="mandataire-tournees__chiffre-label">Vides à récupérer</span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
