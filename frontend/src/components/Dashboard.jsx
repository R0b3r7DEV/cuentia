import { useEffect, useState } from 'react'
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer, CartesianGrid, LabelList,
} from 'recharts'
import { eur } from '../lib/format'
import { useTranslation } from '../i18n/LanguageContext'

// Read chart colors from the CSS design tokens so the charts follow the theme
// (including dark mode). / Lee los colores de los tokens CSS para que los gráficos
// sigan el tema (incluido el modo oscuro).
function useChartColors() {
  const [c, setC] = useState({ blue: '#2a78d6', red: '#e34948', grid: '#e1e0d9', muted: '#898781' })
  useEffect(() => {
    const s = getComputedStyle(document.documentElement)
    const g = (name, fallback) => (s.getPropertyValue(name).trim() || fallback)
    setC({
      blue: g('--chart-1', '#2a78d6'),
      red: g('--chart-2', '#e34948'),
      grid: g('--chart-grid', '#e1e0d9'),
      muted: g('--chart-muted', '#898781'),
    })
  }, [])
  return c
}

export default function Dashboard({ stats }) {
  const { blue, red, grid, muted } = useChartColors()
  const { t } = useTranslation()
  if (!stats) return null

  const expensesByCategory = (stats.byCategory || [])
    .filter((c) => c.kind === 'expense')
    .map((c) => ({ name: c.category, amount: Math.abs(Number(c.total)) }))
    .sort((a, b) => b.amount - a.amount)

  const byMonth = (stats.byMonth || []).map((m) => ({
    month: m.month, income: Number(m.income), expenses: Number(m.expenses),
  }))

  const height = Math.max(180, expensesByCategory.length * 40)

  return (
    <div className="chart-grid">
      <figure className="figure">
        <figcaption>{t('chart.byCategory')}</figcaption>
        <ResponsiveContainer width="100%" height={height}>
          <BarChart data={expensesByCategory} layout="vertical" margin={{ left: 8, right: 56 }}>
            <CartesianGrid horizontal={false} stroke={grid} />
            <XAxis type="number" tickFormatter={eur} stroke={muted} fontSize={12} />
            <YAxis type="category" dataKey="name" width={150} stroke={muted} fontSize={12} />
            <Tooltip formatter={(v) => eur(v)} cursor={{ fill: 'rgba(128,128,128,0.08)' }} />
            <Bar dataKey="amount" fill={blue} radius={[0, 4, 4, 0]}>
              <LabelList dataKey="amount" position="right" formatter={eur} fontSize={12} fill={muted} />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </figure>

      <figure className="figure">
        <figcaption>{t('chart.byMonth')}</figcaption>
        <ResponsiveContainer width="100%" height={height}>
          <BarChart data={byMonth} margin={{ left: 8, right: 8 }}>
            <CartesianGrid vertical={false} stroke={grid} />
            <XAxis dataKey="month" stroke={muted} fontSize={12} />
            <YAxis tickFormatter={eur} stroke={muted} fontSize={12} width={70} />
            <Tooltip formatter={(v) => eur(v)} cursor={{ fill: 'rgba(128,128,128,0.08)' }} />
            <Legend />
            <Bar dataKey="income" name={t('stat.income')} fill={blue} radius={[4, 4, 0, 0]} />
            <Bar dataKey="expenses" name={t('stat.expenses')} fill={red} radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </figure>
    </div>
  )
}
