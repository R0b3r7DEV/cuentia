import { useEffect, useState } from 'react'
import { useTranslation } from '../i18n/LanguageContext'

/**
 * Billing settings: choose the invoice mode (standard vs Verifactu demo) and fill the issuer fiscal
 * profile that a standard RD 1619/2012 invoice requires (business name + fiscal address + NIF).
 *
 * ES: Ajustes de facturación: elegir el modo (estándar vs Verifactu demo) y rellenar el perfil fiscal del
 * emisor que exige una factura ordinaria RD 1619/2012 (razón social + domicilio fiscal + NIF).
 */
export default function BillingSettings() {
  const { t } = useTranslation()
  const [form, setForm] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)

  useEffect(() => {
    fetch('/api/account/settings').then((r) => r.ok && r.json()).then((d) => d && setForm({
      billingMode: d.billingMode || 'standard',
      businessName: d.businessName || '',
      fiscalAddress: d.fiscalAddress || '',
      taxId: d.taxId || '',
    }))
  }, [])

  if (!form) return null

  const set = (patch) => setForm((f) => ({ ...f, ...patch }))

  const save = async () => {
    setSaving(true); setMessage(null)
    try {
      const res = await fetch('/api/account/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
      if (!res.ok) throw new Error()
      const d = await res.json()
      set({ billingMode: d.billingMode })
      setMessage(t('billset.saved'))
    } catch {
      setMessage(t('common.errorMsg', { msg: 'error' }))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="card">
      <h2>{t('billset.title')}</h2>
      <p className="msg" style={{ marginTop: 0 }}>{t('billset.desc')}</p>

      <div className="mode-choice">
        <label className={`mode-option${form.billingMode === 'standard' ? ' active' : ''}`}>
          <input type="radio" name="billingMode" value="standard"
            checked={form.billingMode === 'standard'} onChange={() => set({ billingMode: 'standard' })} />
          <div>
            <strong>{t('billset.standard')}</strong>
            <span className="muted">{t('billset.standardDesc')}</span>
          </div>
        </label>
        <label className={`mode-option${form.billingMode === 'verifactu' ? ' active' : ''}`}>
          <input type="radio" name="billingMode" value="verifactu"
            checked={form.billingMode === 'verifactu'} onChange={() => set({ billingMode: 'verifactu' })} />
          <div>
            <strong>{t('billset.verifactu')} <span className="demo-badge">{t('billset.demoTag')}</span></strong>
            <span className="muted">{t('billset.verifactuDesc')}</span>
          </div>
        </label>
      </div>

      <div className="form-sec">{t('billset.issuer')}</div>
      <p className="msg" style={{ marginTop: 0 }}>{t('billset.issuerDesc')}</p>
      <div className="field-row">
        <label className="field"><span>{t('billset.businessName')}</span>
          <input value={form.businessName} onChange={(e) => set({ businessName: e.target.value })} /></label>
        <label className="field field-sm"><span>{t('billset.taxId')}</span>
          <input value={form.taxId} onChange={(e) => set({ taxId: e.target.value })} /></label>
      </div>
      <label className="field"><span>{t('billset.fiscalAddress')}</span>
        <input value={form.fiscalAddress} onChange={(e) => set({ fiscalAddress: e.target.value })} /></label>

      <button className="btn btn-primary btn-sm" style={{ marginTop: '0.9rem' }} onClick={save} disabled={saving}>
        {saving ? t('billset.saving') : t('billset.save')}
      </button>
      {message && <p className="msg">{message}</p>}
    </div>
  )
}
