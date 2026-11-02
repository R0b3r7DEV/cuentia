import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from '../../i18n/LanguageContext'
import { STEPS, applyBill, mergeEmpty, missingInStep, missingRequired } from '../../lib/cie'
import { downscalePhoto } from '../../lib/photo'

const SELECTS = {
  installationType: ['nueva', 'ampliacion', 'reforma'],
  useType: ['vivienda', 'local', 'industrial', 'garaje', 'comunes', 'agricola', 'otros'],
  supplyType: ['monofasico', 'trifasico'],
}
const OPTION_KEY = { installationType: 'cie.type.', useType: 'cie.use.', supplyType: 'cie.supply.' }
const NUMERIC = ['maxPower', 'installedPower', 'voltage', 'circuits']

/** POST JSON or FormData; returns {ok, data} or {ok:false, code} — never throws. */
async function post(url, body) {
  try {
    const isForm = body instanceof FormData
    const r = await fetch(url, {
      method: 'POST',
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
 * The CIE, step by step: a fixed guide on every step (always there, no cost), and three Claude helpers —
 * read the electricity bill, "¿Qué pongo aquí?", and a final review. What Claude fills is marked ✨ until the
 * electrician touches it; what a rule can check (CUPS, NIF) is flagged ⚠️. Compliance is never Claude's call.
 *
 * ES: El CIE paso a paso: una guía fija en cada paso y tres ayudas de Claude. Lo que rellena Claude se marca ✨
 * hasta que el electricista lo toca; lo que una regla puede comprobar (CUPS, NIF) se marca ⚠️.
 */
export default function CieWizard({ initial, onSaved, onCancel }) {
  const { t } = useTranslation()
  const [step, setStep] = useState(initial?.fromInstallation ? 1 : 0)
  const [form, setForm] = useState(initial.form)
  const [aiFields, setAiFields] = useState([])
  const [checks, setChecks] = useState([])
  const [contractedPower, setContractedPower] = useState(null)
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

  const go = (to) => { setStep(to); setAnswer(null); setQuestion(''); setError(null) }

  const readBill = async (file) => {
    if (!file) return
    setBusy('bill'); setError(null)
    let upload = file
    if (file.type !== 'application/pdf') {
      try { upload = await downscalePhoto(file) } catch { setBusy(null); setError(errorText('invalid_image')); return }
    }
    const fd = new FormData()
    fd.append('bill', upload, file.type === 'application/pdf' ? 'factura.pdf' : 'factura.jpg')
    const r = await post('/api/certificates/assistant/bill', fd)
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
    const r = await post('/api/certificates/assistant/help', { step: current.id, question, form })
    setBusy(null)
    if (!r.ok) { setError(errorText(r.code)); return }
    setAnswer(r.data.answer)
  }

  const runReview = async () => {
    setBusy('review'); setError(null)
    const r = await post('/api/certificates/assistant/review', { form })
    setBusy(null)
    if (!r.ok) { setError(errorText(r.code)); return }
    setReview(r.data)
  }

  const save = async () => {
    setBusy('save'); setError(null)
    const { id: _id, ...body } = form
    const r = await post('/api/certificates', body)
    setBusy(null)
    if (!r.ok) { setError(errorText(r.code)); return }
    setSaved(r.data)
    onSaved?.(r.data)
  }

  const label = (k) => (
    <span>
      {t('cie.' + k)}
      {aiFields.includes(k) && <em className="wiz-ai" title={t('wiz.aiTitle')}> ✨ {t('wiz.aiTag')}</em>}
    </span>
  )

  const fieldFor = (k) => (
    <label key={k} className={`field${checks.includes(k) ? ' wiz-check' : ''}`}>
      {label(k)}
      {SELECTS[k] ? (
        <select className="bank-select" value={form[k] ?? ''} onChange={(e) => set(k, e.target.value)}>
          {k === 'supplyType' && <option value="">—</option>}
          {SELECTS[k].map((v) => <option key={v} value={v}>{t(OPTION_KEY[k] + v)}</option>)}
        </select>
      ) : k === 'observations' ? (
        <textarea className="wo-textarea" rows={3} value={form[k] ?? ''} onChange={(e) => set(k, e.target.value)} />
      ) : (
        <input value={form[k] ?? ''} inputMode={NUMERIC.includes(k) ? 'decimal' : undefined}
          onChange={(e) => set(k, e.target.value)} aria-invalid={checks.includes(k) || undefined} />
      )}
      {checks.includes(k) && <small className="wiz-check-msg">⚠️ {t('wiz.checkFailed')}</small>}
      {t(`wiz.hint.${k}`) !== `wiz.hint.${k}` && <small className="muted">{t(`wiz.hint.${k}`)}</small>}
    </label>
  )

  const missingHere = missingInStep(form, current.id)
  const claudeOn = assistant?.enabled

  return (
    <div className="wiz">
      <div className="wiz-head">
        <strong>{t('wiz.stepOf', { n: step + 1, total: STEPS.length })} · {t(`wiz.step.${current.id}`)}</strong>
        <div className="wiz-dots" aria-hidden="true">
          {STEPS.map((s, i) => <span key={s.id} className={i < step ? 'done' : i === step ? 'on' : ''} />)}
        </div>
      </div>

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

      {current.fields.length > 0 && (
        <div className="wiz-fields">{current.fields.map(fieldFor)}</div>
      )}

      {current.id === 'installation' && contractedPower && (
        <div className="wiz-power">
          {t('wiz.contractedPower', { kw: String(Number(contractedPower)).replace('.', ',') })}{' '}
          {!form.maxPower && (
            <button type="button" className="link-btn" onClick={() => set('maxPower', String(Number(contractedPower)))}>{t('wiz.usePower')}</button>
          )}
          <small className="muted"> {t('wiz.powerNote')}</small>
        </div>
      )}

      {missingHere.length > 0 && (
        <p className="msg">{t('wiz.missingHere', { fields: missingHere.map((k) => t('cie.' + k)).join(', ') })}</p>
      )}

      {current.id === 'review' && (
        <div className="wiz-review">
          {missingRequired(form).length > 0 ? (
            <p className="alert-warn">{t('wiz.stillMissing', { fields: missingRequired(form).map((k) => t('cie.' + k)).join(', ') })}</p>
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
          {saved ? (
            <div className="card wiz-done">
              <p>✓ {t('wiz.saved')}</p>
              <p><a className="btn btn-primary" href={`/api/certificates/${saved.id}/pdf`}>{t('cie.download')}</a></p>
              <p className="muted">{t('wiz.nextSteps')}</p>
              <ol className="cie-steps">
                <li>{t('cie.step2')}</li>
                <li>{t('cie.step3')}</li>
              </ol>
            </div>
          ) : null}
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
          <button type="button" className="btn btn-primary" onClick={save} disabled={busy !== null || !['address', 'titularName', 'companyName'].every((k) => String(form[k] ?? '').trim())}>
            {busy === 'save' ? t('cie.saving') : t('wiz.saveCie')}
          </button>
        )}
        {saved && <button type="button" className="btn btn-glass" onClick={onCancel}>{t('wiz.close')}</button>}
      </div>
    </div>
  )
}
