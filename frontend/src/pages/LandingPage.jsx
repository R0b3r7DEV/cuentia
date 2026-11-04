import { Suspense, lazy, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import Icon from '../components/Icon'
import AppGlow from '../components/AppGlow'
import LiveLoads from '../components/landing/LiveLoads'
import { CIRCUITS, INITIAL_BOARD, act, statusKey } from '../lib/board'

// Three.js only downloads after the page is up, as its own chunk. / Three.js se descarga después, aparte.
const Board3D = lazy(() => import('../components/landing/Board3D'))

/** WebGL available? Without it, the 2D breakers do the same job. / ¿Hay WebGL? Si no, los automáticos 2D. */
function hasWebGL() {
  try {
    const c = document.createElement('canvas')
    return Boolean(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')))
  } catch { return false }
}

/** A short "clack", from the user's own click (Web Audio). / Un «clac» corto. */
let audio = null
function clack(hard) {
  try {
    audio = audio || new (window.AudioContext || window.webkitAudioContext)()
    const len = Math.floor(audio.sampleRate * (hard ? 0.09 : 0.05))
    const buf = audio.createBuffer(1, len, audio.sampleRate)
    const d = buf.getChannelData(0)
    for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, hard ? 3 : 6)
    const src = audio.createBufferSource(), f = audio.createBiquadFilter(), g = audio.createGain()
    f.type = 'bandpass'; f.frequency.value = hard ? 1400 : 2600; g.gain.value = hard ? 0.5 : 0.35
    src.buffer = buf; src.connect(f); f.connect(g); g.connect(audio.destination); src.start()
  } catch { /* no audio: fine */ }
}

const FAQ = ['computer', 'mtd', 'submit']

/** The breakers in 2D: while the 3D loads, or without WebGL. / Los automáticos en 2D. */
function Breakers2D({ state, onAct, t }) {
  return (
    <div className="landing-breakers">
      {CIRCUITS.map((k, i) => (
        <button key={k} type="button" className="breaker" aria-pressed={state[k]} onClick={() => onAct(k)}
          aria-label={`${t(`landing.tag.${k}`)} · C${i + 1}`}>
          <span className="breaker-tag">{t(`landing.tag.${k}`)}</span>
          <span className="breaker-slot"><i className="breaker-lever" /></span>
          <i className="breaker-led" />
          <small>C{i + 1}</small>
        </button>
      ))}
    </div>
  )
}

/**
 * The public landing page for electricians — always dark, whatever the app theme. "Entrar" leads to the login.
 * Its centrepiece is a working distribution board in 3D: flip a breaker and the part of the app it powers
 * lights up below; press the RCD's T button and it trips. Something only this trade would recognise.
 * ES: La portada pública para electricistas, siempre oscura. Su pieza central es un cuadro eléctrico en 3D que
 * funciona: al subir un automático se enciende abajo lo que alimenta; el botón T del diferencial lo hace saltar.
 */
export default function LandingPage() {
  const { t, lang, setLang } = useTranslation()
  const [board, setBoard] = useState(INITIAL_BOARD)
  const [toast, setToast] = useState(null)
  const [webgl] = useState(hasWebGL)
  const shakeAt = useRef(0)
  const toastTimer = useRef(null)

  const onAct = (target) => {
    const r = act(board, target)
    if (r.state === board && !r.message) return
    setBoard(r.state)
    clack(r.tripped)
    if (r.tripped) shakeAt.current = performance.now()
    if (r.message) {
      setToast(r.message)
      clearTimeout(toastTimer.current)
      toastTimer.current = setTimeout(() => setToast(null), 3600)
    }
  }
  const status = statusKey(board)
  const labels = Object.fromEntries(CIRCUITS.map((k) => [k, t(`landing.tag.${k}`)]))

  return (
    <div className="landing">
      <AppGlow />
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

          <div className="board-col">
            <div className={`landing-panel board-stage${webgl ? ' is-3d' : ''}`} aria-label={t('landing.panelLabel')}>
              {webgl
                ? <Suspense fallback={<Breakers2D state={board} onAct={onAct} t={t} />}><Board3D state={board} onAct={onAct} labels={labels} shakeAt={shakeAt} /></Suspense>
                : <Breakers2D state={board} onAct={onAct} t={t} />}
              <span className={`board-status mono${status === 'board.statusOn' ? ' on' : ' off'}`}>● {t(status)}</span>
              <div className={`board-toast${toast ? ' show' : ''}`} role="status">{toast ? t(toast) : ''}</div>
              {webgl && <div className="board-hint mono"><span>{t('board.hint1')}</span><span>{t('board.hint2')}</span></div>}
            </div>
            {/* the same actions for the keyboard and screen readers / las mismas acciones con el teclado */}
            <div className="board-switches" role="group" aria-label={t('board.switches')}>
              <button type="button" aria-pressed={board.iga} onClick={() => onAct('iga')}>IGA</button>
              <button type="button" aria-pressed={board.id} onClick={() => onAct('id')}>ID</button>
              <button type="button" className="test" onClick={() => onAct('test')}>{t('board.test')}</button>
              {CIRCUITS.map((k, i) => (
                <button key={k} type="button" aria-pressed={board[k]} onClick={() => onAct(k)}>C{i + 1} · {t(`landing.tag.${k}`)}</button>
              ))}
            </div>
          </div>
        </div>
      </header>

      <main className="landing-main">
        <section className="landing-sec">
          <span className="landing-label">C1 · C2 · C3 — {t('landing.flowLabel')}</span>
          <h2>{t('landing.flowTitle')}</h2>
          <LiveLoads state={board} />
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
