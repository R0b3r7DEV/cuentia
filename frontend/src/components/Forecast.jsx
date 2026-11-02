import { useEffect, useState } from 'react'
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { eur } from '../lib/format'
import { useTranslation } from '../i18n/LanguageContext'

function useChartColors() {
  const [c, setC] = useState({ blue: '#1558c9', grid: '#dadcd3', muted: '#5d636e' })
  useEffect(() => {
    const s = getComputedStyle(document.documentElement)
    const g = (n, fb) => (s.getPropertyValue(n).trim() || fb)
    setC({ blue: g('--chart-1', '#1558c9'), grid: g('--chart-grid', '#dadcd3'), muted: g('--chart-muted', '#5d636e') })
  }, [])
  return c
}

export default function Forecast({ forecast }) {
  const { blue, grid, muted } = useChartColors()
  const { t } = useTranslation()
  if (!forecast) return null

  const data = forecast.points.map((p) => ({
    label: p.dayOffset === 0 ? t('forecast.now') : `+${p.dayOffset}d`,
    balance: Number(p.balance),
  }))

  return (
    <ResponsiveContainer width="100%" height={220}>
      <LineChart data={data} margin={{ left: 8, right: 16, top: 8 }}>
        <CartesianGrid stroke={grid} />
        <XAxis dataKey="label" stroke={muted} fontSize={12} />
        <YAxis tickFormatter={eur} stroke={muted} fontSize={12} width={80} />
        <Tooltip formatter={(v) => eur(v)} cursor={{ stroke: muted }} />
        <Line type="monotone" dataKey="balance" name={t('forecast.title')} stroke={blue} strokeWidth={2} dot={{ r: 4 }} />
      </LineChart>
    </ResponsiveContainer>
  )
}
