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
      <h1 style={{ marginBottom: 4 }}>Movements</h1>
      <p style={{ color: '#666', marginTop: 0 }}>Import a bank CSV and categorize your transactions.</p>

      <div style={{ margin: '1rem 0' }}>
        <label>
          <span style={{ fontWeight: 600 }}>Import bank CSV: </span>
          <input type="file" accept=".csv,text/csv" onChange={handleImport} disabled={importing} />
        </label>
        <button
          onClick={handleCategorize}
          disabled={categorizing || transactions.length === 0}
          style={{ marginLeft: 12, padding: '6px 12px', cursor: 'pointer' }}
        >
          {categorizing ? 'Categorizing…' : '🧠 Categorize'}
        </button>
        {message && <p style={{ color: '#555' }}>{message}</p>}
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
        <thead>
          <tr style={{ textAlign: 'left', borderBottom: '2px solid #eee' }}>
            <th style={{ padding: '8px 6px' }}>Date</th>
            <th style={{ padding: '8px 6px' }}>Description</th>
            <th style={{ padding: '8px 6px' }}>Category</th>
            <th style={{ padding: '8px 6px', textAlign: 'right' }}>Amount</th>
          </tr>
        </thead>
        <tbody>
          {transactions.map((t) => (
            <tr key={t.id} style={{ borderBottom: '1px solid #f2f2f2' }}>
              <td style={{ padding: '8px 6px', color: '#666', whiteSpace: 'nowrap' }}>{t.bookedAt}</td>
              <td style={{ padding: '8px 6px' }}>{t.description}</td>
              <td style={{ padding: '8px 6px' }}>
                {t.category
                  ? <span style={{ background: '#eef2ff', color: '#3730a3', padding: '2px 8px', borderRadius: 999, fontSize: 12 }}>{t.category}</span>
                  : <span style={{ color: '#ccc' }}>—</span>}
              </td>
              <td style={{ padding: '8px 6px', textAlign: 'right', fontVariantNumeric: 'tabular-nums', color: Number(t.amount) < 0 ? '#dc2626' : '#16a34a' }}>
                {eur(t.amount)}
              </td>
            </tr>
          ))}
          {transactions.length === 0 && (
            <tr><td colSpan={4} style={{ padding: '1.5rem', color: '#999', textAlign: 'center' }}>
              No transactions yet — import a CSV to get started.
            </td></tr>
          )}
        </tbody>
      </table>
    </>
  )
}
