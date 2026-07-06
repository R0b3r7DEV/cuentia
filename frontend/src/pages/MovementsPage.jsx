import { useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'
import { useTranslation } from '../i18n/LanguageContext'
import BankConnect from '../components/BankConnect'

export default function MovementsPage() {
  const { transactions, reload } = useOutletContext()
  const { t } = useTranslation()
  const [importing, setImporting] = useState(false)
  const [categorizing, setCategorizing] = useState(false)
  const [demoLoading, setDemoLoading] = useState(false)
  const [message, setMessage] = useState(null)

  const handleDemo = async () => {
    setDemoLoading(true); setMessage(null)
    try {
      const res = await fetch('/api/demo/load', { method: 'POST' })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      reload()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setDemoLoading(false)
    }
  }

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
          <label className={`btn btn-glass btn-sm${importing ? ' is-disabled' : ''}`}>
            {importing ? t('mov.importing') : t('mov.import')}
            <input
              type="file"
              className="file-input-hidden"
              accept=".csv,.n43,.txt,text/csv,text/plain"
              onChange={handleImport}
              disabled={importing}
            />
          </label>
          <button
            className="btn btn-glass btn-sm"
            onClick={handleCategorize}
            disabled={categorizing || transactions.length === 0}
          >
            {categorizing ? t('mov.categorizing') : t('mov.categorize')}
          </button>
        </div>
        <p className="muted" style={{ fontSize: 12, marginTop: 8 }}>{t('mov.importHint')}</p>
        {message && <p className="msg">{message}</p>}
      </div>

      <BankConnect onImported={reload} />

      <div className="card table-scroll" style={{ padding: 0 }}>
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
              <tr><td className="empty" colSpan={4}>
                <p style={{ marginBottom: 12 }}>{t('mov.empty')}</p>
                <button className="btn btn-glass btn-sm" onClick={handleDemo} disabled={demoLoading}>
                  {demoLoading ? t('mov.loadingDemo') : t('mov.loadDemo')}
                </button>
              </td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
