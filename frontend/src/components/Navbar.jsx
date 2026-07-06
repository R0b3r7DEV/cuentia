import { NavLink } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { useAuth } from '../auth/AuthContext'
import { useTheme } from '../theme/ThemeContext'

const cls = ({ isActive }) => (isActive ? 'navlink active' : 'navlink')

export default function Navbar() {
  const { t, lang, setLang } = useTranslation()
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()

  return (
    <header className="navbar">
      <nav className="navbar-inner">
        <div className="nav-links">
          <NavLink to="/" className={cls} end>{t('nav.movements')}</NavLink>
          <NavLink to="/dashboard" className={cls}>{t('nav.dashboard')}</NavLink>
          <NavLink to="/taxes" className={cls}>{t('nav.taxes')}</NavLink>
          <NavLink to="/invoices" className={cls}>{t('nav.invoices')}</NavLink>
          <NavLink to="/chat" className={cls}>{t('nav.assistant')}</NavLink>
        </div>

        <div className="nav-actions">
          <NavLink to="/account" className="nav-user" title={t('account.title')}>{user?.email}</NavLink>
          <button className="btn btn-glass btn-sm" onClick={logout}>{t('auth.logout')}</button>
          <button className="btn btn-glass btn-sm icon-btn" onClick={toggle} title="Light / dark" aria-label="Toggle theme">
            {theme === 'dark' ? '☀️' : '🌙'}
          </button>
          <button className="btn btn-glass btn-sm icon-btn" onClick={() => setLang(lang === 'es' ? 'en' : 'es')} title="Change language / Cambiar idioma">
            {lang === 'es' ? 'EN' : 'ES'}
          </button>
        </div>
      </nav>
    </header>
  )
}
