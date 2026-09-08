import { KpiCard } from '../components/KpiCard'
import './Overview.css'

// Données en dur (placeholders) — à remplacer par un appel API réel
// via react-query une fois les endpoints du backend disponibles.
const KPIS = [
  {
    label: 'Dépôts en tension',
    value: 7,
    suffix: '/ 42',
    trend: '+2 depuis hier',
    tone: 'accent' as const,
  },
  {
    label: 'Vides à récupérer',
    value: 128,
    trend: 'Sur 6 zones',
    tone: 'default' as const,
  },
  {
    label: 'Tournées du jour',
    value: 14,
    trend: '3 en cours',
    tone: 'default' as const,
  },
  {
    label: 'Livraisons à temps',
    value: 96,
    suffix: '%',
    trend: '+1,4 pt vs semaine dernière',
    tone: 'success' as const,
  },
]

// Placeholder de dépôts en tension pour illustrer le style de liste.
const DEPOTS_EN_TENSION = [
  { nom: 'Dépôt Nord — Casablanca', niveau: 'Critique', valeur: '92%' },
  { nom: 'Dépôt Sud — Marrakech', niveau: 'Élevé', valeur: '81%' },
  { nom: 'Dépôt Est — Fès', niveau: 'Élevé', valeur: '77%' },
  { nom: 'Dépôt Centre — Rabat', niveau: 'Modéré', valeur: '64%' },
]

export function Overview() {
  return (
    <div className="overview">
      <header className="overview__header">
        <h1>Tableau de bord — Mandataire</h1>
        <p>Vue consolidée des dépôts et des tournées en cours.</p>
      </header>

      <section className="overview__kpis">
        {KPIS.map((kpi) => (
          <KpiCard
            key={kpi.label}
            label={kpi.label}
            value={kpi.value}
            suffix={kpi.suffix}
            trend={kpi.trend}
            tone={kpi.tone}
          />
        ))}
      </section>

      <section className="overview__panel">
        <div className="overview__panel-header">
          <h2>Dépôts en tension</h2>
          <span className="overview__panel-badge">Placeholder</span>
        </div>
        <ul className="overview__list">
          {DEPOTS_EN_TENSION.map((depot) => (
            <li key={depot.nom} className="overview__list-item">
              <span className="overview__list-name">{depot.nom}</span>
              <span className="overview__list-niveau">{depot.niveau}</span>
              <span className="overview__list-valeur">{depot.valeur}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}
