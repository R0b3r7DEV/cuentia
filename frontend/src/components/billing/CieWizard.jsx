import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from '../../i18n/LanguageContext'
import { MULTI, STEPS, applyBill, canSave, mergeEmpty, missingInStep, missingRequired, payload } from '../../lib/cie'
import { downscalePhoto } from '../../lib/photo'

const NUMERIC = ['maxPower', 'installedPower', 'voltage', 'circuits']
const TEXTAREA = ['useSpecifications', 'observations']
const DATE = ['designDate']

/** POST/PUT JSON or FormData; returns {ok, data} or {ok:false, code} — never throws. */
async function send(method, url, body) {
  try {
    const isForm = body instanceof FormData
    const r = await fetch(url, {
      method,
      headers: isForm ? undefined : { 'Content-Type': 'application/json' },
      body: isForm ? body : JSON.stringify(body),
    })
    const data = await r.json().catch(() => null)
    return r.ok ? { ok: true, data } : { ok: false, code: data?.error ?? 'generic' }
  } catch {
    return { ok: false, code: 'network' }
  }
}

/**
 * The CIE, step by step, in the order of the GVA's CERTINS E (A–G) plus the data for the Technical Design
 * Report: a fixed guide on every step, and three Claude helpers — read the electricity bill, "¿Qué pongo
 * aquí?", and a final review. What Claude fills is marked ✨ until touched; what a rule can check (CUPS, NIF)
 * is flagged ⚠️. Compliance is never Claude's call. Used both to create and to edit a certificate.
 *
 * ES: El CIE paso a paso, en el orden del CERTINS E de la GVA (A–G) más los datos de la MTD. Sirve para crear y
 * para editar un certificado.
 */
