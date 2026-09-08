import { Navigate, createBrowserRouter } from 'react-router-dom'
import { AppShell } from './components/AppShell'
import { RequireAuth } from './auth/RequireAuth'
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
      { index: true, element: <Navigate to="/mandataire" replace /> },
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
