import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import SignaturePad from '../components/workorders/SignaturePad'
import { downscalePhoto } from '../lib/photo'
import { canEdit, formatCents, nextActions, stepHours, toCents, totals } from '../lib/workOrders'

const VAT_RATES = ['21', '10', '4', '0']
const NEW_CUSTOMER = 'new'

const emptyLine = () => ({ description: '', quantity: 1, unitPrice: '', vatRate: '21' })

/** "21.00" → "21", "1.50" → "1.5": what the person typed, not the database format. */
const plain = (v) => {
  if (v === null || v === undefined) return ''
  const s = String(v)
  return (s.includes('.') ? s.replace(/0+$/, '').replace(/\.$/, '') : s).replace('.', ',')
}

function formFromOrder(o) {
  return {
    customerId: o.customer?.id ? String(o.customer.id) : NEW_CUSTOMER,
    customer: { name: '', taxId: '' },
    title: o.title ?? '',
    description: o.description ?? '',
    scheduledAt: o.scheduledAt ? o.scheduledAt.replace(' ', 'T') : '',
    laborHours: plain(o.laborHours ?? '0'),
    laborRate: plain(o.laborRate),
    laborVatRate: plain(o.laborVatRate ?? '21'),
    lines: (o.lines ?? []).map((l) => ({ ...l, unitPrice: plain(l.unitPrice), vatRate: plain(l.vatRate) })),
  }
}

/** The form as the API expects it; amounts with a dot. */
function payload(form) {
  const dot = (v) => String(v ?? '').trim().replace(',', '.')
  return {
    ...(form.customerId === NEW_CUSTOMER
      ? { customer: { name: form.customer.name.trim(), taxId: form.customer.taxId.trim() } }
      : { customerId: Number(form.customerId) }),
    title: form.title.trim(),
    description: form.description,
    scheduledAt: form.scheduledAt || null,
    laborHours: dot(form.laborHours) || '0',
    laborRate: dot(form.laborRate) || '0',
    laborVatRate: dot(form.laborVatRate) || '0',
    lines: form.lines
      .filter((l) => l.description.trim() || toCents(l.unitPrice))
      .map((l) => ({ description: l.description.trim(), quantity: l.quantity, unitPrice: dot(l.unitPrice), vatRate: dot(l.vatRate) })),
  }
}

/** First thing wrong with the form, as a translation key; null if it can be saved. */
function problem(form) {
  if (!form.title.trim()) return 'wo.err.title_required'
  if (form.customerId === NEW_CUSTOMER && !form.customer.name.trim()) return 'wo.err.customer_required'
  const amounts = [form.laborHours, form.laborRate, ...form.lines.map((l) => l.unitPrice)]
  if (amounts.some((a) => toCents(a) === null)) return 'wo.err.invalid_amount'
  return null
}

/** fetch + JSON, returning the backend's error code instead of throwing. */
async function api(method, url, body) {
  try {
    const isForm = body instanceof FormData
    const res = await fetch(url, {
      method,
      headers: body && !isForm ? { 'Content-Type': 'application/json' } : undefined,
      body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
    })
    const data = await res.json().catch(() => null)
    return res.ok ? { ok: true, data } : { ok: false, code: data?.error ?? 'generic' }
  } catch {
    return { ok: false, code: 'network' }
  }
}

/**
 * One work order, filled in at the site from the phone: client and job, materials from the catalogue,
 * labour hours, photos, the client's signature, live totals in cents, and a fixed bar at the bottom with
 * the one thing to do next (start → finish → invoice).
 *
 * ES: Un parte, rellenado a pie de obra desde el móvil: cliente y trabajo, materiales del catálogo, horas,
 * fotos, la firma del cliente, totales en vivo en céntimos y una barra fija abajo con lo siguiente que toca
 * (empezar → terminar → facturar).
 */
