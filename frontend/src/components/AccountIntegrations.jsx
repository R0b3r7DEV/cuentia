import { useEffect, useState } from 'react'
import { useTranslation } from '../i18n/LanguageContext'

/**
 * "Bring your own key" panel in the Account page: the user pastes their own Anthropic / GoCardless
 * credentials to enable AI and open banking for their account. Keys are sent once, stored encrypted,
 * and never shown again (only a configured/masked status comes back).
 * ES: Panel "trae tu propia clave" en Cuenta: el usuario pega sus credenciales para activar IA y banca
 * abierta. Las claves se envían una vez, se guardan cifradas y no se vuelven a mostrar.
 */
export default function AccountIntegrations() {
  const { t } = useTranslation()
  const [status, setStatus] = useState(null)
  const [aiKey, setAiKey] = useState('')
  const [gcId, setGcId] = useState('')
  const [gcKey, setGcKey] = useState('')
  const [busy, setBusy] = useState(null) // 'ai' | 'gc'
  const [msg, setMsg] = useState(null)

  const load = async () => {
    const r = await fetch('/api/account/integrations')
    if (r.ok) setStatus(await r.json())
  }
  useEffect(() => { load() }, [])

  const call = async (kind, method, url, body) => {
    setBusy(kind); setMsg(null)
    try {
      const r = await fetch(url, {
        method,
        headers: body ? { 'Content-Type': 'application/json' } : undefined,
        body: body ? JSON.stringify(body) : undefined,
      })
      if (r.ok) { setStatus(await r.json()); if (body) setMsg(t('int.savedMsg')) }
    } finally { setBusy(null) }
  }

  const saveAi = () => call('ai', 'PUT', '/api/account/integrations/anthropic', { key: aiKey }).then(() => setAiKey(''))
  const removeAi = () => call('ai', 'DELETE', '/api/account/integrations/anthropic')
  const saveGc = () => call('gc', 'PUT', '/api/account/integrations/gocardless', { secretId: gcId, secretKey: gcKey }).then(() => { setGcId(''); setGcKey('') })
  const removeGc = () => call('gc', 'DELETE', '/api/account/integrations/gocardless')

  if (!status) return null

  const badge = (s) => s.configured
    ? <span className="chain-badge chain-ok">{t('int.configured')}{s.hint ? ' · ' + s.hint : ''}</span>
    : <span className="chain-badge chain-empty">{t('int.notConfigured')}</span>

  return (
    <div className="card">
      <h2>{t('int.title')}</h2>
      <p className="msg" style={{ marginTop: 0 }}>{t('int.desc')}</p>

      <div className="form-sec">{t('int.ai.title')}</div>
      <p className="muted" style={{ fontSize: 13 }}>
        {t('int.ai.desc')} <a className="link-btn" href="https://console.anthropic.com/settings/keys" target="_blank" rel="noreferrer">{t('int.getKey')}</a>
      </p>
      <div className="verify-bar">{badge(status.anthropic)}</div>
      <div className="field-row" style={{ marginTop: 8 }}>
        <label className="field"><span>{t('int.ai.label')}</span>
          <input type="password" autoComplete="off" placeholder="sk-ant-…" value={aiKey} onChange={(e) => setAiKey(e.target.value)} /></label>
      </div>
      <div className="verify-bar">
        <button className="btn btn-glass btn-sm" onClick={saveAi} disabled={busy === 'ai' || !aiKey.trim()}>{busy === 'ai' ? t('int.saving') : t('int.save')}</button>
        {status.anthropic.configured && <button className="link-btn danger-link" onClick={removeAi}>{t('int.remove')}</button>}
      </div>

      <div className="form-sec">{t('int.bank.title')}</div>
      <p className="muted" style={{ fontSize: 13 }}>
        {t('int.bank.desc')} <a className="link-btn" href="https://bankaccountdata.gocardless.com/" target="_blank" rel="noreferrer">{t('int.getKey')}</a>
      </p>
      <div className="verify-bar">{badge(status.gocardless)}</div>
      <div className="field-row" style={{ marginTop: 8 }}>
        <label className="field"><span>{t('int.bank.id')}</span>
          <input autoComplete="off" value={gcId} onChange={(e) => setGcId(e.target.value)} /></label>
        <label className="field"><span>{t('int.bank.key')}</span>
          <input type="password" autoComplete="off" value={gcKey} onChange={(e) => setGcKey(e.target.value)} /></label>
      </div>
      <div className="verify-bar">
        <button className="btn btn-glass btn-sm" onClick={saveGc} disabled={busy === 'gc' || !gcId.trim() || !gcKey.trim()}>{busy === 'gc' ? t('int.saving') : t('int.save')}</button>
        {status.gocardless.configured && <button className="link-btn danger-link" onClick={removeGc}>{t('int.remove')}</button>}
      </div>

      <p className="muted" style={{ fontSize: 12, marginTop: 14 }}>{t('int.privacy')}</p>
      {msg && <p className="msg">{msg}</p>}
    </div>
  )
}
