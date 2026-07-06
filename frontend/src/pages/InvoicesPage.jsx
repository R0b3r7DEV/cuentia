import { Fragment, useEffect, useState } from 'react'
import { eur } from '../lib/format'
import { useTranslation } from '../i18n/LanguageContext'

const emptyLine = () => ({ description: '', quantity: 1, unitPrice: '', vatRate: '21' })

// Client-side preview only — the server recomputes totals in exact cents and is authoritative.
// ES: Solo previsualización — el servidor recalcula los totales en céntimos exactos y es la autoridad.
function previewTotal(lines) {
  let cents = 0
  for (const l of lines) {
    const base = Math.round(Number(l.unitPrice || 0) * 100) * Math.max(1, Number(l.quantity || 1))
    cents += base + Math.round((base * Number(l.vatRate || 0)) / 100)
  }
  return cents / 100
}

export default function InvoicesPage() {
  const { t } = useTranslation()
  const [invoices, setInvoices] = useState([])
  const [verify, setVerify] = useState(null)
  const [verifying, setVerifying] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState(null)
  const [expanded, setExpanded] = useState(null) // { id, detail }

  const [customer, setCustomer] = useState({ name: '', taxId: '' })
  const [series, setSeries] = useState('')
  const [lines, setLines] = useState([emptyLine()])

  const loadInvoices = async () => {
    const res = await fetch('/api/invoices')
    if (res.ok) setInvoices(await res.json())
  }

  useEffect(() => { loadInvoices() }, [])

  const runVerify = async () => {
    setVerifying(true)
    try {
      const res = await fetch('/api/invoices/verify')
      setVerify(await res.json())
    } finally {
      setVerifying(false)
    }
  }

  const setLine = (i, patch) =>
    setLines((prev) => prev.map((l, j) => (j === i ? { ...l, ...patch } : l)))

  const submit = async (e) => {
    e.preventDefault()
    setSubmitting(true); setMessage(null)
    try {
      const res = await fetch('/api/invoices', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ series: series || undefined, customer, lines }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessage(t('inv.createdMsg', { n: data.number }))
      setCustomer({ name: '', taxId: '' }); setSeries(''); setLines([emptyLine()])
      setShowForm(false)
      await loadInvoices()
      await runVerify()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setSubmitting(false)
    }
  }

  const toggleRow = async (id) => {
    if (expanded?.id === id) return setExpanded(null)
    setExpanded({ id, detail: null })
    const res = await fetch(`/api/invoices/${id}`)
    if (res.ok) setExpanded({ id, detail: await res.json() })
  }

  const reasonText = (reason) =>
    reason === 'record_tampered' ? t('inv.reasonTampered') : t('inv.reasonMismatch')

  return (
    <>
      <h1 className="page-title">{t('inv.title')}</h1>
      <p className="page-subtitle">{t('inv.subtitle')}</p>

      {/* Verifactu chain status — the headline "wow": tamper-evident integrity. */}
      <div className="card">
        <div className="verify-bar">
          <button className="btn btn-glass btn-sm" onClick={runVerify} disabled={verifying}>
            {verifying ? t('inv.verifying') : t('inv.verify')}
          </button>
          {verify && (
            verify.count === 0 ? (
              <span className="chain-badge chain-empty">{t('inv.chainEmpty')}</span>
            ) : verify.ok ? (
              <span className="chain-badge chain-ok">🔒 {t('inv.chainOk', { n: verify.count })}</span>
            ) : (
              <span className="chain-badge chain-broken">
                ⚠️ {t('inv.chainBroken', { at: verify.brokenAt, reason: reasonText(verify.reason) })}
              </span>
            )
          )}
        </div>
      </div>

      <div className="card">
        <button className="btn btn-glass btn-sm" onClick={() => setShowForm((s) => !s)}>
          {showForm ? t('inv.newHide') : t('inv.new')}
        </button>

        {showForm && (
          <form className="invoice-form" onSubmit={submit}>
            <div className="field-row">
              <label className="field">
                <span>{t('inv.customerName')}</span>
                <input required value={customer.name}
                  onChange={(e) => setCustomer({ ...customer, name: e.target.value })} />
              </label>
              <label className="field">
                <span>{t('inv.taxId')}</span>
                <input required value={customer.taxId}
                  onChange={(e) => setCustomer({ ...customer, taxId: e.target.value })} />
              </label>
              <label className="field field-sm">
                <span>{t('inv.series')}</span>
                <input placeholder={String(new Date().getFullYear())} value={series}
                  onChange={(e) => setSeries(e.target.value)} />
              </label>
            </div>

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
                    <td>
                      <input required value={l.description}
                        onChange={(e) => setLine(i, { description: e.target.value })} />
                    </td>
                    <td className="right">
                      <input className="num-input" type="number" min="1" value={l.quantity}
                        onChange={(e) => setLine(i, { quantity: e.target.value })} />
                    </td>
                    <td className="right">
                      <input className="num-input" type="number" step="0.01" min="0" required value={l.unitPrice}
                        onChange={(e) => setLine(i, { unitPrice: e.target.value })} />
                    </td>
                    <td className="right">
                      <input className="num-input" type="number" step="0.01" min="0" value={l.vatRate}
                        onChange={(e) => setLine(i, { vatRate: e.target.value })} />
                    </td>
                    <td className="right">
                      {lines.length > 1 && (
                        <button type="button" className="link-btn"
                          onClick={() => setLines(lines.filter((_, j) => j !== i))}>
                          {t('inv.removeLine')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div className="verify-bar">
              <button type="button" className="link-btn" onClick={() => setLines([...lines, emptyLine()])}>
                {t('inv.addLine')}
              </button>
              <span className="muted num">{t('inv.previewTotal', { total: eur(previewTotal(lines)) })}</span>
            </div>

            <button className="btn btn-glass" type="submit" disabled={submitting}>
              {submitting ? t('inv.creating') : t('inv.create')}
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
              <th>{t('col.status')}</th>
            </tr>
          </thead>
          <tbody>
            {invoices.map((inv) => (
              <Fragment key={inv.id}>
                <tr className="row-click" onClick={() => toggleRow(inv.id)}>
                  <td className="num">{inv.number}</td>
                  <td>{inv.customer}</td>
                  <td className="muted" style={{ whiteSpace: 'nowrap' }}>{inv.issuedAt}</td>
                  <td className="right num">{eur(inv.total)}</td>
                  <td><span className="tag tag-sealed">{t('inv.sealed')}</span></td>
                </tr>
                {expanded?.id === inv.id && expanded.detail && (
                  <tr className="detail-row">
                    <td colSpan={5}>
                      <div className="invoice-detail">
                        <ul className="detail-lines">
                          {expanded.detail.lines.map((l, i) => (
                            <li key={i}>
                              <span>{l.description}</span>
                              <span className="muted num">
                                {l.quantity} × {eur(l.unitPrice)} · {l.vatRate}% IVA
                              </span>
                            </li>
                          ))}
                        </ul>
                        {expanded.detail.verifactu && (
                          <div className="fingerprint">
                            <div><span className="fp-label">{t('inv.hash')}</span>
                              <code className="hash">{expanded.detail.verifactu.hash}</code></div>
                            <div><span className="fp-label">{t('inv.prevHash')}</span>
                              <code className="hash">
                                {expanded.detail.verifactu.previousHash || `(${t('inv.none')})`}
                              </code></div>
                            <div><span className="fp-label">{t('inv.genAt')}</span>
                              <span className="muted num">{expanded.detail.verifactu.generatedAt}</span></div>
                          </div>
                        )}
                      </div>
                    </td>
                  </tr>
                )}
              </Fragment>
            ))}
            {invoices.length === 0 && (
              <tr><td className="empty" colSpan={5}>{t('inv.empty')}</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
