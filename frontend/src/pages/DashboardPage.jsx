import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'
import Stat from '../components/Stat'
import Dashboard from '../components/Dashboard'

export default function DashboardPage() {
  const { transactions, stats } = useOutletContext()

  const income = transactions.filter((t) => Number(t.amount) > 0).reduce((s, t) => s + Number(t.amount), 0)
  const expenses = transactions.filter((t) => Number(t.amount) < 0).reduce((s, t) => s + Number(t.amount), 0)
  const balance = income + expenses

  return (
    <>
      <h1 style={{ marginBottom: 4 }}>Dashboard</h1>
      <p style={{ color: '#666', marginTop: 0 }}>Where your money comes from and goes to.</p>

      <div style={{ display: 'flex', gap: 32, margin: '1.5rem 0' }}>
        <Stat label="Income" value={eur(income)} color="#16a34a" />
        <Stat label="Expenses" value={eur(expenses)} color="#dc2626" />
        <Stat label="Balance" value={eur(balance)} />
      </div>

      <Dashboard stats={stats} />
    </>
  )
}
