import { useEffect, useState } from 'react'
import { useTranslation } from '../../i18n/LanguageContext'

const blank = { id: null, name: '', taxId: '', address: '', email: '' }

export default function CustomersTab() {
  const { t } = useTranslation()
  const [customers, setCustomers] = useState([])
  const [form, setForm] = useState(null) // null = closed; otherwise the customer being created/edited
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)

  const load = async () => {
    const res = await fetch('/api/customers')
    if (res.ok) setCustomers(await res.json())
  }
  useEffect(() => { load() }, [])

  const save = async (e) => {
    e.preventDefault()
    setSaving(true); setMessage(null)
    try {
      const res = await fetch(form.id ? `/api/customers/${form.id}` : '/api/customers', {
        method: form.id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setForm(null)
      setMessage(t('cust.savedMsg'))
      await load()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setSaving(false)
    }
  }

  const remove = async (c) => {
    if (!window.confirm(t('cust.deleteConfirm'))) return
    setMessage(null)
    const res = await fetch(`/api/customers/${c.id}`, { method: 'DELETE' })
    if (res.ok) { await load() }
    else {
      const data = await res.json().catch(() => ({}))
      setMessage(t('common.errorMsg', { msg: data.error || `HTTP ${res.status}` }))
    }
  }

  const set = (patch) => setForm((f) => ({ ...f, ...patch }))

  return (
    <>
      <p className="page-subtitle">{t('cust.subtitle')}</p>

      <div className="card">
        {form === null ? (
          <button className="btn btn-glass btn-sm" onClick={() => setForm({ ...blank })}>{t('cust.new')}</button>
        ) : (
          <form className="invoice-form" onSubmit={save}>
            <div className="field-row">
              <label className="field">
                <span>{t('cust.name')}</span>
                <input required value={form.name} onChange={(e) => set({ name: e.target.value })} />
              </label>
              <label className="field field-sm">
                <span>{t('cust.taxId')}</span>
                <input required value={form.taxId} onChange={(e) => set({ taxId: e.target.value })} />
              </label>
            </div>
            <div className="field-row">
              <label className="field">
                <span>{t('cust.address')}</span>
                <input value={form.address || ''} onChange={(e) => set({ address: e.target.value })} />
              </label>
              <label className="field">
                <span>{t('cust.email')}</span>
                <input type="email" value={form.email || ''} onChange={(e) => set({ email: e.target.value })} />
              </label>
            </div>
            <div className="verify-bar">
              <button className="btn btn-glass btn-sm" type="submit" disabled={saving}>
                {saving ? t('cust.saving') : t('cust.save')}
              </button>
              <button type="button" className="link-btn" onClick={() => setForm(null)}>{t('cust.cancel')}</button>
            </div>
          </form>
        )}
        {message && <p className="msg">{message}</p>}
      </div>

      <div className="card table-scroll" style={{ padding: 0 }}>
        <table className="table">
          <thead>
            <tr>
              <th>{t('cust.name')}</th>
              <th>{t('cust.taxId')}</th>
              <th>{t('cust.email')}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {customers.map((c) => (
              <tr key={c.id}>
                <td>{c.name}</td>
                <td className="num">{c.taxId}</td>
                <td className="muted">{c.email || '—'}</td>
                <td className="right">
                  <button className="link-btn" onClick={() => setForm({ ...blank, ...c })}>{t('cust.edit')}</button>
                  <button className="link-btn danger-link" onClick={() => remove(c)}>{t('cust.delete')}</button>
                </td>
              </tr>
            ))}
            {customers.length === 0 && (
              <tr><td className="empty" colSpan={4}>{t('cust.empty')}</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
