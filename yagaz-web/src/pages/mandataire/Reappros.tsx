import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { getReappros } from '../../api/mandataire'
import { formatDate } from '../../lib/format'
import type { StatutCommande } from '../../api/types'
import './Reappros.css'

const STATUT_LABEL: Record<StatutCommande, string> = {
  proposee: 'Proposée',
  confirmee: 'Confirmée',
  preparee: 'Préparée',
  en_livraison: 'En livraison',
  livree: 'Livrée',
  annulee: 'Annulée',
}

export function MandataireReappros() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''

  const { data, isLoading } = useQuery({
    queryKey: ['mandataire', orgUuid, 'reappros'],
    queryFn: () => getReappros(orgUuid),
    enabled: orgUuid !== '',
  })

  const reappros = data ?? []

  return (
    <div className="reappros">
      <header className="reappros__header">
        <div>
          <h1>Réappros</h1>
          <p>
            Demandes de réapprovisionnement préparées automatiquement à partir de l'état des stocks
            des dépôts.
          </p>
        </div>
        {reappros.length > 0 ? (
          <Link to="/mandataire/tournees/nouvelle" className="reappros__cta">
            Préparer une tournée
          </Link>
        ) : null}
      </header>

      <div className="reappros__table-wrap">
        {isLoading ? (
          <p className="reappros__vide">Chargement...</p>
        ) : reappros.length === 0 ? (
          <p className="reappros__vide">Aucune demande de réappro en attente.</p>
        ) : (
          <table className="reappros__table">
            <thead>
              <tr>
                <th>Dépôt</th>
                <th>Zone</th>
                <th>Format</th>
                <th>Quantité demandée</th>
                <th>Statut</th>
                <th>Créée le</th>
              </tr>
            </thead>
            <tbody>
              {reappros.map((reappro) => (
                <tr key={reappro.uuid}>
                  <td className="reappros__nom">{reappro.depot.nom}</td>
                  <td>{reappro.depot.zone ?? '-'}</td>
                  <td>{reappro.format_code}</td>
                  <td className="reappros__quantite">{reappro.quantite}</td>
                  <td>
                    <span className={`reappros__statut reappros__statut--${reappro.statut}`}>
                      {STATUT_LABEL[reappro.statut]}
                    </span>
                  </td>
                  <td className="reappros__date">{formatDate(reappro.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
