import { useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'

export default function MovementsPage() {
  const { transactions, reload } = useOutletContext()
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
      setMessage(`Imported ${data.imported} · errors: ${data.errors.length}`)
      reload()
    } catch (err) {
      setMessage(`Error: ${err.message}`)
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
      setMessage(`Categorized ${data.categorized} (AI: ${data.byAi}, rules: ${data.byRule})`)
      reload()
    } catch (err) {
      setMessage(`Error: ${err.message}`)
    } finally {
      setCategorizing(false)
    }
  }

  return (
    <>
      <h1 className="page-title">Movements</h1>
      <p className="page-subtitle">Import a bank CSV and categorize your transactions.</p>

      <div className="card">
        <div className="file-field">
          <label className="file-field">
            <span style={{ fontWeight: 600, fontSize: 14 }}>Import bank CSV</span>
            <input type="file" accept=".csv,text/csv" onChange={handleImport} disabled={importing} />
          </label>
          <button
            className="btn btn-primary"
            onClick={handleCategorize}
            disabled={categorizing || transactions.length === 0}
          >
            {categorizing ? 'Categorizing…' : '🧠 Categorize'}
          </button>
        </div>
        {message && <p className="msg">{message}</p>}
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table className="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Description</th>
              <th>Category</th>
              <th className="right">Amount</th>
            </tr>
          </thead>
          <tbody>
            {transactions.map((t) => (
              <tr key={t.id}>
                <td className="muted" style={{ whiteSpace: 'nowrap' }}>{t.bookedAt}</td>
                <td>{t.description}</td>
                <td>{t.category ? <span className="tag">{t.category}</span> : <span className="tag-empty">—</span>}</td>
                <td className={`right num ${Number(t.amount) < 0 ? 'amount-neg' : 'amount-pos'}`}>{eur(t.amount)}</td>
              </tr>
            ))}
            {transactions.length === 0 && (
              <tr><td className="empty" colSpan={4}>No transactions yet — import a CSV to get started.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
