import type { ReactNode } from 'react'
import './KpiCard.css'

interface KpiCardProps {
  label: string
  value: ReactNode
  suffix?: string
  trend?: string
  tone?: 'default' | 'accent' | 'success'
}

export function KpiCard({ label, value, suffix, trend, tone = 'default' }: KpiCardProps) {
  return (
    <div className={`kpi-card kpi-card--${tone}`}>
      <p className="kpi-card__label">{label}</p>
      <p className="kpi-card__value">
        {value}
        {suffix ? <span className="kpi-card__suffix">{suffix}</span> : null}
      </p>
      {trend ? <p className="kpi-card__trend">{trend}</p> : null}
    </div>
  )
}
