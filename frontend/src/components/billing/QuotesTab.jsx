import { Fragment, useEffect, useState } from 'react'
import { eur } from '../../lib/format'
import { useTranslation } from '../../i18n/LanguageContext'

const emptyLine = () => ({ description: '', quantity: 1, unitPrice: '', vatRate: '21' })

function previewTotal(lines) {
  let cents = 0
  for (const l of lines) {
    const base = Math.round(Number(l.unitPrice || 0) * 100) * Math.max(1, Number(l.quantity || 1))
    cents += base + Math.round((base * Number(l.vatRate || 0)) / 100)
  }
  return cents / 100
}

const STATUSES = ['draft', 'sent', 'accepted', 'rejected']

export default function QuotesTab({ prefill }) {
  const { t } = useTranslation()
  const [quotes, setQuotes] = useState([])
  const [customers, setCustomers] = useState([])
  const [services, setServices] = useState([])
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState(null)
  const [expanded, setExpanded] = useState(null) // { id, detail }
  const [busyId, setBusyId] = useState(null)

  const [customerId, setCustomerId] = useState('')
  const [customer, setCustomer] = useState({ name: '', taxId: '' })
  const [validUntil, setValidUntil] = useState('')
  const [lines, setLines] = useState([emptyLine()])

  const load = async () => {
    const [q, c, s] = await Promise.all([fetch('/api/quotes'), fetch('/api/customers'), fetch('/api/services')])
    if (q.ok) setQuotes(await q.json())
    if (c.ok) setCustomers(await c.json())
    if (s.ok) setServices(await s.json())
  }
  useEffect(() => { load() }, [])

  // Open a prefilled quote (lines from the installation designer's materials).
  useEffect(() => {
    if (prefill?.lines?.length) { setLines(prefill.lines); setShowForm(true) }
  }, [prefill])

  const setLine = (i, patch) => setLines((prev) => prev.map((l, j) => (j === i ? { ...l, ...patch } : l)))
  const addFromService = (id) => {
    const s = services.find((x) => String(x.id) === String(id))
    if (!s) return
    setLines((prev) => {
      const next = [...prev, { description: s.name, quantity: 1, unitPrice: s.unitPrice, vatRate: s.vatRate }]
      return next.length === 2 && !prev[0].description && !prev[0].unitPrice ? next.slice(1) : next
    })
  }

  const submit = async (e) => {
    e.preventDefault()
    setSubmitting(true); setMessage(null)
    try {
      const who = customerId ? { customerId: Number(customerId) } : { customer }
      const res = await fetch('/api/quotes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...who, validUntil: validUntil || undefined, lines }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(t('qt.createdMsg', { n: data.number }))
      setCustomerId(''); setCustomer({ name: '', taxId: '' }); setValidUntil(''); setLines([emptyLine()])
      setShowForm(false)
      await load()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setSubmitting(false)
    }
  }

  const toggleRow = async (id) => {
    if (expanded?.id === id) return setExpanded(null)
    setExpanded({ id, detail: null })
    const res = await fetch(`/api/quotes/${id}`)
    if (res.ok) setExpanded({ id, detail: await res.json() })
  }

  const refresh = async (id) => {
    const res = await fetch(`/api/quotes/${id}`)
    if (res.ok) setExpanded({ id, detail: await res.json() })
    await load()
  }

  const setStatus = async (id, status) => {
    await fetch(`/api/quotes/${id}/status`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status }),
    })
    await refresh(id)
  }

  const convert = async (id) => {
    setBusyId(id); setMessage(null)
    try {
      const res = await fetch(`/api/quotes/${id}/convert`, { method: 'POST' })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(t('qt.convertedMsg', { n: data.invoiceNumber }))
      await refresh(id)
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <>
      <p className="page-subtitle">{t('qt.subtitle')}</p>

      <div className="card">
        <button className="btn btn-glass btn-sm" onClick={() => setShowForm((s) => !s)}>
          {showForm ? t('qt.newHide') : t('qt.new')}
        </button>

        {showForm && (
          <form className="invoice-form" onSubmit={submit}>
            <div className="field-row">
              <label className="field">
                <span>{t('inv.customerLabel')}</span>
                <select className="bank-select" value={customerId} onChange={(e) => setCustomerId(e.target.value)}>
                  <option value="">{t('inv.newCustomer')}</option>
                  {customers.map((c) => <option key={c.id} value={c.id}>{c.name} · {c.taxId}</option>)}
                </select>
              </label>
              <label className="field field-sm">
                <span>{t('qt.validUntil')}</span>
                <input type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} />
              </label>
            </div>

            {!customerId && (
              <div className="field-row">
                <label className="field">
                  <span>{t('inv.customerName')}</span>
                  <input required value={customer.name} onChange={(e) => setCustomer({ ...customer, name: e.target.value })} />
                </label>
                <label className="field">
                  <span>{t('inv.taxId')}</span>
                  <input required value={customer.taxId} onChange={(e) => setCustomer({ ...customer, taxId: e.target.value })} />
                </label>
              </div>
            )}

            <table className="table lines-table">
              <thead>
                <tr>
                  <th>{t('inv.lineDesc')}</th>
                  <th className="right">{t('inv.qty')}</th>
                  <th className="right">{t('inv.unitPrice')}</th>
                  <th className="right">{t('inv.vatRate')}</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={i}>
                    <td><input required value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} /></td>
                    <td className="right"><input className="num-input" type="number" min="1" value={l.quantity} onChange={(e) => setLine(i, { quantity: e.target.value })} /></td>
                    <td className="right"><input className="num-input" type="number" step="0.01" min="0" required value={l.unitPrice} onChange={(e) => setLine(i, { unitPrice: e.target.value })} /></td>
                    <td className="right"><input className="num-input" type="number" step="0.01" min="0" value={l.vatRate} onChange={(e) => setLine(i, { vatRate: e.target.value })} /></td>
                    <td className="right">
                      {lines.length > 1 && (
                        <button type="button" className="link-btn" onClick={() => setLines(lines.filter((_, j) => j !== i))}>
                          {t('inv.removeLine')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div className="verify-bar">
              <button type="button" className="link-btn" onClick={() => setLines([...lines, emptyLine()])}>{t('inv.addLine')}</button>
              {services.length > 0 && (
                <select className="bank-select" value="" onChange={(e) => { addFromService(e.target.value); e.target.value = '' }}>
                  <option value="">{t('inv.fromCatalog')}</option>
                  {services.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
              )}
              <span className="muted num">{t('inv.previewTotal', { total: eur(previewTotal(lines)) })}</span>
            </div>

            <button className="btn btn-glass" type="submit" disabled={submitting}>
              {submitting ? t('qt.creating') : t('qt.create')}
            </button>
          </form>
        )}
        {message && <p className="msg">{message}</p>}
      </div>

      <div className="card table-scroll" style={{ padding: 0 }}>
        <table className="table">
          <thead>
            <tr>
              <th>{t('col.number')}</th>
              <th>{t('col.customer')}</th>
              <th>{t('col.date')}</th>
              <th className="right">{t('col.total')}</th>
              <th>{t('qt.status')}</th>
            </tr>
          </thead>
          <tbody>
            {quotes.map((q) => (
              <Fragment key={q.id}>
                <tr className="row-click" onClick={() => toggleRow(q.id)}>
                  <td className="num">{q.number}</td>
                  <td>{q.customer}</td>
                  <td className="muted" style={{ whiteSpace: 'nowrap' }}>{q.issuedAt}</td>
                  <td className="right num">{eur(q.total)}</td>
                  <td><span className={`tag status-${q.status}`}>{t(`status.${q.status}`)}</span></td>
                </tr>
                {expanded?.id === q.id && expanded.detail && (
                  <tr className="detail-row">
                    <td colSpan={5}>
                      <div className="invoice-detail">
                        <ul className="detail-lines">
                          {expanded.detail.lines.map((l, i) => (
                            <li key={i}>
                              <span>{l.description}</span>
                              <span className="muted num">{l.quantity} × {eur(l.unitPrice)} · {l.vatRate}% IVA</span>
                            </li>
                          ))}
                        </ul>
                        <div className="verify-bar">
                          {expanded.detail.convertedInvoice ? (
                            <span className="chain-badge chain-ok">✓ {t('qt.convertedTo', { n: expanded.detail.convertedInvoice.number })}</span>
                          ) : (
                            <>
                              <select className="bank-select" value={expanded.detail.status}
                                onChange={(e) => setStatus(q.id, e.target.value)}>
                                {STATUSES.map((s) => <option key={s} value={s}>{t(`status.${s}`)}</option>)}
                              </select>
                              <button className="btn btn-glass btn-sm" disabled={busyId === q.id} onClick={() => convert(q.id)}>
                                {busyId === q.id ? t('qt.converting') : t('qt.convert')}
                              </button>
                            </>
                          )}
                          <a className="link-btn" href={`/api/quotes/${q.id}/pdf`}>{t('qt.download')}</a>
                        </div>
                      </div>
                    </td>
                  </tr>
                )}
              </Fragment>
            ))}
            {quotes.length === 0 && (
              <tr><td className="empty" colSpan={5}>{t('qt.empty')}</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
