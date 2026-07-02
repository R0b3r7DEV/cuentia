import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'
import Stat from '../components/Stat'
import Dashboard from '../components/Dashboard'
import { useTranslation } from '../i18n/LanguageContext'

export default function DashboardPage() {
  const { transactions, stats } = useOutletContext()
  const { t } = useTranslation()

  const income = transactions.filter((tx) => Number(tx.amount) > 0).reduce((s, tx) => s + Number(tx.amount), 0)
  const expenses = transactions.filter((tx) => Number(tx.amount) < 0).reduce((s, tx) => s + Number(tx.amount), 0)
  const balance = income + expenses

  return (
    <>
      <h1 className="page-title">{t('dash.title')}</h1>
      <p className="page-subtitle">{t('dash.subtitle')}</p>

      <div className="card">
        <div className="stat-row">
          <Stat label={t('stat.income')} value={eur(income)} color="var(--pos)" />
          <Stat label={t('stat.expenses')} value={eur(expenses)} color="var(--neg)" />
          <Stat label={t('stat.balance')} value={eur(balance)} />
        </div>
      </div>

      <div className="card">
        <Dashboard stats={stats} />
      </div>
    </>
  )
}
