import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { useAuth } from '../../auth/AuthContext'
import { fenetreParDefaut, getVolumes } from '../../api/distributeur'
import { formatNombre } from '../../lib/format'
import type { Granularite } from '../../api/types'
import './Volumes.css'

const GRANULARITES: Array<{ valeur: Granularite; label: string }> = [
  { valeur: 'jour', label: 'Jour' },
  { valeur: 'semaine', label: 'Semaine' },
  { valeur: 'mois', label: 'Mois' },
]

const PALETTE = ['#f5741e', '#f7a56b', '#f7d06b', '#6b7280', '#34c77b', '#4b8bef']

export function DistributeurVolumes() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''
  const [pas, setPas] = useState<Granularite>('mois')
  const { depuis, jusqua } = useMemo(() => fenetreParDefaut(), [])

  const { data, isLoading } = useQuery({
    queryKey: ['distributeur', orgUuid, 'volumes', pas, depuis, jusqua],
    queryFn: () => getVolumes(orgUuid, { depuis, jusqua, pas }),
    enabled: orgUuid !== '',
  })

  const points = useMemo(() => data ?? [], [data])

  const zones = useMemo(() => Array.from(new Set(points.map((p) => p.zone))), [points])

  const evolution = useMemo(() => {
    const parPeriode = new Map<string, Record<string, number | string>>()
    for (const point of points) {
      const existant = parPeriode.get(point.periode) ?? { periode: point.periode }
      existant[point.zone] = point.volume
      parPeriode.set(point.periode, existant)
    }
    return Array.from(parPeriode.values())
  }, [points])

  const comparaison = useMemo(() => {
    const totaux = new Map<string, number>()
    for (const point of points) {
      totaux.set(point.zone, (totaux.get(point.zone) ?? 0) + point.volume)
    }
    return Array.from(totaux.entries())
      .map(([zone, volume]) => ({ zone, volume }))
      .sort((a, b) => b.volume - a.volume)
  }, [points])

  const dernierePeriode = evolution.at(-1)
  const periodePrecedente = evolution.at(-2)
  const totalDernierePeriode = dernierePeriode
    ? zones.reduce((sum, zone) => sum + ((dernierePeriode[zone] as number) ?? 0), 0)
    : 0
  const totalPeriodePrecedente = periodePrecedente
    ? zones.reduce((sum, zone) => sum + ((periodePrecedente[zone] as number) ?? 0), 0)
    : 0
  const variation =
    totalPeriodePrecedente > 0
      ? Math.round(((totalDernierePeriode - totalPeriodePrecedente) / totalPeriodePrecedente) * 100)
      : 0

  return (
    <div className="volumes">
      <header className="volumes__header">
        <div>
          <h1>Volumes</h1>
          <p>Évolution dans le temps et comparaison entre zones - saisonnalité et tendances.</p>
        </div>
        <div className="volumes__toggle">
          {GRANULARITES.map((option) => (
            <button
              key={option.valeur}
              type="button"
              className={
                option.valeur === pas ? 'volumes__toggle-btn volumes__toggle-btn--actif' : 'volumes__toggle-btn'
              }
              onClick={() => setPas(option.valeur)}
            >
              {option.label}
            </button>
          ))}
        </div>
      </header>

      <section className="volumes__resume">
        <div className="volumes__resume-item">
          <span className="volumes__resume-label">Dernière période</span>
          <span className="volumes__resume-valeur">{formatNombre(totalDernierePeriode)}</span>
        </div>
        <div className="volumes__resume-item">
          <span className="volumes__resume-label">Variation vs période précédente</span>
          <span
            className={
              variation >= 0
                ? 'volumes__resume-valeur volumes__resume-valeur--hausse'
                : 'volumes__resume-valeur volumes__resume-valeur--baisse'
            }
          >
            {variation >= 0 ? '+' : ''}
            {variation}%
          </span>
        </div>
      </section>

      <section className="volumes__panel">
        <h2>Évolution des volumes par zone</h2>
        {isLoading ? (
          <p className="volumes__vide">Chargement...</p>
        ) : (
          <ResponsiveContainer width="100%" height={340}>
            <LineChart data={evolution} margin={{ top: 8, right: 16, left: -8, bottom: 8 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#ececf0" vertical={false} />
              <XAxis dataKey="periode" tick={{ fontSize: 12, fill: '#6b7280' }} tickMargin={8} />
              <YAxis tick={{ fontSize: 12, fill: '#6b7280' }} width={48} />
              <Tooltip contentStyle={{ borderRadius: 10, border: '1px solid #ececf0', fontSize: 13 }} />
              <Legend wrapperStyle={{ fontSize: 12.5 }} />
              {zones.map((zone, index) => (
                <Line
                  key={zone}
                  type="monotone"
                  dataKey={zone}
                  stroke={PALETTE[index % PALETTE.length]}
                  strokeWidth={2.5}
                  dot={false}
                />
              ))}
            </LineChart>
          </ResponsiveContainer>
        )}
      </section>

      <section className="volumes__panel">
        <h2>Comparaison entre zones (période cumulée)</h2>
        {isLoading ? (
          <p className="volumes__vide">Chargement...</p>
        ) : (
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={comparaison} margin={{ top: 8, right: 16, left: -8, bottom: 8 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#ececf0" vertical={false} />
              <XAxis dataKey="zone" tick={{ fontSize: 12, fill: '#6b7280' }} />
              <YAxis tick={{ fontSize: 12, fill: '#6b7280' }} width={48} />
              <Tooltip contentStyle={{ borderRadius: 10, border: '1px solid #ececf0', fontSize: 13 }} />
              <Bar dataKey="volume" fill="#f5741e" radius={[8, 8, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </section>
    </div>
  )
}
