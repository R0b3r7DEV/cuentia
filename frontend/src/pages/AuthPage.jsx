import { useState } from 'react'
import { useAuth } from '../auth/AuthContext'
import { useTranslation } from '../i18n/LanguageContext'
import { useTheme } from '../theme/ThemeContext'

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
    <div className="auth-wrap">
      <div className="card auth-card">
        <div className="auth-topbar">
          <button className="btn btn-glass btn-sm icon-btn" onClick={toggle} title="Light / dark" aria-label="Toggle theme">
            {theme === 'dark' ? '☀️' : '🌙'}
          </button>
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
          <button className="btn btn-glass" type="submit" disabled={busy}>
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
