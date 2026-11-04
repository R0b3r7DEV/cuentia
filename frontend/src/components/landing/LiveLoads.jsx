import { useEffect, useRef, useState } from 'react'
import { useTranslation } from '../../i18n/LanguageContext'
import { CIRCUITS, isLive } from '../../lib/board'

const CUPS = 'ES0021000012345678LB'
const SECTIONS = ['A', 'B', 'C', 'D', 'E', 'F', 'G']
const hex = (n) => Array.from({ length: n }, () => '0123456789abcdef'[Math.floor(Math.random() * 16)]).join('')

/** C2 · the invoice's fingerprint is written and sealed into the chain. / La huella se escribe y se sella. */
function InvoiceDemo({ reduce }) {
  const { t } = useTranslation()
  const [n, setN] = useState(reduce ? 64 : 0)
  const hash = useRef(hex(64))
  useEffect(() => {
    if (reduce) return undefined
    const id = setInterval(() => setN((v) => (v >= 64 ? (clearInterval(id), v) : v + 4)), 45)
    return () => clearInterval(id)
  }, [reduce])
  return (
    <div className="live-inv">
      <div className="live-row"><span className="mono">F-2026/0043</span><span className="live-total">459,30 €</span></div>
      <div className="live-hash mono">SHA-256 {hash.current.slice(0, n)}</div>
      <div className={`live-sealed mono${n >= 64 ? ' show' : ''}`}>✓ {t('live.sealed')}</div>
    </div>
  )
}

/** C3 · the certificate fills section by section, the CUPS is read. / El certificado se rellena por secciones. */
function CieDemo({ reduce }) {
  const [n, setN] = useState(reduce ? SECTIONS.length + 1 : 0)
  useEffect(() => {
    if (reduce) return undefined
    const id = setInterval(() => setN((v) => (v > SECTIONS.length ? (clearInterval(id), v) : v + 1)), 260)
    return () => clearInterval(id)
  }, [reduce])
  return (
    <div>
      <div className="live-cie">{SECTIONS.map((s, i) => <span key={s} className={i < n ? 'done' : ''}>{s}</span>)}</div>
      <div className="live-cups mono">CUPS {CUPS.slice(0, Math.min(CUPS.length, n * 3))}</div>
      <p className="live-note">María López García · 5,75 kW · 230 V</p>
    </div>
  )
}

/** C1 · the customer's signature draws itself. / La firma del cliente se dibuja. */
function SignatureDemo() {
  const { t } = useTranslation()
  return (
    <div>
      <svg className="live-sig" viewBox="0 0 300 70" aria-hidden="true">
        <path d="M14 46c18-30 30-30 26-6-3 18 10 10 18-6s16-14 14 4c-2 14 12 6 20-6 6-9 12-4 10 6-2 9 8 6 16-2 10-10 20-12 24 0 3 9 12 4 22-6 8-8 20-10 26 2 4 8 14 4 24-4 12-9 26-8 40 2" />
      </svg>
      <p className="live-note">{t('live.signed')}</p>
    </div>
  )
}

/**
 * The three things the board's circuits power, as cards that light up when current reaches them — each one
 * plays a short demo of what that part of the app does. Remounting a demo restarts it, so flipping a breaker
 * back on plays it again. / Lo que alimenta cada circuito: tarjetas que se encienden con su demostración.
 */
export default function LiveLoads({ state }) {
  const { t } = useTranslation()
  const reduce = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  const demo = { wo: <SignatureDemo />, inv: <InvoiceDemo reduce={reduce} />, cie: <CieDemo reduce={reduce} /> }
  return (
    <div className="live-loads" aria-live="polite">
      {CIRCUITS.map((k, i) => {
        const live = isLive(state, k)
        return (
          <article key={k} className={`live-load${live ? ' on' : ''}`}>
            <header><span className="landing-n">C{i + 1}</span><span className="live-led" aria-hidden="true" /></header>
            <strong>{t(`landing.step.${k}`)}</strong>
            <span className="live-desc">{t(`landing.step.${k}.desc`)}</span>
            {live ? demo[k] : <p className="live-dead mono">{t('live.dead', { c: `C${i + 1}` })}</p>}
          </article>
        )
      })}
    </div>
  )
}
