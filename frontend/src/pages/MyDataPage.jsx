import { useEffect, useRef, useState } from 'react'
import { useTranslation } from '../i18n/LanguageContext'
import { isValidIban, missingFor } from '../lib/profile'

const FIELDS = ['businessName', 'taxId', 'fiscalAddress', 'installerRegNumber', 'phone', 'iban']

/**
 * "Mis datos": the user's own data, filled in once and printed on every invoice, quote, work order and CIE.
 * Kept to what a self-employed electrician actually needs, plus the logo.
 * ES: «Mis datos»: los datos propios, que se rellenan una vez y salen en facturas, presupuestos, partes y
 * CIE. Solo lo que necesita un autónomo, más el logo.
 */
export default function MyDataPage() {
  const { t } = useTranslation()
  const [form, setForm] = useState(null)
  const [logoUrl, setLogoUrl] = useState(null)
  const [logoVersion, setLogoVersion] = useState(0)
  const [busy, setBusy] = useState(null)
  const [message, setMessage] = useState(null) // { kind, text }
  const logoInput = useRef(null)

  const applySettings = (d) => {
    setForm(Object.fromEntries(FIELDS.map((k) => [k, d[k] ?? ''])))
    setLogoUrl(d.logoUrl)
  }

  useEffect(() => {
    fetch('/api/account/settings').then((r) => (r.ok ? r.json() : null)).then((d) => d && applySettings(d))
  }, [])

  if (!form) return <p className="muted">{t('wo.loading')}</p>

  const set = (k, v) => { setForm((f) => ({ ...f, [k]: v })); setMessage(null) }
  const ibanBad = form.iban.trim() !== '' && !isValidIban(form.iban)
  const missingInvoice = missingFor(form, 'invoice')
  const missingCie = missingFor(form, 'certificate')

  const save = async (e) => {
    e.preventDefault()
    if (ibanBad) { setMessage({ kind: 'error', text: t('my.ibanInvalid') }); return }
    setBusy('save')
    const r = await fetch('/api/account/settings', {
      method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form),
    }).catch(() => null)
    setBusy(null)
    if (!r?.ok) {
      const code = (await r?.json().catch(() => null))?.error
      setMessage({ kind: 'error', text: code === 'invalid_iban' ? t('my.ibanInvalid') : t('wo.err.generic') })
      return
    }
    applySettings(await r.json())
    setMessage({ kind: 'ok', text: t('my.saved') })
  }

  const uploadLogo = async (file) => {
    if (!file) return
    setBusy('logo'); setMessage(null)
    const fd = new FormData()
    fd.append('logo', file)
    const r = await fetch('/api/account/logo', { method: 'POST', body: fd }).catch(() => null)
    setBusy(null)
    if (logoInput.current) logoInput.current.value = ''
    if (!r?.ok) {
      const code = (await r?.json().catch(() => null))?.error
      setMessage({ kind: 'error', text: code === 'image_too_large' ? t('my.logoTooLarge') : t('my.logoInvalid') })
      return
    }
    setLogoUrl((await r.json()).logoUrl)
    setLogoVersion((v) => v + 1)
    setMessage({ kind: 'ok', text: t('my.logoSaved') })
  }

  const removeLogo = async () => {
    if (!window.confirm(t('my.confirmRemoveLogo'))) return
    const r = await fetch('/api/account/logo', { method: 'DELETE' }).catch(() => null)
    if (r?.ok) setLogoUrl(null)
  }

  const status = (missing) => (missing.length === 0
    ? <span className="chain-badge chain-ok">✓ {t('my.ready')}</span>
    : <span className="chain-badge chain-broken">{t('my.missing', { fields: missing.map((k) => t(`my.f.${k}`)).join(', ') })}</span>)

  const field = (k, opts = {}) => (
    <label className={`field${opts.sm ? ' field-sm' : ''}`}>
      <span>{t(`my.f.${k}`)}{opts.optional && <em className="muted"> · {t('my.optional')}</em>}</span>
      <input value={form[k]} onChange={(e) => set(k, e.target.value)} placeholder={opts.placeholder}
        inputMode={opts.inputMode} autoComplete={opts.autoComplete} aria-invalid={opts.invalid || undefined} />
      {opts.hint && <small className="muted">{opts.hint}</small>}
    </label>
  )

  return (
    <>
      <h1 className="page-title">{t('my.title')}</h1>
      <p className="page-subtitle">{t('my.subtitle')}</p>

      <form onSubmit={save}>
        <section className="card wo-section">
          <h2>{t('my.secFiscal')} {status(missingInvoice)}</h2>
          <p className="msg" style={{ marginTop: 0 }}>{t('my.secFiscalDesc')}</p>
          {field('businessName', { placeholder: t('my.ph.businessName'), autoComplete: 'name' })}
          <div className="field-row">
            {field('taxId', { sm: true, placeholder: '12345678Z' })}
            {field('fiscalAddress', { placeholder: t('my.ph.fiscalAddress'), autoComplete: 'street-address' })}
          </div>
        </section>

        <section className="card wo-section">
          <h2>{t('my.secInstaller')} {status(missingCie)}</h2>
          <p className="msg" style={{ marginTop: 0 }}>{t('my.secInstallerDesc')}</p>
          {field('installerRegNumber', { sm: true, hint: t('my.regHint') })}
        </section>

        <section className="card wo-section">
          <h2>{t('my.secContact')}</h2>
          <div className="field-row">
            {field('phone', { sm: true, optional: true, inputMode: 'tel', autoComplete: 'tel' })}
            {field('iban', { optional: true, placeholder: 'ES00 0000 0000 0000 0000 0000', invalid: ibanBad,
              hint: ibanBad ? t('my.ibanInvalid') : t('my.ibanHint') })}
          </div>
        </section>

        <div className="wo-add-row">
          <button className="btn btn-primary" type="submit" disabled={busy !== null || ibanBad}>
            {busy === 'save' ? t('wo.working') : t('my.save')}
          </button>
        </div>
      </form>

      <section className="card wo-section" style={{ marginTop: '1.25rem' }}>
        <h2>{t('my.secLogo')} <em className="muted" style={{ fontSize: 13, fontWeight: 400 }}>· {t('my.optional')}</em></h2>
        <p className="msg" style={{ marginTop: 0 }}>{t('my.secLogoDesc')}</p>
        {logoUrl && (
          <div className="my-logo-preview">
            <img src={`${logoUrl}?v=${logoVersion}`} alt={t('my.logoAlt')} />
          </div>
        )}
        <div className="wo-add-row">
          <label className={`btn btn-glass${busy ? ' is-disabled' : ''}`}>
            {busy === 'logo' ? t('wo.working') : logoUrl ? t('my.changeLogo') : t('my.uploadLogo')}
            <input ref={logoInput} type="file" accept="image/png,image/jpeg" className="file-input-hidden"
              disabled={busy !== null} onChange={(e) => uploadLogo(e.target.files?.[0])} />
          </label>
          {logoUrl && <button type="button" className="btn btn-glass btn-sm btn-danger-glass" onClick={removeLogo}>{t('my.removeLogo')}</button>}
        </div>
      </section>

      {message && <p className={`msg${message.kind === 'error' ? ' error' : ''}`} role="status">{message.text}</p>}
    </>
  )
}
