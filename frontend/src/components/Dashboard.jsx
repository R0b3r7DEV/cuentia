import {
  BarChart, Bar, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer, CartesianGrid, LabelList,
} from 'recharts'

// Validated palette (see dataviz skill). Blue = income / magnitude, red = expenses.
// ES: Paleta validada (skill dataviz). Azul = ingresos / magnitud, rojo = gastos.
const BLUE = '#2a78d6'
const RED = '#e34948'
const MUTED = '#898781'   // axis / labels
const GRID = '#e1e0d9'    // hairline gridline

const eur = (v) => new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(v))

export default function Dashboard({ stats }) {
  if (!stats) return null

  // Expenses by category, as positive magnitudes, largest first.
  // ES: Gastos por categoría, como magnitudes positivas, de mayor a menor.
  const expensesByCategory = (stats.byCategory || [])
    .filter((c) => c.kind === 'expense')
    .map((c) => ({ name: c.category, amount: Math.abs(Number(c.total)) }))
    .sort((a, b) => b.amount - a.amount)

  const byMonth = (stats.byMonth || []).map((m) => ({
    month: m.month,
    income: Number(m.income),
    expenses: Number(m.expenses),
  }))

  return (
    <section style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, margin: '1.5rem 0' }}>
      {/* Chart 1 — spending by category (single hue = magnitude comparison) */}
      <figure style={{ margin: 0 }}>
        <figcaption style={{ fontWeight: 600, marginBottom: 8 }}>Spending by category</figcaption>
        <ResponsiveContainer width="100%" height={Math.max(160, expensesByCategory.length * 38)}>
          <BarChart data={expensesByCategory} layout="vertical" margin={{ left: 8, right: 48 }}>
            <CartesianGrid horizontal={false} stroke={GRID} />
            <XAxis type="number" tickFormatter={eur} stroke={MUTED} fontSize={12} />
            <YAxis type="category" dataKey="name" width={150} stroke={MUTED} fontSize={12} />
            <Tooltip formatter={(v) => eur(v)} cursor={{ fill: 'rgba(0,0,0,0.04)' }} />
            <Bar dataKey="amount" fill={BLUE} radius={[0, 4, 4, 0]}>
              <LabelList dataKey="amount" position="right" formatter={eur} fontSize={12} fill={MUTED} />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </figure>

      {/* Chart 2 — income vs expenses by month (two series → legend) */}
      <figure style={{ margin: 0 }}>
        <figcaption style={{ fontWeight: 600, marginBottom: 8 }}>Income vs expenses by month</figcaption>
        <ResponsiveContainer width="100%" height={Math.max(160, expensesByCategory.length * 38)}>
          <BarChart data={byMonth} margin={{ left: 8, right: 8 }}>
            <CartesianGrid vertical={false} stroke={GRID} />
            <XAxis dataKey="month" stroke={MUTED} fontSize={12} />
            <YAxis tickFormatter={eur} stroke={MUTED} fontSize={12} width={70} />
            <Tooltip formatter={(v) => eur(v)} cursor={{ fill: 'rgba(0,0,0,0.04)' }} />
            <Legend />
            <Bar dataKey="income" name="Income" fill={BLUE} radius={[4, 4, 0, 0]} />
            <Bar dataKey="expenses" name="Expenses" fill={RED} radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </figure>
    </section>
  )
}
