import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { useAuth } from '../../auth/AuthContext'
import { fenetreParDefaut, getDemande } from '../../api/distributeur'
import { KpiCard } from '../../components/KpiCard'
import { formatNombre } from '../../lib/format'
import type { Granularite } from '../../api/types'
import './Demande.css'

const GRANULARITES: Array<{ valeur: Granularite; label: string }> = [
  { valeur: 'jour', label: 'Jour' },
  { valeur: 'semaine', label: 'Semaine' },
  { valeur: 'mois', label: 'Mois' },
]

type Dimension = 'zone' | 'format'

const PALETTE = ['#f5741e', '#f7a56b', '#f7d06b', '#6b7280', '#34c77b', '#4b8bef', '#a55bd6']

export function DistributeurDemande() {
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''
  const [pas, setPas] = useState<Granularite>('mois')
  const [dimension, setDimension] = useState<Dimension>('zone')
  const { depuis, jusqua } = useMemo(() => fenetreParDefaut(), [])

  const { data, isLoading } = useQuery({
    queryKey: ['distributeur', orgUuid, 'demande', pas, depuis, jusqua],
    queryFn: () => getDemande(orgUuid, { depuis, jusqua, pas }),
    enabled: orgUuid !== '',
  })

  const points = useMemo(() => data ?? [], [data])

  const series = useMemo(() => Array.from(new Set(points.map((p) => (dimension === 'zone' ? p.zone : p.format_code)))), [
    points,
    dimension,
  ])

  const chartData = useMemo(() => {
    const parPeriode = new Map<string, Record<string, number | string>>()
    for (const point of points) {
      const cle = dimension === 'zone' ? point.zone : point.format_code
      const existant = parPeriode.get(point.periode) ?? { periode: point.periode }
      existant[cle] = ((existant[cle] as number) ?? 0) + point.volume
      parPeriode.set(point.periode, existant)
    }
    return Array.from(parPeriode.values())
  }, [points, dimension])

  const volumeTotal = points.reduce((sum, p) => sum + p.volume, 0)

  const parZone = useMemo(() => {
    const totaux = new Map<string, number>()
    for (const point of points) {
      totaux.set(point.zone, (totaux.get(point.zone) ?? 0) + point.volume)
    }
    return Array.from(totaux.entries()).sort((a, b) => b[1] - a[1])
  }, [points])

  const parFormat = useMemo(() => {
    const totaux = new Map<string, number>()
    for (const point of points) {
      totaux.set(point.format_code, (totaux.get(point.format_code) ?? 0) + point.volume)
    }
    return Array.from(totaux.entries()).sort((a, b) => b[1] - a[1])
  }, [points])

  const zoneEnTete = parZone[0]
  const formatEnTete = parFormat[0]

  return (
    <div className="demande">
      <header className="demande__header">
        <div>
          <h1>Demande régionale</h1>
          <p>Volumes livrés et réappros, agrégés par zone et par format - aucune donnée de foyer.</p>
        </div>
        <div className="demande__controles">
          <div className="demande__toggle">
            {GRANULARITES.map((option) => (
              <button
                key={option.valeur}
                type="button"
                className={
                  option.valeur === pas ? 'demande__toggle-btn demande__toggle-btn--actif' : 'demande__toggle-btn'
                }
                onClick={() => setPas(option.valeur)}
              >
                {option.label}
              </button>
            ))}
          </div>
          <div className="demande__toggle">
            <button
              type="button"
              className={dimension === 'zone' ? 'demande__toggle-btn demande__toggle-btn--actif' : 'demande__toggle-btn'}
              onClick={() => setDimension('zone')}
            >
              Par zone
            </button>
            <button
              type="button"
              className={
                dimension === 'format' ? 'demande__toggle-btn demande__toggle-btn--actif' : 'demande__toggle-btn'
              }
              onClick={() => setDimension('format')}
            >
              Par format
            </button>
          </div>
        </div>
      </header>

      <section className="demande__kpis">
        <KpiCard label="Volume total" value={isLoading ? '-' : formatNombre(volumeTotal)} suffix="bouteilles" />
        <KpiCard
          label="Zone la plus demandeuse"
          value={isLoading || !zoneEnTete ? '-' : zoneEnTete[0]}
          trend={zoneEnTete ? `${formatNombre(zoneEnTete[1])} bouteilles` : undefined}
          tone="accent"
        />
        <KpiCard
          label="Format dominant"
          value={isLoading || !formatEnTete ? '-' : formatEnTete[0]}
          trend={formatEnTete ? `${formatNombre(formatEnTete[1])} bouteilles` : undefined}
        />
        <KpiCard label="Zones suivies" value={isLoading ? '-' : parZone.length} tone="success" />
      </section>

      <section className="demande__panel">
        <div className="demande__panel-header">
          <h2>Évolution des volumes {dimension === 'zone' ? 'par zone' : 'par format'}</h2>
        </div>
        {isLoading ? (
          <p className="demande__vide">Chargement...</p>
        ) : (
          <div className="demande__chart">
            <ResponsiveContainer width="100%" height={360}>
              <LineChart data={chartData} margin={{ top: 8, right: 16, left: -8, bottom: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#ececf0" vertical={false} />
                <XAxis dataKey="periode" tick={{ fontSize: 12, fill: '#6b7280' }} tickMargin={8} />
                <YAxis tick={{ fontSize: 12, fill: '#6b7280' }} width={48} />
                <Tooltip
                  contentStyle={{
                    borderRadius: 10,
                    border: '1px solid #ececf0',
                    fontSize: 13,
                  }}
                />
                <Legend wrapperStyle={{ fontSize: 12.5 }} />
                {series.map((cle, index) => (
                  <Line
                    key={cle}
                    type="monotone"
                    dataKey={cle}
                    stroke={PALETTE[index % PALETTE.length]}
                    strokeWidth={2.5}
                    dot={false}
                  />
                ))}
              </LineChart>
            </ResponsiveContainer>
          </div>
        )}
      </section>
    </div>
  )
}
