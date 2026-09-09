import { Navigate, createBrowserRouter } from 'react-router-dom'
import { AppShell } from './components/AppShell'
import { RequireAuth } from './auth/RequireAuth'
import { useAuth } from './auth/AuthContext'
import { Login } from './pages/Login'
import { Parametres } from './pages/Parametres'
import { MandataireOverview } from './pages/mandataire/Overview'
import { MandataireDepots } from './pages/mandataire/Depots'
import { MandataireTournees } from './pages/mandataire/Tournees'
import { MandataireTourneeDetail } from './pages/mandataire/TourneeDetail'
import { MandataireReappros } from './pages/mandataire/Reappros'
import { DistributeurDemande } from './pages/distributeur/Demande'
import { DistributeurZones } from './pages/distributeur/Zones'
import { DistributeurVolumes } from './pages/distributeur/Volumes'

/**
 * Redirige la racine vers l'espace du compte : un distributeur va sur
 * /distributeur, un mandataire sur /mandataire. `espacesDisponibles` est
 * dérivé de `user.roles` (synchrone), donc la cible est correcte dès le
 * premier rendu, sans dépendre de l'espace par défaut.
 */
function AccueilEspace() {
  const { espace, espacesDisponibles } = useAuth()
  const cible = espacesDisponibles.includes(espace) ? espace : (espacesDisponibles[0] ?? espace)
  return <Navigate to={`/${cible}`} replace />
}

export const router = createBrowserRouter([
  { path: '/login', element: <Login /> },
  {
    path: '/',
    element: (
      <RequireAuth>
        <AppShell />
      </RequireAuth>
    ),
    children: [
      { index: true, element: <AccueilEspace /> },
      { path: 'mandataire', element: <MandataireOverview /> },
      { path: 'mandataire/depots', element: <MandataireDepots /> },
      { path: 'mandataire/tournees', element: <MandataireTournees /> },
      { path: 'mandataire/tournees/:uuid', element: <MandataireTourneeDetail /> },
      { path: 'mandataire/reappros', element: <MandataireReappros /> },
      { path: 'distributeur', element: <DistributeurDemande /> },
      { path: 'distributeur/zones', element: <DistributeurZones /> },
      { path: 'distributeur/volumes', element: <DistributeurVolumes /> },
      { path: 'parametres', element: <Parametres /> },
    ],
  },
])
