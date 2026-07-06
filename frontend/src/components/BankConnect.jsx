import { useEffect, useState } from 'react'
import { useTranslation } from '../i18n/LanguageContext'

/**
 * Open-banking (GoCardless) connect + import flow. Status-aware: if the feature isn't configured on the
 * backend it shows an honest disabled explainer instead of a dead button.
 * ES: Flujo de conectar + importar de banca abierta (GoCardless). Consciente del estado: si la función no
 * está configurada en el backend, muestra un aviso honesto en vez de un botón muerto.
 */
export default function BankConnect({ onImported }) {
  const { t } = useTranslation()
  const [enabled, setEnabled] = useState(null) // null = loading
  const [institutions, setInstitutions] = useState([])
  const [institutionId, setInstitutionId] = useState('')
  const [requisitionId, setRequisitionId] = useState(null)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState(null)

  useEffect(() => {
    (async () => {
      const res = await fetch('/api/bank/status')
      const { enabled } = await res.json()
      setEnabled(enabled)
      if (enabled) {
        const list = await fetch('/api/bank/institutions')
        if (list.ok) setInstitutions(await list.json())
      }
    })()
  }, [])

  const connect = async () => {
    if (!institutionId) return
    setBusy(true); setMessage(null)
    try {
      const res = await fetch('/api/bank/connect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ institutionId, redirect: window.location.href }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setRequisitionId(data.requisitionId)
      window.open(data.link, '_blank', 'noopener')
      setMessage(t('bank.authorize'))
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setBusy(false)
    }
  }

  const runImport = async () => {
    setBusy(true); setMessage(null)
    try {
      const res = await fetch('/api/bank/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ requisitionId }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(t('bank.importedMsg', { n: data.imported, s: data.skipped }))
      setRequisitionId(null)
      onImported?.()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setBusy(false)
    }
  }

  if (enabled === null) return null

  return (
    <div className="card">
      <div className="verify-bar" style={{ justifyContent: 'space-between' }}>
        <strong>{t('bank.title')}</strong>
        <span className="muted" style={{ fontSize: 12 }}>{t('bank.demoNote')}</span>
      </div>

      {!enabled ? (
        <p className="muted" style={{ fontSize: 13, marginTop: 10 }}>{t('bank.disabled')}</p>
      ) : requisitionId ? (
        <div className="verify-bar" style={{ marginTop: 12 }}>
          <button className="btn btn-glass btn-sm" onClick={runImport} disabled={busy}>
            {busy ? t('bank.importing') : t('bank.import')}
          </button>
        </div>
      ) : (
        <div className="verify-bar" style={{ marginTop: 12 }}>
          <select className="bank-select" value={institutionId} onChange={(e) => setInstitutionId(e.target.value)}>
            <option value="">{t('bank.choose')}</option>
            {institutions.map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
          </select>
          <button className="btn btn-glass btn-sm" onClick={connect} disabled={busy || !institutionId}>
            {busy ? t('bank.connecting') : t('bank.connect')}
          </button>
        </div>
      )}
      {message && <p className="msg">{message}</p>}
    </div>
  )
}
