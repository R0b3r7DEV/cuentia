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
      <h1 className="page-title">Dashboard</h1>
      <p className="page-subtitle">Where your money comes from and goes to.</p>

      <div className="card">
        <div className="stat-row">
          <Stat label="Income" value={eur(income)} color="var(--pos)" />
          <Stat label="Expenses" value={eur(expenses)} color="var(--neg)" />
          <Stat label="Balance" value={eur(balance)} />
        </div>
      </div>

      <div className="card">
        <Dashboard stats={stats} />
      </div>
    </>
  )
}
