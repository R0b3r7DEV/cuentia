import { useState, useEffect, useCallback } from 'react'

/**
 * Loads all the app's data (transactions + stats + VAT + IRPF) and exposes a single
 * `reload()` so any page can refresh everything after an import or categorization.
 * ES: Carga todos los datos de la app (movimientos + stats + IVA + IRPF) y expone un
 * único `reload()` para que cualquier página pueda refrescar todo tras importar o categorizar.
 */
export function useFinanceData() {
  const [transactions, setTransactions] = useState([])
  const [stats, setStats] = useState(null)
  const [vat, setVat] = useState(null)
  const [irpf, setIrpf] = useState(null)
  const [error, setError] = useState(null)

  const reload = useCallback(() => {
    fetch('/api/transactions')
      .then((r) => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json() })
      .then(setTransactions)
      .catch((e) => setError(e.message))

    fetch('/api/stats').then((r) => (r.ok ? r.json() : null)).then(setStats).catch(() => {})
    fetch('/api/vat').then((r) => (r.ok ? r.json() : null)).then(setVat).catch(() => {})
    fetch('/api/irpf').then((r) => (r.ok ? r.json() : null)).then(setIrpf).catch(() => {})
  }, [])

  useEffect(() => { reload() }, [reload])

  return { transactions, stats, vat, irpf, error, reload }
}
