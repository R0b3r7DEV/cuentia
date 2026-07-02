import { useEffect, useState } from 'react'
import Dashboard from './components/Dashboard'

// Format a decimal string as euros using Spanish locale.
// ES: Formatea un string decimal como euros con la configuración española.
const eur = (value) =>
  new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value))

function App() {
  const [transactions, setTransactions] = useState([])
  const [stats, setStats] = useState(null)
  const [vat, setVat] = useState(null)
  const [irpf, setIrpf] = useState(null)
  const [error, setError] = useState(null)
  const [importing, setImporting] = useState(false)
  const [categorizing, setCategorizing] = useState(false)
  const [message, setMessage] = useState(null)

  // Load the transactions from the API. / Carga los movimientos desde la API.
  const load = () => {
    fetch('/api/transactions')
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        return res.json()
      })
      .then(setTransactions)
      .catch((err) => setError(err.message))

    fetch('/api/stats')
      .then((res) => (res.ok ? res.json() : null))
      .then(setStats)
      .catch(() => {})

    fetch('/api/vat')
      .then((res) => (res.ok ? res.json() : null))
      .then(setVat)
      .catch(() => {})

    fetch('/api/irpf')
      .then((res) => (res.ok ? res.json() : null))
      .then(setIrpf)
      .catch(() => {})
  }

  useEffect(load, [])

  // Upload the selected CSV file, then refresh the list.
  // ES: Sube el CSV seleccionado y luego refresca la lista.
  const handleImport = async (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    setImporting(true)
    setMessage(null)
    try {
      const form = new FormData()
      form.append('file', file)
      const res = await fetch('/api/import/csv', { method: 'POST', body: form })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(`Imported ${data.imported} · errors: ${data.errors.length}`)
      load()
    } catch (err) {
      setMessage(`Error: ${err.message}`)
    } finally {
      setImporting(false)
      event.target.value = '' // allow re-uploading the same file / permite re-subir el mismo fichero
    }
  }

  // Ask the backend to categorize uncategorized transactions, then refresh.
  // ES: Pide al backend categorizar los movimientos sin categoría y refresca.
  const handleCategorize = async () => {
    setCategorizing(true)
    setMessage(null)
    try {
      const res = await fetch('/api/transactions/categorize', { method: 'POST' })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(`Categorized ${data.categorized} (AI: ${data.byAi}, rules: ${data.byRule})`)
      load()
    } catch (err) {
      setMessage(`Error: ${err.message}`)
    } finally {
      setCategorizing(false)
    }
  }

  // Totals for the little summary. / Totales para el pequeño resumen.
  const income = transactions.filter((t) => Number(t.amount) > 0).reduce((s, t) => s + Number(t.amount), 0)
  const expenses = transactions.filter((t) => Number(t.amount) < 0).reduce((s, t) => s + Number(t.amount), 0)
  const balance = income + expenses

  return (
    <main style={{ fontFamily: 'system-ui, sans-serif', maxWidth: 1000, margin: '0 auto', padding: '2rem' }}>
      <h1 style={{ marginBottom: 0 }}>Cuentia</h1>
      <p style={{ color: '#666', marginTop: 4 }}>AI cash-flow &amp; tax copilot — work in progress</p>

      <div style={{ display: 'flex', gap: 24, margin: '1.5rem 0' }}>
        <Stat label="Income" value={eur(income)} color="#16a34a" />
        <Stat label="Expenses" value={eur(expenses)} color="#dc2626" />
        <Stat label="Balance" value={eur(balance)} color="#111" />
      </div>

      <Dashboard stats={stats} />

      {vat && (
        <section style={{ border: '1px solid #eee', borderRadius: 12, padding: '1rem 1.25rem', margin: '1.5rem 0' }}>
          <h2 style={{ fontSize: 16, margin: '0 0 0.75rem' }}>VAT summary (IVA)</h2>
          <div style={{ display: 'flex', gap: 32, flexWrap: 'wrap' }}>
            <Stat label="Output VAT (repercutido)" value={eur(vat.outputVat)} color="#2a78d6" />
            <Stat label="Input VAT (soportado)" value={eur(vat.inputVat)} color="#e34948" />
            <Stat
              label={Number(vat.net) >= 0 ? 'Net VAT to pay' : 'Net VAT to reclaim'}
              value={eur(Math.abs(Number(vat.net)))}
              color="#111"
            />
          </div>
          <p style={{ color: '#999', fontSize: 12, marginBottom: 0 }}>
            Output − input VAT. Rates inferred from each transaction's category (default Spanish rates).
          </p>
        </section>
      )}

      {irpf && (
        <section style={{ border: '1px solid #eee', borderRadius: 12, padding: '1rem 1.25rem', margin: '1.5rem 0' }}>
          <h2 style={{ fontSize: 16, margin: '0 0 0.75rem' }}>IRPF · modelo 130 (estimate, {irpf.year})</h2>

          {irpf.nextDeadline && irpf.nextDeadline.daysLeft <= 30 && (
            <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', color: '#9a3412', padding: '8px 12px', borderRadius: 8, marginBottom: 12, fontSize: 14 }}>
              ⏰ Modelo 130 Q{irpf.nextDeadline.quarter} due on {irpf.nextDeadline.date} — {irpf.nextDeadline.daysLeft} days left
            </div>
          )}

          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
            <thead>
              <tr style={{ textAlign: 'left', borderBottom: '2px solid #eee', color: '#666' }}>
                <th style={{ padding: '6px' }}>Quarter</th>
                <th style={{ padding: '6px', textAlign: 'right' }}>Net (base)</th>
                <th style={{ padding: '6px', textAlign: 'right' }}>Payment (20%)</th>
                <th style={{ padding: '6px' }}>Deadline</th>
              </tr>
            </thead>
            <tbody>
              {irpf.quarters.map((q) => (
                <tr key={q.quarter} style={{ borderBottom: '1px solid #f2f2f2' }}>
                  <td style={{ padding: '6px' }}>{q.label}</td>
                  <td style={{ padding: '6px', textAlign: 'right', fontVariantNumeric: 'tabular-nums' }}>{eur(q.net)}</td>
                  <td style={{ padding: '6px', textAlign: 'right', fontVariantNumeric: 'tabular-nums', fontWeight: 600 }}>{eur(q.payment)}</td>
                  <td style={{ padding: '6px', color: '#666' }}>{q.deadline}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p style={{ color: '#999', fontSize: 12, marginBottom: 0 }}>
            20% of year-to-date net (income − deductible expenses, without VAT), cumulative per quarter.
            Salary (“Nómina”) is excluded — it is not self-employment income.
          </p>
        </section>
      )}

      <label style={{ display: 'inline-block', marginBottom: '1rem' }}>
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
      {error && <p style={{ color: '#dc2626' }}>API error: {error}</p>}

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
              <td style={{
                padding: '8px 6px',
                textAlign: 'right',
                fontVariantNumeric: 'tabular-nums',
                color: Number(t.amount) < 0 ? '#dc2626' : '#16a34a',
              }}>
                {eur(t.amount)}
              </td>
            </tr>
          ))}
          {transactions.length === 0 && !error && (
            <tr><td colSpan={4} style={{ padding: '1.5rem', color: '#999', textAlign: 'center' }}>
              No transactions yet — import a CSV to get started.
            </td></tr>
          )}
        </tbody>
      </table>
    </main>
  )
}

function Stat({ label, value, color }) {
  return (
    <div>
      <div style={{ fontSize: 12, textTransform: 'uppercase', color: '#999', letterSpacing: 0.5 }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 700, color }}>{value}</div>
    </div>
  )
}

export default App
