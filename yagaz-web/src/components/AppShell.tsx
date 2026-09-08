import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import type { Espace } from '../auth/AuthContext'
import './AppShell.css'

interface NavItem {
  to: string
  label: string
}

const NAV_ITEMS: Record<Espace, NavItem[]> = {
  mandataire: [
    { to: '/mandataire', label: "Vue d'ensemble" },
    { to: '/mandataire/depots', label: 'Dépôts' },
    { to: '/mandataire/tournees', label: 'Tournées' },
    { to: '/mandataire/reappros', label: 'Réappros' },
  ],
  distributeur: [
    { to: '/distributeur', label: 'Demande régionale' },
    { to: '/distributeur/zones', label: 'Tensions par zone' },
    { to: '/distributeur/volumes', label: 'Volumes' },
  ],
}

const ESPACE_LABEL: Record<Espace, string> = {
  mandataire: 'Mandataire',
  distributeur: 'Distributeur',
}

export function AppShell() {
  const { user, espace, setEspace, espacesDisponibles, organisationCourante, logout } = useAuth()
  const navigate = useNavigate()

  function handleEspaceChange(next: Espace) {
    if (next === espace) return
    setEspace(next)
    navigate(`/${next}`)
  }

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  const initiale = user?.nom.trim().charAt(0).toUpperCase() ?? '?'

  return (
    <div className="app-shell">
      <aside className="app-shell__sidebar">
        <div className="app-shell__logo">
          <span className="app-shell__logo-mark">Y</span>
          <span className="app-shell__logo-text">Yagaz</span>
        </div>

        {espacesDisponibles.length > 1 ? (
          <div className="app-shell__espace-switch" role="tablist" aria-label="Espace de pilotage">
            {espacesDisponibles.map((item) => (
              <button
                key={item}
                type="button"
                role="tab"
                aria-selected={item === espace}
                className={
                  item === espace
                    ? 'app-shell__espace-btn app-shell__espace-btn--active'
                    : 'app-shell__espace-btn'
                }
                onClick={() => handleEspaceChange(item)}
              >
                {ESPACE_LABEL[item]}
              </button>
            ))}
          </div>
        ) : null}

        <nav className="app-shell__nav">
          <ul>
            {NAV_ITEMS[espace].map((item) => (
              <li key={item.to}>
                <NavLink
                  to={item.to}
                  end={item.to === '/mandataire' || item.to === '/distributeur'}
                  className={({ isActive }) =>
                    isActive
                      ? 'app-shell__nav-link app-shell__nav-link--active'
                      : 'app-shell__nav-link'
                  }
                >
                  {item.label}
                </NavLink>
              </li>
            ))}
          </ul>
          <ul className="app-shell__nav app-shell__nav--secondaire">
            <li>
              <NavLink
                to="/parametres"
                className={({ isActive }) =>
                  isActive
                    ? 'app-shell__nav-link app-shell__nav-link--active'
                    : 'app-shell__nav-link'
                }
              >
                Paramètres
              </NavLink>
            </li>
          </ul>
        </nav>

        <div className="app-shell__sidebar-footer">
          <div className="app-shell__user">
            <div className="app-shell__user-avatar">{initiale}</div>
            <div>
              <p className="app-shell__user-name">{user?.nom ?? 'Utilisateur'}</p>
              <p className="app-shell__user-role">{organisationCourante?.nom ?? ESPACE_LABEL[espace]}</p>
            </div>
          </div>
          <button type="button" className="app-shell__logout" onClick={handleLogout}>
            Déconnexion
          </button>
        </div>
      </aside>

      <div className="app-shell__body">
        <header className="app-shell__topbar">
          <div className="app-shell__search">
            <input type="search" placeholder="Rechercher un dépôt, une tournée..." />
          </div>
        </header>

        <main className="app-shell__content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
