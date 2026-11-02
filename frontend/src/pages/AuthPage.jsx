import { useEffect, useRef, useState } from 'react'
import { useAuth } from '../auth/AuthContext'
import { useTranslation } from '../i18n/LanguageContext'
import { useTheme } from '../theme/ThemeContext'
// Background: office scene by George Bakos on Unsplash (free license). Optimised to ~2000px / 142 KB.
import authScene from '../assets/auth-scene.jpg'

/**
 * The login / register screen shown when nobody is logged in.
 * ES: La pantalla de login / registro que se muestra cuando nadie ha iniciado sesión.
 */
// The backend's stable error codes → translation keys.
// ES: Los códigos de error estables del backend → claves de traducción.
const CODE_MESSAGES = {
  bad_credentials: 'auth.badCredentials',
  email_taken: 'auth.emailTaken',
  invalid_email: 'auth.invalidEmail',
  weak_password: 'auth.weakPassword',
  too_many_attempts: 'auth.tooManyAttempts',
  too_many_requests: 'auth.tooManyAttempts',
  invalid_token: 'auth.resetInvalidToken',
  network_error: 'auth.networkError',
}

/** The token of a reset link (…/restablecer#token=…). It travels after the # so no server ever sees it. */
function tokenFromUrl() {
  const m = /[#&]token=([a-f0-9]{64})/.exec(window.location.hash)
  return m ? m[1] : null
}

/** POST JSON to a public endpoint; throws an Error with the backend's `code` and HTTP status. */
async function postPublic(url, body) {
  let r
  try {
    r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
  } catch {
    const err = new Error('network'); err.code = 'network_error'; throw err
  }
  const data = await r.json().catch(() => ({}))
  if (!r.ok) { const err = new Error(data.code || 'error'); err.code = data.code; err.status = r.status; throw err }
  return data
}

export default function AuthPage() {
  const { login, register } = useAuth()
  const { t, lang, setLang } = useTranslation()
  const { theme, toggle } = useTheme()
  const [resetToken] = useState(tokenFromUrl)
  // 'login' | 'register' | 'forgot' | 'reset'
  const [mode, setMode] = useState(() => (resetToken ? 'reset' : 'login'))
  const [notice, setNotice] = useState(null) // a neutral, non-error message

  // Once read, take the token out of the address bar and the history entry.
  useEffect(() => {
    if (resetToken) window.history.replaceState(null, '', '/')
  }, [resetToken])
  const cardRef = useRef(null)

  // Subtle 3D parallax: the card tilts toward the cursor so it reads as a layer floating above the static
  // photo, not part of it. Motion is the depth cue, so it is disabled under prefers-reduced-motion.
  // ES: Paralaje 3D sutil: la tarjeta se inclina hacia el cursor y se lee como una capa sobre la foto.
  const tilt = (e) => {
    const el = cardRef.current
    if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    const r = el.getBoundingClientRect()
    const clamp = (v) => Math.max(-0.6, Math.min(0.6, v))
    const dx = clamp((e.clientX - (r.left + r.width / 2)) / r.width)
    const dy = clamp((e.clientY - (r.top + r.height / 2)) / r.height)
    el.style.setProperty('--rx', `${dx * 5}deg`)
    el.style.setProperty('--ry', `${-dy * 5}deg`)
  }
  const untilt = () => {
    const el = cardRef.current
    if (!el) return
    el.style.setProperty('--rx', '0deg')
    el.style.setProperty('--ry', '0deg')
  }
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [password2, setPassword2] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  /** Turn a thrown auth error into a friendly, translated message. */
  const messageFor = (err) => {
    const key = CODE_MESSAGES[err.code]
    if (key) return t(key)
    if (err.status === 401) return t('auth.badCredentials')
    if (err.status === 409) return t('auth.emailTaken')
    if (err.status === 429) return t('auth.tooManyAttempts')
    if (err.status >= 500) return t('auth.serverError')
    return t('auth.error')
  }

  const go = (next) => { setMode(next); setError(null); setNotice(null); setPassword(''); setPassword2('') }

  const submit = async (e) => {
    e.preventDefault()
    setError(null); setNotice(null)
    if (mode === 'reset' && password !== password2) { setError(t('auth.resetMismatch')); return }
    setBusy(true)
    try {
      if (mode === 'login') await login(email, password)
      else if (mode === 'register') await register(email, password)
      else if (mode === 'forgot') {
        await postPublic('/api/password/forgot', { email })
        setNotice(t('auth.resetSent'))
      } else {
        await postPublic('/api/password/reset', { token: resetToken, password })
        go('login')
        setNotice(t('auth.resetDone'))
      }
    } catch (err) {
      setError(messageFor(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="auth-wrap" onPointerMove={tilt} onPointerLeave={untilt}>
      {/* The scene behind the card: a warm lamp-lit room, warmed or dimmed by the theme. */}
      <div className="auth-scene" aria-hidden="true" style={{ backgroundImage: `url(${authScene})` }} />
      <div className="auth-scene-tint" aria-hidden="true" />
      {/* The lamp itself is the theme switch: click it (or focus + Enter) to turn the room's light on/off.
          A real, labelled, keyboard-focusable control — it is the only theme toggle on this page. */}
      <button
        type="button"
        className="auth-lamp-switch"
        onClick={toggle}
        aria-pressed={theme === 'light'}
        aria-label={theme === 'dark' ? t('auth.lightOn') : t('auth.lightOff')}
        title={theme === 'dark' ? t('auth.lightOn') : t('auth.lightOff')}
      />

      <div className="card auth-card" ref={cardRef}>
        <div className="auth-glow" aria-hidden="true" />
        <div className="auth-topbar">
          <button className="btn btn-glass btn-sm icon-btn" onClick={() => setLang(lang === 'es' ? 'en' : 'es')}>
            {lang === 'es' ? 'EN' : 'ES'}
          </button>
        </div>
        <div className="auth-brand">Cuentia<span className="brand-dot">.</span></div>
        <p className="page-subtitle auth-tagline">{t('auth.tagline')}</p>

        <h2>{t({ login: 'auth.loginTitle', register: 'auth.registerTitle', forgot: 'auth.forgotTitle', reset: 'auth.resetTitle' }[mode])}</h2>
        {mode === 'forgot' && <p className="msg" style={{ marginTop: 0 }}>{t('auth.forgotDesc')}</p>}
        <form onSubmit={submit} className="auth-form">
          {mode !== 'reset' && (
            <input className="chat-input" type="email" required placeholder={t('auth.email')}
              value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" />
          )}
          {mode !== 'forgot' && (
            <input className="chat-input" type="password" required minLength={mode === 'login' ? 1 : 8}
              placeholder={mode === 'reset' ? t('auth.newPassword') : t('auth.password')}
              value={password} onChange={(e) => setPassword(e.target.value)}
              autoComplete={mode === 'login' ? 'current-password' : 'new-password'} />
          )}
          {mode === 'reset' && (
            <input className="chat-input" type="password" required minLength={8} placeholder={t('auth.repeatPassword')}
              value={password2} onChange={(e) => setPassword2(e.target.value)} autoComplete="new-password" />
          )}
          {error && <p className="msg error">{error}</p>}
          {notice && <p className="msg" role="status">{notice}</p>}
          {!(mode === 'forgot' && notice) && (
            <button className="btn btn-primary" type="submit" disabled={busy}>
              {t({ login: 'auth.login', register: 'auth.register', forgot: 'auth.forgotSend', reset: 'auth.resetSave' }[mode])}
            </button>
          )}
        </form>

        {mode === 'login' && (
          <p className="msg"><button className="link-btn" onClick={() => go('forgot')}>{t('auth.forgotLink')}</button></p>
        )}
        {mode === 'login' || mode === 'register' ? (
          <p className="msg">
            {mode === 'login' ? t('auth.noAccount') : t('auth.haveAccount')}{' '}
            <button className="link-btn" onClick={() => go(mode === 'login' ? 'register' : 'login')}>
              {mode === 'login' ? t('auth.register') : t('auth.login')}
            </button>
          </p>
        ) : (
          <p className="msg"><button className="link-btn" onClick={() => go('login')}>← {t('auth.backToLogin')}</button></p>
        )}
      </div>
    </div>
  )
}
