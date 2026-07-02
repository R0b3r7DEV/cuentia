import { useEffect, useState } from 'react'

// Format a decimal string as euros using Spanish locale.
// ES: Formatea un string decimal como euros con la configuración española.
const eur = (value) =>
  new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value))

function App() {
  const [transactions, setTransactions] = useState([])
  const [error, setError] = useState(null)
  const [importing, setImporting] = useState(false)
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

  // Totals for the little summary. / Totales para el pequeño resumen.
  const income = transactions.filter((t) => Number(t.amount) > 0).reduce((s, t) => s + Number(t.amount), 0)
  const expenses = transactions.filter((t) => Number(t.amount) < 0).reduce((s, t) => s + Number(t.amount), 0)
  const balance = income + expenses

  return (
    <main style={{ fontFamily: 'system-ui, sans-serif', maxWidth: 820, margin: '0 auto', padding: '2rem' }}>
      <h1 style={{ marginBottom: 0 }}>Cuentia</h1>
      <p style={{ color: '#666', marginTop: 4 }}>AI cash-flow &amp; tax copilot — work in progress</p>

      <div style={{ display: 'flex', gap: 24, margin: '1.5rem 0' }}>
        <Stat label="Income" value={eur(income)} color="#16a34a" />
        <Stat label="Expenses" value={eur(expenses)} color="#dc2626" />
        <Stat label="Balance" value={eur(balance)} color="#111" />
      </div>

      <label style={{ display: 'inline-block', marginBottom: '1rem' }}>
        <span style={{ fontWeight: 600 }}>Import bank CSV: </span>
        <input type="file" accept=".csv,text/csv" onChange={handleImport} disabled={importing} />
      </label>
      {message && <p style={{ color: '#555' }}>{message}</p>}
      {error && <p style={{ color: '#dc2626' }}>API error: {error}</p>}

      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
        <thead>
          <tr style={{ textAlign: 'left', borderBottom: '2px solid #eee' }}>
            <th style={{ padding: '8px 6px' }}>Date</th>
            <th style={{ padding: '8px 6px' }}>Description</th>
            <th style={{ padding: '8px 6px', textAlign: 'right' }}>Amount</th>
          </tr>
        </thead>
        <tbody>
          {transactions.map((t) => (
            <tr key={t.id} style={{ borderBottom: '1px solid #f2f2f2' }}>
              <td style={{ padding: '8px 6px', color: '#666', whiteSpace: 'nowrap' }}>{t.bookedAt}</td>
              <td style={{ padding: '8px 6px' }}>{t.description}</td>
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
            <tr><td colSpan={3} style={{ padding: '1.5rem', color: '#999', textAlign: 'center' }}>
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
