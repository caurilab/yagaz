import { NavLink, Outlet } from 'react-router-dom'
import './AppShell.css'

interface NavItem {
  to: string
  label: string
}

const NAV_ITEMS: NavItem[] = [
  { to: '/', label: "Vue d'ensemble" },
  { to: '/depots', label: 'Dépôts' },
  { to: '/tournees', label: 'Tournées' },
  { to: '/analyse', label: 'Analyse' },
  { to: '/parametres', label: 'Paramètres' },
]

export function AppShell() {
  return (
    <div className="app-shell">
      <aside className="app-shell__sidebar">
        <div className="app-shell__logo">
          <span className="app-shell__logo-mark">Y</span>
          <span className="app-shell__logo-text">Yagaz</span>
        </div>

        <nav className="app-shell__nav">
          <ul>
            {NAV_ITEMS.map((item) => (
              <li key={item.to}>
                <NavLink
                  to={item.to}
                  end={item.to === '/'}
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
        </nav>

        <div className="app-shell__sidebar-footer">
          <div className="app-shell__user">
            <div className="app-shell__user-avatar">M</div>
            <div>
              <p className="app-shell__user-name">Mandataire</p>
              <p className="app-shell__user-role">Compte principal</p>
            </div>
          </div>
        </div>
      </aside>

      <div className="app-shell__body">
        <header className="app-shell__topbar">
          <div className="app-shell__search">
            <input type="search" placeholder="Rechercher un dépôt, une tournée…" />
          </div>
        </header>

        <main className="app-shell__content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
