import { useAuth } from '../auth/AuthContext'
import { DEMO_MODE } from '../api/demo'
import './Placeholder.css'

export function Parametres() {
  const { user, espacesDisponibles } = useAuth()

  return (
    <div className="placeholder-page">
      <h1>Paramètres</h1>
      <p>Préférences du compte et espaces disponibles.</p>
      <div className="placeholder-page__card placeholder-page__card--info">
        <p>
          <strong>Nom</strong> {user?.nom ?? '-'}
        </p>
        <p>
          <strong>Téléphone</strong> {user?.telephone ?? '-'}
        </p>
        <p>
          <strong>Espaces</strong>{' '}
          {espacesDisponibles.length > 0 ? espacesDisponibles.join(', ') : 'aucun'}
        </p>
        <p>
          <strong>Mode démo</strong> {DEMO_MODE ? 'activé (fixtures)' : 'désactivé (API réelle)'}
        </p>
      </div>
    </div>
  )
}
