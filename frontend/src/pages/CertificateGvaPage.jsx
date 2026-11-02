import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'

const SEDE_URL = 'https://sede.gva.es/es/detall-tramit?id_proc=440'

/**
 * "Para la GVA": one certificate laid out as the CERTINS E web form (sections A–G, the model's labels and
 * order), each value with a copy button — to copy it by hand, or for Claude in Chrome to read from this tab
 * and type into the GVA form in the other one. Signing and registering are always the electrician's.
 *
 * ES: «Para la GVA»: un certificado ordenado como el formulario web CERTINS E, cada dato con su botón de copiar,
 * para copiarlo a mano o para que Claude en Chrome lo lea de esta pestaña y lo escriba en la de la GVA.
 * Firmar y registrar es siempre cosa del electricista.
 */
export default function CertificateGvaPage() {
  const { id } = useParams()
  const { t } = useTranslation()
  const [sheet, setSheet] = useState(null)
  const [error, setError] = useState(false)
  const [copied, setCopied] = useState(null)

  useEffect(() => {
    fetch(`/api/certificates/${id}/gva`).then((r) => (r.ok ? r.json() : Promise.reject())).then(setSheet).catch(() => setError(true))
  }, [id])

  const copy = async (key, text) => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(key)
      setTimeout(() => setCopied((c) => (c === key ? null : c)), 1500)
    } catch { /* clipboard not allowed: the value is on screen to copy by hand */ }
  }

  if (error) return <p className="msg error">{t('gva.loadError')}</p>
  if (!sheet) return <p className="muted">{t('wo.loading')}</p>

  const prompt = t('gva.prompt')

  return (
    <div className="gva">
      <Link to="/certificates" className="link-btn">← {t('bill.certificates')}</Link>
      <h1 className="page-title">🏛️ {t('gva.title')}</h1>
      <p className="page-subtitle">{t('gva.subtitle')}</p>

      <div className="card gva-how">
        <ol>
          <li>{t('gva.how1')} <a className="link-btn" href={SEDE_URL} target="_blank" rel="noreferrer">{t('gva.openSede')} ↗</a></li>
          <li>{t('gva.how2')}</li>
          <li><strong>{t('gva.how3')}</strong></li>
        </ol>
        <details className="gva-claude">
          <summary>🤖 {t('gva.claudeTitle')}</summary>
          <p className="muted">{t('gva.claudeDesc')}</p>
          <pre className="gva-prompt">{prompt}</pre>
          <button type="button" className="btn btn-glass btn-sm" onClick={() => copy('prompt', prompt)}>
            {copied === 'prompt' ? `✓ ${t('gva.copied')}` : `📋 ${t('gva.copyPrompt')}`}
          </button>
        </details>
      </div>

      {sheet.missing.length > 0 && (
        <div className="card alert-warn">
          {t('gva.missing')} <strong>{sheet.missing.map((m) => m.label).join(' · ')}</strong>
        </div>
      )}

      {sheet.sections.map((s) => (
        <section key={s.code} className={`card gva-sec${s.code === 'MTD' ? ' gva-mtd' : ''}`}>
          <h2><span className="gva-code">{s.code}</span>{s.title}</h2>
          <dl>
            {s.rows.map((r) => (
              <div key={r.key} className="gva-row">
                <dt>{r.label}</dt>
                <dd>
                  {r.value
                    ? <span className="gva-value">{r.value}</span>
                    : <span className={r.required ? 'gva-missing' : 'muted'}>{r.required ? t('gva.fieldMissing') : '—'}</span>}
                  {r.value && (
                    <button type="button" className="gva-copy" aria-label={t('gva.copyField', { field: r.label })} onClick={() => copy(r.key, r.value)}>
                      {copied === r.key ? '✓' : '📋'}
                    </button>
                  )}
                </dd>
              </div>
            ))}
          </dl>
        </section>
      ))}

      <p className="small muted">{t('gva.version', { v: sheet.version })}</p>
      <div className="wo-add-row">
        <Link className="btn btn-glass" to="/certificates" state={{ edit: sheet.id }}>✏️ {t('cie.edit')}</Link>
        <a className="btn btn-glass" href={`/api/certificates/${sheet.id}/pdf`}>{t('cie.download')}</a>
      </div>
    </div>
  )
}