export default function CieWizard({ initial, onSaved, onCancel }) {
  const { t } = useTranslation()
  const editing = Boolean(initial.form.id)
  const [step, setStep] = useState(editing || initial?.fromInstallation ? 1 : 0)
  const [form, setForm] = useState(initial.form)
  const [aiFields, setAiFields] = useState([])
  const [checks, setChecks] = useState([])
  const [contractedPower, setContractedPower] = useState(null)
  const [model, setModel] = useState(null) // the CERTINS E lists, with the model's labels
  const [assistant, setAssistant] = useState(null) // {enabled}
  const [busy, setBusy] = useState(null)
  const [error, setError] = useState(null)
  const [question, setQuestion] = useState('')
  const [answer, setAnswer] = useState(null)
  const [review, setReview] = useState(null)
  const [saved, setSaved] = useState(null)
  const billInput = useRef(null)

  // Late defaults (e.g. "Mis datos" arriving after opening) only fill what is still empty.
  useEffect(() => { setForm((f) => mergeEmpty(f, initial.form).form) }, [initial.form])

  useEffect(() => {
    fetch('/api/certificates/assistant/status').then((r) => (r.ok ? r.json() : null)).then(setAssistant).catch(() => setAssistant(null))
    fetch('/api/certificates/model').then((r) => (r.ok ? r.json() : null)).then(setModel).catch(() => setModel(null))
  }, [])

  const current = STEPS[step]
  const errorText = (code) => {
    const key = `wiz.err.${code}`
    return t(key) === key ? t('wiz.err.generic') : t(key)
  }

  const set = (k, v) => {
    setForm((f) => ({ ...f, [k]: v }))
    setAiFields((a) => a.filter((x) => x !== k)) // touched = reviewed
    setChecks((c) => c.filter((x) => x !== k))
    setReview(null)
  }
  const toggle = (k, key) => set(k, form[k].includes(key) ? form[k].filter((x) => x !== key) : [...form[k], key])

  const go = (to) => { setStep(to); setAnswer(null); setQuestion(''); setError(null); window.scrollTo?.(0, 0) }

  const readBill = async (file) => {
    if (!file) return
    setBusy('bill'); setError(null)
    let upload = file
    if (file.type !== 'application/pdf') {
      try { upload = await downscalePhoto(file) } catch { setBusy(null); setError(errorText('invalid_image')); return }
    }
    const fd = new FormData()
    fd.append('bill', upload, file.type === 'application/pdf' ? 'factura.pdf' : 'factura.jpg')
    const r = await send('POST', '/api/certificates/assistant/bill', fd)
    setBusy(null)
    if (billInput.current) billInput.current.value = ''
    if (!r.ok) { setError(errorText(r.code)); return }
    const merged = applyBill(form, r.data)
    setForm(merged.form)
    setAiFields((a) => [...new Set([...a, ...merged.aiFields])])
    setChecks(merged.checks)
    setContractedPower(merged.contractedPowerKw)
    if (merged.aiFields.length === 0) setError(t('wiz.billNothing'))
  }

  const ask = async (e) => {
    e.preventDefault()
    if (!question.trim()) return
    setBusy('help'); setError(null); setAnswer(null)
    const r = await send('POST', '/api/certificates/assistant/help', { step: current.id, question, form })
    setBusy(null)
    if (!r.ok) { setError(errorText(r.code)); return }
    setAnswer(r.data.answer)
  }

  const runReview = async () => {
    setBusy('review'); setError(null)
    const r = await send('POST', '/api/certificates/assistant/review', { form })
    setBusy(null)
    if (!r.ok) { setError(errorText(r.code)); return }
    setReview(r.data)
  }

  const save = async () => {
    setBusy('save'); setError(null)
    const r = editing
      ? await send('PUT', `/api/certificates/${form.id}`, payload(form))
      : await send('POST', '/api/certificates', payload(form))
    setBusy(null)
    if (!r.ok) { setError(errorText(r.code)); return }
    setSaved(r.data)
    setForm((f) => ({ ...f, id: r.data.id }))
    onSaved?.(r.data)
  }

  const options = (k) => ({
    installationType: Object.fromEntries(['nueva', 'ampliacion', 'reforma'].map((v) => [v, t('cie.type.' + v)])),
    supplyType: Object.fromEntries(['monofasico', 'trifasico'].map((v) => [v, t('cie.supply.' + v)])),
    supplySource: model?.sources,
    useType: model?.uses,
    designBasis: model?.designBasis,
    specificRisks: model?.risks,
    secondaryUses: model?.uses && Object.fromEntries(Object.entries(model.uses).filter(([key]) => key !== form.useType)),
  }[k])

  const label = (k) => (
    <span>
      {t('cie.' + k)}
      {aiFields.includes(k) && <em className="wiz-ai" title={t('wiz.aiTitle')}> ✨ {t('wiz.aiTag')}</em>}
    </span>
  )
  const hint = (k) => (t(`wiz.hint.${k}`) !== `wiz.hint.${k}` ? <small className="muted">{t(`wiz.hint.${k}`)}</small> : null)

  const fieldFor = (k) => {
    if (MULTI.includes(k)) {
      const opts = options(k) ?? {}
      return (
        <fieldset key={k} className="field wiz-multi">
          <legend>{label(k)}</legend>
          {Object.entries(opts).map(([key, text]) => (
            <label key={key} className="wiz-check-item">
              <input type="checkbox" checked={form[k].includes(key)} onChange={() => toggle(k, key)} /> {text}
            </label>
          ))}
          {hint(k)}
        </fieldset>
      )
    }
    const opts = options(k)
    return (
      <label key={k} className={`field${checks.includes(k) ? ' wiz-check' : ''}`}>
        {label(k)}
        {opts ? (
          <select className="bank-select" value={form[k] ?? ''} onChange={(e) => set(k, e.target.value)}>
            {k === 'supplyType' && <option value="">—</option>}
            {Object.entries(opts).map(([key, text]) => <option key={key} value={key}>{text}</option>)}
          </select>
        ) : TEXTAREA.includes(k) ? (
          <textarea className="wo-textarea" rows={3} value={form[k] ?? ''} onChange={(e) => set(k, e.target.value)} />
        ) : (
          <input type={DATE.includes(k) ? 'date' : 'text'} value={form[k] ?? ''} inputMode={NUMERIC.includes(k) ? 'decimal' : undefined}
            onChange={(e) => set(k, e.target.value)} aria-invalid={checks.includes(k) || undefined} />
        )}
        {checks.includes(k) && <small className="wiz-check-msg">⚠️ {t('wiz.checkFailed')}</small>}
        {hint(k)}
      </label>
    )
  }

  // The distributor and CUPS only exist with the distribution network as the source.
  const visibleFields = current.fields.filter((k) => !(form.supplySource === 'generacion_aislada' && ['distributor', 'cups'].includes(k)))
  const missingHere = missingInStep(form, current.id)
  const missingAll = missingRequired(form)
  const claudeOn = assistant?.enabled
  const savedId = saved?.id ?? form.id

  return (
    <div className="wiz">
      <div className="wiz-head">
        <strong>{t('wiz.stepOf', { n: step + 1, total: STEPS.length })} · {t(`wiz.step.${current.id}`)}</strong>
        <div className="wiz-dots" aria-hidden="true">
          {STEPS.map((s, i) => <span key={s.id} className={i < step ? 'done' : i === step ? 'on' : ''} />)}
        </div>
      </div>
      {current.section && <p className="wiz-section">{t('wiz.sectionOf', { s: current.section })}</p>}

      <p className="wiz-guide">🧭 {t(`wiz.guide.${current.id}`)}</p>

      {current.id === 'start' && (
        <div className="wiz-choices">
          <Link to="/installation" className="card wiz-choice">
            <strong>⚡ {t('wiz.fromInstallation')}</strong>
            <span className="muted">{t('wiz.fromInstallationDesc')}</span>
          </Link>
          <button type="button" className="card wiz-choice" onClick={() => go(1)}>
            <strong>📝 {t('wiz.fromScratch')}</strong>
            <span className="muted">{t('wiz.fromScratchDesc')}</span>
          </button>
        </div>
      )}

      {current.id === 'client' && (
        <div className="wiz-bill">
          <label className={`btn ${claudeOn ? 'btn-primary' : 'btn-glass'}${!claudeOn || busy ? ' is-disabled' : ''}`}>
            {busy === 'bill' ? t('wiz.reading') : t('wiz.readBill')}
            <input ref={billInput} type="file" accept="image/*,application/pdf" capture="environment" className="file-input-hidden"
              disabled={!claudeOn || busy !== null} onChange={(e) => readBill(e.target.files?.[0])} />
          </label>
          <small className="muted">{claudeOn ? t('wiz.billPrivacy') : t('wiz.noKey')}</small>
          {!claudeOn && <Link to="/account" className="link-btn">{t('wiz.addKey')}</Link>}
        </div>
      )}

      {visibleFields.length > 0 && <div className="wiz-fields">{visibleFields.map(fieldFor)}</div>}

      {current.id === 'installation' && contractedPower && (
        <div className="wiz-power">
          {t('wiz.contractedPower', { kw: String(Number(contractedPower)).replace('.', ',') })}
          <small className="muted"> {t('wiz.powerNote')}</small>
        </div>
      )}

      {missingHere.length > 0 && (
        <p className="msg">{t('wiz.missingHere', { fields: missingHere.map((k) => t('cie.' + k)).join(', ') })}</p>
      )}

      {current.id === 'review' && (
        <div className="wiz-review">
          {missingAll.length > 0 ? (
            <p className="alert-warn">{t('wiz.stillMissing', { fields: missingAll.map((k) => t('cie.' + k)).join(', ') })}</p>
          ) : (
            <p className="chain-badge chain-ok">✓ {t('wiz.allRequired')}</p>
          )}
          <button type="button" className="btn btn-glass" disabled={!claudeOn || busy !== null} onClick={runReview}>
            {busy === 'review' ? t('wiz.reviewing') : t('wiz.review')}
          </button>
          {review && (
            <div className="card wiz-review-box">
              <p>{review.summary}</p>
              <ul>
                {review.items.map((it, i) => (
                  <li key={i} className={`wiz-item wiz-${it.severity}`}>
                    {it.severity === 'warning' ? '⚠️' : 'ℹ️'} {it.field && <strong>{t('cie.' + it.field)}: </strong>}{it.message}
                  </li>
                ))}
              </ul>
              <small className="muted">{t('wiz.reviewDisclaimer')}</small>
            </div>
          )}
          {saved && (
            <div className="card wiz-done">
              <p>✓ {t('wiz.saved')}</p>
              <p className="wo-add-row" style={{ marginTop: 0 }}>
                <Link className="btn btn-primary" to={`/certificates/${savedId}/gva`}>🏛️ {t('gva.open')}</Link>
                <a className="btn btn-glass" href={`/api/certificates/${savedId}/pdf`}>{t('cie.download')}</a>
              </p>
              <p className="muted">{t('wiz.nextSteps')}</p>
              <ol className="cie-steps">
                <li>{t('cie.step2')}</li>
                <li>{t('cie.step3')}</li>
              </ol>
            </div>
          )}
        </div>
      )}

      {current.id !== 'start' && current.id !== 'review' && (
        <form className="wiz-ask" onSubmit={ask}>
          <input value={question} onChange={(e) => setQuestion(e.target.value)} maxLength={500}
            placeholder={claudeOn ? t('wiz.askPlaceholder') : t('wiz.noKeyShort')} disabled={!claudeOn || busy !== null} />
          <button type="submit" className="btn btn-glass btn-sm" disabled={!claudeOn || busy !== null || !question.trim()}>
            {busy === 'help' ? t('wiz.thinking') : t('wiz.ask')}
          </button>
        </form>
      )}
      {answer && <div className="card wiz-answer">💬 {answer}<br /><small className="muted">{t('wiz.answerDisclaimer')}</small></div>}

      {error && <p className="msg error" role="alert">{error}</p>}

      <div className="wiz-nav">
        {step > 0
          ? <button type="button" className="btn btn-glass" onClick={() => go(step - 1)} disabled={busy !== null}>← {t('wiz.back')}</button>
          : <button type="button" className="btn btn-glass" onClick={onCancel}>{t('cie.cancel')}</button>}
        {step > 0 && step < STEPS.length - 1 && (
          <button type="button" className="btn btn-primary" onClick={() => go(step + 1)} disabled={busy !== null}>{t('wiz.next')} →</button>
        )}
        {current.id === 'review' && !saved && (
          <button type="button" className="btn btn-primary" onClick={save} disabled={busy !== null || !canSave(form)}>
            {busy === 'save' ? t('cie.saving') : t(editing ? 'wiz.saveChanges' : 'wiz.saveCie')}
          </button>
        )}
        {saved && <button type="button" className="btn btn-glass" onClick={onCancel}>{t('wiz.close')}</button>}
      </div>
    </div>
  )
}
