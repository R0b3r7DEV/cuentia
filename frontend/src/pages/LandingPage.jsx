import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import Icon from '../components/Icon'

const STEPS = ['wo', 'inv', 'cie'] // C1 · C2 · C3
const FAQ = ['computer', 'mtd', 'submit']

/**
 * The public landing page for electricians — always dark, whatever the app theme. "Entrar" leads to the login.
 * The three features are drawn as breakers on a DIN rail (they can be switched with a finger): something only
 * this trade would recognise.
 * ES: La portada pública para electricistas, siempre oscura. «Entrar» lleva al login. Las tres funciones son
 * automáticos en un carril DIN (se suben y bajan con el dedo): algo que solo reconoce este oficio.
 */
export default function LandingPage() {
  const { t, lang, setLang } = useTranslation()
  const [on, setOn] = useState({ wo: true, inv: true, cie: true })

  return (
    <div className="landing">
      <header className="landing-hero">
        <div className="landing-bar">
          <span className="landing-logo"><b>Cuentia<i>.</i></b><small>{t('landing.tag')}</small><span className="earth" aria-hidden="true" /></span>
          <div className="landing-bar-actions">
            <button type="button" className="landing-lang" onClick={() => setLang(lang === 'es' ? 'en' : 'es')} title="Change language / Cambiar idioma">{lang === 'es' ? 'EN' : 'ES'}</button>
            <Link to="/entrar" className="btn btn-primary landing-enter-sm">{t('landing.enter')}</Link>
          </div>
        </div>

        <div className="landing-hero-grid">
          <div className="landing-copy">
            <h1>{t('landing.h1a')} <mark>{t('landing.h1b')}</mark></h1>
            <p>{t('landing.lead')}</p>
            <div className="landing-ctas">
              <Link to="/entrar" className="btn btn-primary">{t('landing.enter')}</Link>
              <Link to="/registro" className="btn landing-ghost">{t('landing.signup')}</Link>
            </div>
          </div>

          <div className="landing-panel" aria-label={t('landing.panelLabel')}>
            <div className="landing-breakers">
              {STEPS.map((k, i) => (
                <button key={k} type="button" className="breaker" aria-pressed={on[k]} onClick={() => setOn((o) => ({ ...o, [k]: !o[k] }))}
                  aria-label={`${t(`landing.tag.${k}`)} · C${i + 1}`}>
                  <span className="breaker-tag">{t(`landing.tag.${k}`)}</span>
                  <span className="breaker-slot"><i className="breaker-lever" /></span>
                  <i className="breaker-led" />
                  <small>C{i + 1}</small>
                </button>
              ))}
            </div>
          </div>
        </div>
      </header>

      <main className="landing-main">
        <section className="landing-sec">
          <span className="landing-label">C1 · C2 · C3 — {t('landing.flowLabel')}</span>
          <h2>{t('landing.flowTitle')}</h2>
          <ol className="landing-steps">
            {STEPS.map((k, i) => (
              <li key={k}>
                <span className="landing-n">C{i + 1}</span>
                <div>
                  <strong>{t(`landing.step.${k}`)}</strong>
                  <span>{t(`landing.step.${k}.desc`)}</span>
                  {k === 'cie' && <span className="landing-data">ES0021 0000 1234 5678 LB · 5,75 kW · 230 V</span>}
                </div>
              </li>
            ))}
          </ol>
        </section>

        <section className="landing-sec">
          <div className="landing-block">
            <h2>{t('landing.gvaA')} <mark>{t('landing.gvaB')}</mark></h2>
            <p>{t('landing.gvaText')}</p>
          </div>
        </section>

        <section className="landing-sec">
          <h2>{t('landing.trustTitle')}</h2>
          <ul className="landing-trust">
            {['ai', 'data', 'verifactu'].map((k) => <li key={k}><Icon name="check" size={20} />{t(`landing.trust.${k}`)}</li>)}
          </ul>
        </section>

        <section className="landing-sec">
          <h2>{t('landing.faqTitle')}</h2>
          {FAQ.map((k) => (
            <details key={k} className="landing-faq">
              <summary>{t(`landing.faq.${k}.q`)}</summary>
              <p>{t(`landing.faq.${k}.a`)}</p>
            </details>
          ))}
        </section>

        <div className="landing-final">
          <Link to="/entrar" className="btn btn-primary">{t('landing.enter')}</Link>
        </div>
      </main>

      <footer className="landing-foot">
        <span>Cuentia · Castelló</span>
        <a href="https://github.com/R0b3r7DEV/cuentia" target="_blank" rel="noreferrer">GitHub</a>
      </footer>
    </div>
  )
}
