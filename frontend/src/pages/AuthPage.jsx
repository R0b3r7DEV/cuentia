import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { useTranslation } from '../i18n/LanguageContext'
import AppGlow from '../components/AppGlow'
import LogoWord from '../components/LogoWord'

/**
 * Log in, sign up, forgot / reset password — reached from the landing page's "Entrar". A black header with the
 * brand (as on the landing) and the form below, on the light page.
 * ES: Entrar, crear cuenta, recuperar contraseña — se llega desde «Entrar» en la portada. Cabecera negra con la
 * marca (como en la portada) y el formulario debajo.
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

export default function AuthPage({ initialMode = 'login' }) {
  const { login, register } = useAuth()
  const { t, lang, setLang } = useTranslation()
  const [resetToken] = useState(tokenFromUrl)
  // 'login' | 'register' | 'forgot' | 'reset'
  const [mode, setMode] = useState(() => (resetToken ? 'reset' : initialMode))
  const [notice, setNotice] = useState(null) // a neutral, non-error message

  // Once read, take the token out of the address bar and the history entry.
  useEffect(() => {
    if (resetToken) window.history.replaceState(null, '', '/entrar')
  }, [resetToken])
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
    <div className="auth">
      <AppGlow />
      <header className="auth-head">
        <div className="auth-head-bar">
          <Link to="/" className="landing-logo"><LogoWord auto /><small>{t('landing.tag')}</small></Link>
          <div className="auth-head-actions">
            <button type="button" className="landing-lang" onClick={() => setLang(lang === 'es' ? 'en' : 'es')}>{lang === 'es' ? 'EN' : 'ES'}</button>
            <Link to="/" className="auth-back">{t('landing.back')}</Link>
          </div>
        </div>
      </header>

      <div className="auth-body">
        <h1 className="auth-title">{t({ login: 'auth.hello', register: 'auth.registerTitle', forgot: 'auth.forgotTitle', reset: 'auth.resetTitle' }[mode])}</h1>
        {mode === 'forgot' && <p className="msg" style={{ marginTop: 0 }}>{t('auth.forgotDesc')}</p>}
        <form onSubmit={submit} className="auth-form">
          {mode !== 'reset' && (
            <label className="field" htmlFor="auth-email"><span>{t('auth.email')}</span>
              <input id="auth-email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" />
            </label>
          )}
          {mode !== 'forgot' && (
            <label className="field" htmlFor="auth-password"><span>{mode === 'reset' ? t('auth.newPassword') : t('auth.password')}</span>
              <input id="auth-password" type="password" required minLength={mode === 'login' ? 1 : 8}
                value={password} onChange={(e) => setPassword(e.target.value)}
                autoComplete={mode === 'login' ? 'current-password' : 'new-password'} />
            </label>
          )}
          {mode === 'reset' && (
            <label className="field" htmlFor="auth-password2"><span>{t('auth.repeatPassword')}</span>
              <input id="auth-password2" type="password" required minLength={8}
                value={password2} onChange={(e) => setPassword2(e.target.value)} autoComplete="new-password" />
            </label>
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
