import { useRef, useState } from 'react'
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
  network_error: 'auth.networkError',
}

export default function AuthPage() {
  const { login, register } = useAuth()
  const { t, lang, setLang } = useTranslation()
  const { theme, toggle } = useTheme()
  const [mode, setMode] = useState('login') // 'login' | 'register'
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

  const submit = async (e) => {
    e.preventDefault()
    setError(null); setBusy(true)
    try {
      if (mode === 'login') await login(email, password)
      else await register(email, password)
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

        <h2>{mode === 'login' ? t('auth.loginTitle') : t('auth.registerTitle')}</h2>
        <form onSubmit={submit} className="auth-form">
          <input className="chat-input" type="email" required placeholder={t('auth.email')}
            value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" />
          <input className="chat-input" type="password" required minLength={mode === 'register' ? 8 : 1} placeholder={t('auth.password')}
            value={password} onChange={(e) => setPassword(e.target.value)}
            autoComplete={mode === 'login' ? 'current-password' : 'new-password'} />
          {error && <p className="msg error">{error}</p>}
          <button className="btn btn-primary" type="submit" disabled={busy}>
            {mode === 'login' ? t('auth.login') : t('auth.register')}
          </button>
        </form>

        <p className="msg">
          {mode === 'login' ? t('auth.noAccount') : t('auth.haveAccount')}{' '}
          <button className="link-btn" onClick={() => { setMode(mode === 'login' ? 'register' : 'login'); setError(null) }}>
            {mode === 'login' ? t('auth.register') : t('auth.login')}
          </button>
        </p>
      </div>
    </div>
  )
}
