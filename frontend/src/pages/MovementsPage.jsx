import { useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'
import { useTranslation } from '../i18n/LanguageContext'

export default function MovementsPage() {
  const { transactions, reload } = useOutletContext()
  const { t } = useTranslation()
  const [importing, setImporting] = useState(false)
  const [categorizing, setCategorizing] = useState(false)
  const [message, setMessage] = useState(null)

  const handleImport = async (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    setImporting(true); setMessage(null)
    try {
      const form = new FormData()
      form.append('file', file)
      const res = await fetch('/api/import/csv', { method: 'POST', body: form })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(t('mov.importedMsg', { n: data.imported, e: data.errors.length }))
      reload()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setImporting(false)
      event.target.value = ''
    }
  }

  const handleCategorize = async () => {
    setCategorizing(true); setMessage(null)
    try {
      const res = await fetch('/api/transactions/categorize', { method: 'POST' })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(t('mov.categorizedMsg', { n: data.categorized, ai: data.byAi, rule: data.byRule }))
      reload()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setCategorizing(false)
    }
  }

  return (
    <>
      <h1 className="page-title">{t('mov.title')}</h1>
      <p className="page-subtitle">{t('mov.subtitle')}</p>

      <div className="card">
        <div className="file-field">
          <label className="file-field">
            <span style={{ fontWeight: 600, fontSize: 14 }}>{t('mov.import')}</span>
            <input type="file" accept=".csv,.n43,.txt,text/csv,text/plain" onChange={handleImport} disabled={importing} />
          </label>
          <button
            className="btn btn-primary"
            onClick={handleCategorize}
            disabled={categorizing || transactions.length === 0}
          >
            {categorizing ? t('mov.categorizing') : t('mov.categorize')}
          </button>
        </div>
        {message && <p className="msg">{message}</p>}
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table className="table">
          <thead>
            <tr>
              <th>{t('col.date')}</th>
              <th>{t('col.description')}</th>
              <th>{t('col.category')}</th>
              <th className="right">{t('col.amount')}</th>
            </tr>
          </thead>
          <tbody>
            {transactions.map((tx) => (
              <tr key={tx.id}>
                <td className="muted" style={{ whiteSpace: 'nowrap' }}>{tx.bookedAt}</td>
                <td>{tx.description}</td>
                <td>{tx.category ? <span className="tag">{tx.category}</span> : <span className="tag-empty">—</span>}</td>
                <td className={`right num ${Number(tx.amount) < 0 ? 'amount-neg' : 'amount-pos'}`}>{eur(tx.amount)}</td>
              </tr>
            ))}
            {transactions.length === 0 && (
              <tr><td className="empty" colSpan={4}>{t('mov.empty')}</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