export default function WorkOrderPage() {
  const { id } = useParams()
  const isNew = id === 'nuevo'
  const navigate = useNavigate()
  const { t } = useTranslation()

  const [order, setOrder] = useState(null)
  const [form, setForm] = useState(null)
  const [dirty, setDirty] = useState(false)
  const [customers, setCustomers] = useState([])
  const [services, setServices] = useState([])
  const [busy, setBusy] = useState(null) // which action is running
  const [message, setMessage] = useState(null) // { kind: 'ok' | 'error', text }
  const [signer, setSigner] = useState('')
  const [padEmpty, setPadEmpty] = useState(true)
  const padRef = useRef(null)
  const signatureRef = useRef(null)
  const photoInput = useRef(null)

  const errorText = (code) => {
    const key = `wo.err.${code}`
    const text = t(key)
    return text === key ? t('wo.err.generic') : text
  }
  const fail = (code) => setMessage({ kind: 'error', text: errorText(code) })

  // Load the order (or a blank one), plus the customers and the service catalogue. Going from
  // /partes/nuevo to /partes/:id keeps this component mounted, so the previous state is cleared first.
  useEffect(() => {
    setForm(null)
    setOrder(null)
    setMessage(null)
    fetch('/api/customers').then((r) => (r.ok ? r.json() : [])).then(setCustomers).catch(() => {})
    fetch('/api/services').then((r) => (r.ok ? r.json() : [])).then(setServices).catch(() => {})

    if (isNew) {
      fetch('/api/account/settings')
        .then((r) => (r.ok ? r.json() : {}))
        .catch(() => ({}))
        .then((s) => {
          setOrder(null)
          setForm({ ...formFromOrder({ laborVatRate: '21' }), laborRate: plain(s.defaultLaborRate ?? '') })
          setDirty(false)
        })
      return
    }
    api('GET', `/api/work-orders/${id}`).then((r) => {
      if (!r.ok) { fail(r.code); return }
      setOrder(r.data)
      setForm(formFromOrder(r.data))
      setSigner(r.data.customer?.name ?? '')
      setDirty(false)
    })
  }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

  // Still loading — including the first render right after /partes/nuevo → /partes/:id, before the
  // effect above has cleared the form of the new order.
  if (!form || (!isNew && !order)) {
    return message ? <p className="msg error">{message.text}</p> : <p className="muted">{t('wo.loading')}</p>
  }

  const status = order?.status ?? 'pendiente'
  const editable = isNew || canEdit(status)
  const sums = totals(form)

  const update = (patch) => { setForm((f) => ({ ...f, ...patch })); setDirty(true); setMessage(null) }
  const setLine = (i, patch) => update({ lines: form.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)) })
  const removeLine = (i) => update({ lines: form.lines.filter((_, j) => j !== i) })
  const addLine = (line) => update({ lines: [...form.lines, line] })
  const addFromCatalog = (serviceId) => {
    const s = services.find((x) => String(x.id) === String(serviceId))
    if (s) addLine({ description: s.name, quantity: 1, unitPrice: plain(s.unitPrice), vatRate: plain(s.vatRate) })
  }

  const applyOrder = (o) => { setOrder(o); setForm(formFromOrder(o)); setDirty(false) }

  /** Save the form. Returns true when the order on screen is saved. */
  const save = async () => {
    const p = problem(form)
    if (p) { setMessage({ kind: 'error', text: t(p) }); return false }
    setBusy('save')
    const r = isNew ? await api('POST', '/api/work-orders', payload(form)) : await api('PUT', `/api/work-orders/${id}`, payload(form))
    setBusy(null)
    if (!r.ok) { fail(r.code); return false }
    if (isNew) { navigate(`/partes/${r.data.id}`, { replace: true }); return true }
    applyOrder(r.data)
    setMessage({ kind: 'ok', text: t('wo.saved') })
    return true
  }

  const move = async (to, withoutSignature = false) => {
    if (dirty && !(await save())) return
    setBusy(to)
    const r = await api('POST', `/api/work-orders/${id}/status`, { status: to, withoutSignature })
    setBusy(null)
    if (!r.ok) { fail(r.code); return }
    applyOrder(r.data)
    setMessage({ kind: 'ok', text: t(`wo.moved.${to}`) })
  }

  const finish = async () => {
    if (!order.signature.signed) {
      // No signature yet: either the client is not there, or go and get it.
      if (!window.confirm(t('wo.confirmNoSignature'))) {
        signatureRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        return
      }
      return move('terminado', true)
    }
    return move('terminado')
  }

  const convert = async () => {
    setBusy('convert')
    const r = await api('POST', `/api/work-orders/${id}/convert`)
    if (!r.ok) { setBusy(null); fail(r.code); return }
    const fresh = await api('GET', `/api/work-orders/${id}`)
    setBusy(null)
    if (fresh.ok) applyOrder(fresh.data)
    setMessage({ kind: 'ok', text: t('wo.convertedMsg', { n: r.data.invoiceNumber }) })
  }

  const remove = async () => {
    setBusy('delete')
    const r = await api('DELETE', `/api/work-orders/${id}`)
    setBusy(null)
    if (!r.ok) { fail(r.code); return }
    navigate('/partes', { replace: true })
  }

  const run = (action) => {
    if (action.confirm && !window.confirm(t(action.confirm))) return
    switch (action.id) {
      case 'start': return move('en_curso')
      case 'backToPending': return move('pendiente')
      case 'reopen': return move('en_curso')
      case 'finish': return finish()
      case 'convert': return convert()
      case 'delete': return remove()
      case 'viewInvoice': return navigate('/invoices')
      default: return undefined
    }
  }

  const addPhotos = async (files) => {
    for (const file of files) {
      if (order.photos.length >= order.maxPhotos) { fail('photo_limit'); break }
      setBusy('photo')
      let blob
      try { blob = await downscalePhoto(file) } catch { setBusy(null); fail('invalid_image'); continue }
      const fd = new FormData()
      fd.append('photo', blob, 'foto.jpg')
      const r = await api('POST', `/api/work-orders/${id}/photos`, fd)
      setBusy(null)
      if (!r.ok) { fail(r.code); break }
      setOrder((o) => ({ ...o, photos: [...o.photos, r.data] }))
    }
    if (photoInput.current) photoInput.current.value = ''
  }

  const deletePhoto = async (photo) => {
    if (!window.confirm(t('wo.confirmDeletePhoto'))) return
    const r = await api('DELETE', photo.url)
    if (!r.ok) { fail(r.code); return }
    setOrder((o) => ({ ...o, photos: o.photos.filter((p) => p.id !== photo.id) }))
  }

  const saveSignature = async () => {
    if (!signer.trim()) { fail('signer_required'); return }
    setBusy('sign')
    const r = await api('PUT', `/api/work-orders/${id}/signature`, { png: padRef.current.toDataURL(), signedBy: signer.trim() })
    setBusy(null)
    if (!r.ok) { fail(r.code); return }
    setOrder(r.data)
    setMessage({ kind: 'ok', text: t('wo.signedMsg') })
  }

  const clearSignature = async () => {
    if (!window.confirm(t('wo.confirmClearSignature'))) return
    const r = await api('DELETE', `/api/work-orders/${id}/signature`)
    if (!r.ok) { fail(r.code); return }
    setOrder(r.data)
  }

  const actions = isNew ? [] : nextActions(status)
  const primary = actions.find((a) => a.primary)
  const secondary = actions.filter((a) => !a.primary)
  const pdfUrl = `/api/work-orders/${id}/pdf`

  const actionButton = (a, className) =>
    a.id === 'pdf' ? (
      <a key={a.id} className={className} href={pdfUrl}>{t('wo.action.pdf')}</a>
    ) : (
      <button key={a.id} type="button" className={className} disabled={busy !== null} onClick={() => run(a)}>
        {busy && busy === (a.to ?? a.id) ? t('wo.working') : t(`wo.action.${a.id}`)}
      </button>
    )

  return (
    <div className="wo-page">
      <Link to="/partes" className="link-btn wo-back">← {t('wo.back')}</Link>
      <div className="wo-head">
        <h1 className="page-title">{isNew ? t('wo.newTitle') : order.title}</h1>
        {!isNew && <span className={`wo-badge wo-badge-${status}`}>{t(`wo.status.${status}`)}</span>}
      </div>

      {order?.convertedInvoice && (
        <div className="card wo-invoiced">
          ✓ {t('wo.invoicedIn', { n: order.convertedInvoice.number })}{' '}
          <Link to="/invoices" className="link-btn">{t('wo.action.viewInvoice')}</Link>
        </div>
      )}
      {!isNew && !editable && !order?.convertedInvoice && <p className="msg">{t('wo.lockedHint')}</p>}

      <fieldset className="wo-fieldset" disabled={!editable || busy !== null}>
        {/* ── Client and job ── */}
        <section className="card wo-section">
          <h2>{t('wo.sec.job')}</h2>
          <label className="field"><span>{t('wo.customer')}</span>
            <select className="bank-select" value={form.customerId} onChange={(e) => update({ customerId: e.target.value })}>
              {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              <option value={NEW_CUSTOMER}>{t('wo.newCustomer')}</option>
            </select>
          </label>
          {form.customerId === NEW_CUSTOMER && (
            <div className="field-row">
              <label className="field"><span>{t('wo.customerName')}</span>
                <input value={form.customer.name} autoComplete="off"
                  onChange={(e) => update({ customer: { ...form.customer, name: e.target.value } })} /></label>
              <label className="field field-sm"><span>{t('wo.customerTaxId')}</span>
                <input value={form.customer.taxId} autoComplete="off"
                  onChange={(e) => update({ customer: { ...form.customer, taxId: e.target.value } })} /></label>
            </div>
          )}
          <label className="field"><span>{t('wo.jobTitle')}</span>
            <input value={form.title} placeholder={t('wo.jobTitlePh')} onChange={(e) => update({ title: e.target.value })} /></label>
          <label className="field"><span>{t('wo.description')}</span>
            <textarea className="wo-textarea" rows={3} value={form.description} placeholder={t('wo.descriptionPh')}
              onChange={(e) => update({ description: e.target.value })} /></label>
          <label className="field"><span>{t('wo.when')}</span>
            <input type="datetime-local" value={form.scheduledAt} onChange={(e) => update({ scheduledAt: e.target.value })} /></label>
        </section>

        {/* ── Materials ── */}
        <section className="card wo-section">
          <h2>{t('wo.sec.materials')}</h2>
          {form.lines.length === 0 && <p className="muted">{t('wo.noMaterials')}</p>}
          {form.lines.map((l, i) => (
            <div key={i} className="wo-line">
              <input className="wo-line-desc" value={l.description} placeholder={t('wo.materialPh')}
                aria-label={t('wo.material')} onChange={(e) => setLine(i, { description: e.target.value })} />
              <div className="wo-line-row">
                <div className="wo-stepper" aria-label={t('wo.quantity')}>
                  <button type="button" aria-label={t('wo.less')} onClick={() => setLine(i, { quantity: Math.max(1, l.quantity - 1) })}>−</button>
                  <span className="num">{l.quantity}</span>
                  <button type="button" aria-label={t('wo.more')} onClick={() => setLine(i, { quantity: l.quantity + 1 })}>+</button>
                </div>
                <label className="wo-inline"><span>{t('wo.price')}</span>
                  <input inputMode="decimal" className="num-input" value={l.unitPrice} placeholder="0,00"
                    onChange={(e) => setLine(i, { unitPrice: e.target.value })} /></label>
                <label className="wo-inline"><span>{t('wo.vat')}</span>
                  <select className="bank-select" value={l.vatRate} onChange={(e) => setLine(i, { vatRate: e.target.value })}>
                    {VAT_RATES.map((v) => <option key={v} value={v}>{v} %</option>)}
                  </select></label>
                <button type="button" className="icon-btn btn btn-glass btn-sm" aria-label={t('wo.removeMaterial')} onClick={() => removeLine(i)}>✕</button>
              </div>
            </div>
          ))}
          <div className="wo-add-row">
            <select className="bank-select" value="" aria-label={t('wo.fromCatalog')} onChange={(e) => addFromCatalog(e.target.value)}>
              <option value="">{services.length ? t('wo.fromCatalog') : t('wo.catalogEmpty')}</option>
              {services.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
            <button type="button" className="btn btn-glass btn-sm" onClick={() => addLine(emptyLine())}>{t('wo.otherMaterial')}</button>
          </div>
        </section>

        {/* ── Labour ── */}
        <section className="card wo-section">
          <h2>{t('wo.sec.labor')}</h2>
          <div className="wo-line-row">
            <div className="wo-stepper wo-hours" aria-label={t('wo.hours')}>
              <button type="button" aria-label={t('wo.lessTime')} onClick={() => update({ laborHours: plain(stepHours(form.laborHours, -0.25)) })}>−</button>
              <input inputMode="decimal" className="num-input" value={form.laborHours}
                aria-label={t('wo.hours')} onChange={(e) => update({ laborHours: e.target.value })} />
              <button type="button" aria-label={t('wo.moreTime')} onClick={() => update({ laborHours: plain(stepHours(form.laborHours, 0.25)) })}>+</button>
            </div>
            <span className="muted">{t('wo.hoursUnit')}</span>
            <label className="wo-inline"><span>{t('wo.rate')}</span>
              <input inputMode="decimal" className="num-input" value={form.laborRate} placeholder="0,00"
                onChange={(e) => update({ laborRate: e.target.value })} /></label>
            <label className="wo-inline"><span>{t('wo.vat')}</span>
              <select className="bank-select" value={form.laborVatRate} onChange={(e) => update({ laborVatRate: e.target.value })}>
                {VAT_RATES.map((v) => <option key={v} value={v}>{v} %</option>)}
              </select></label>
          </div>
          {isNew && !form.laborRate && <p className="muted">{t('wo.rateHint')}</p>}
        </section>
      </fieldset>

      {/* ── Totals ── */}
      <section className="card wo-section wo-totals">
        <div><span className="muted">{t('wo.base')}</span><span className="num">{formatCents(sums.base)}</span></div>
        <div><span className="muted">{t('wo.vatTotal')}</span><span className="num">{formatCents(sums.vat)}</span></div>
        <div className="wo-grand"><span>{t('wo.total')}</span><span className="num">{formatCents(sums.total)}</span></div>
      </section>

      {isNew && <p className="muted">{t('wo.saveFirstHint')}</p>}

      {/* ── Photos ── */}
      {!isNew && (
        <section className="card wo-section">
          <h2>{t('wo.sec.photos')} <span className="muted">({order.photos.length}/{order.maxPhotos})</span></h2>
          {order.photos.length === 0 && <p className="muted">{t('wo.noPhotos')}</p>}
          <div className="wo-photos">
            {order.photos.map((p) => (
              <figure key={p.id} className="wo-photo">
                <a href={p.url} target="_blank" rel="noreferrer"><img src={p.url} alt={t('wo.photoAlt')} loading="lazy" /></a>
                {editable && <button type="button" className="wo-photo-del" aria-label={t('wo.deletePhoto')} onClick={() => deletePhoto(p)}>✕</button>}
              </figure>
            ))}
          </div>
          {editable && order.photos.length < order.maxPhotos && (
            <label className={`btn btn-glass wo-camera${busy ? ' is-disabled' : ''}`}>
              {busy === 'photo' ? t('wo.uploading') : t('wo.takePhoto')}
              <input ref={photoInput} type="file" accept="image/*" capture="environment" className="file-input-hidden"
                disabled={busy !== null} onChange={(e) => addPhotos([...e.target.files])} />
            </label>
          )}
        </section>
      )}

      {/* ── Signature ── */}
      {!isNew && (
        <section className="card wo-section" ref={signatureRef}>
          <h2>{t('wo.sec.signature')}</h2>
          {order.signature.signed ? (
            <>
              <img className="wo-signature" src={`${order.signature.url}?v=${encodeURIComponent(order.signature.signedAt)}`} alt={t('wo.signatureAlt')} />
              <p className="muted">{t('wo.signedBy', { name: order.signature.signedBy, at: order.signature.signedAt })}</p>
              {editable && <button type="button" className="btn btn-glass btn-sm" onClick={clearSignature}>{t('wo.clearSignature')}</button>}
            </>
          ) : order.signature.waived ? (
            <p className="muted">{t('wo.signatureWaived')}</p>
          ) : editable ? (
            <>
              <p className="muted">{t('wo.signHint')}</p>
              <SignaturePad ref={padRef} label={t('wo.signatureAlt')} onChange={setPadEmpty} />
              <div className="wo-add-row">
                <label className="field"><span>{t('wo.signer')}</span>
                  <input value={signer} onChange={(e) => setSigner(e.target.value)} /></label>
              </div>
              <div className="wo-add-row">
                <button type="button" className="btn btn-glass btn-sm" onClick={() => padRef.current?.clear()}>{t('wo.clearPad')}</button>
                <button type="button" className="btn btn-primary btn-sm" disabled={padEmpty || busy !== null} onClick={saveSignature}>
                  {busy === 'sign' ? t('wo.working') : t('wo.saveSignature')}
                </button>
              </div>
            </>
          ) : (
            <p className="muted">{t('wo.notSigned')}</p>
          )}
        </section>
      )}

      {message && <p className={`msg${message.kind === 'error' ? ' error' : ''}`} role="status">{message.text}</p>}

      {/* ── Fixed action bar: the one thing to do next ── */}
      <div className="wo-actionbar">
        {secondary.length > 0 && <div className="wo-secondary">{secondary.map((a) => actionButton(a, `btn btn-glass btn-sm${a.danger ? ' btn-danger-glass' : ''}`))}</div>}
        {(isNew || (editable && dirty)) ? (
          <button type="button" className="btn btn-primary wo-main" disabled={busy !== null} onClick={save}>
            {busy === 'save' ? t('wo.working') : t(isNew ? 'wo.create' : 'wo.saveChanges')}
          </button>
        ) : primary && actionButton(primary, 'btn btn-primary wo-main')}
      </div>
    </div>
  )
}
