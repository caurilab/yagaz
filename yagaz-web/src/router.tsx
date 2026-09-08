import { createBrowserRouter } from 'react-router-dom'
import { AppShell } from './components/AppShell'
import { Overview } from './pages/Overview'
import { Depots } from './pages/Depots'
import { Tournees } from './pages/Tournees'
import { Analyse } from './pages/Analyse'
import { Parametres } from './pages/Parametres'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <AppShell />,
    children: [
      { index: true, element: <Overview /> },
      { path: 'depots', element: <Depots /> },
      { path: 'tournees', element: <Tournees /> },
      { path: 'analyse', element: <Analyse /> },
      { path: 'parametres', element: <Parametres /> },
    ],
  },
])
