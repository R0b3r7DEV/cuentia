import { useEffect, useState } from 'react'
import { eur } from '../../lib/format'
import { useTranslation } from '../../i18n/LanguageContext'

const blank = { id: null, name: '', unitPrice: '', vatRate: '21' }

export default function ServicesTab() {
  const { t } = useTranslation()
  const [services, setServices] = useState([])
  const [form, setForm] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)

  const load = async () => {
    const res = await fetch('/api/services')
    if (res.ok) setServices(await res.json())
  }
  useEffect(() => { load() }, [])

  const save = async (e) => {
    e.preventDefault()
    setSaving(true); setMessage(null)
    try {
      const res = await fetch(form.id ? `/api/services/${form.id}` : '/api/services', {
        method: form.id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setForm(null)
      setMessage(t('svc.savedMsg'))
      await load()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setSaving(false)
    }
  }

  const remove = async (s) => {
    if (!window.confirm(t('svc.deleteConfirm'))) return
    const res = await fetch(`/api/services/${s.id}`, { method: 'DELETE' })
    if (res.ok) await load()
  }

  const set = (patch) => setForm((f) => ({ ...f, ...patch }))

  return (
    <>
      <p className="page-subtitle">{t('svc.subtitle')}</p>

      <div className="card">
        {form === null ? (
          <button className="btn btn-glass btn-sm" onClick={() => setForm({ ...blank })}>{t('svc.new')}</button>
        ) : (
          <form className="invoice-form" onSubmit={save}>
            <div className="field-row">
              <label className="field">
                <span>{t('svc.name')}</span>
                <input required value={form.name} onChange={(e) => set({ name: e.target.value })} />
              </label>
              <label className="field field-sm">
                <span>{t('svc.unitPrice')}</span>
                <input className="num-input" type="number" step="0.01" min="0" required
                  value={form.unitPrice} onChange={(e) => set({ unitPrice: e.target.value })} />
              </label>
              <label className="field field-sm">
                <span>{t('svc.vatRate')}</span>
                <input className="num-input" type="number" step="0.01" min="0"
                  value={form.vatRate} onChange={(e) => set({ vatRate: e.target.value })} />
              </label>
            </div>
            <div className="verify-bar">
              <button className="btn btn-primary btn-sm" type="submit" disabled={saving}>
                {saving ? t('svc.saving') : t('svc.save')}
              </button>
              <button type="button" className="link-btn" onClick={() => setForm(null)}>{t('svc.cancel')}</button>
            </div>
          </form>
        )}
        {message && <p className="msg">{message}</p>}
      </div>

      <div className="card table-scroll" style={{ padding: 0 }}>
        <table className="table">
          <thead>
            <tr>
              <th>{t('svc.name')}</th>
              <th className="right">{t('svc.unitPrice')}</th>
              <th className="right">{t('svc.vatRate')}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {services.map((s) => (
              <tr key={s.id}>
                <td>{s.name}</td>
                <td className="right num">{eur(s.unitPrice)}</td>
                <td className="right num">{s.vatRate}%</td>
                <td className="right">
                  <button className="link-btn" onClick={() => setForm({ ...blank, ...s })}>{t('svc.edit')}</button>
                  <button className="link-btn danger-link" onClick={() => remove(s)}>{t('svc.delete')}</button>
                </td>
              </tr>
            ))}
            {services.length === 0 && (
              <tr><td className="empty" colSpan={4}>{t('svc.empty')}</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
