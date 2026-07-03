import { NavLink } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { useAuth } from '../auth/AuthContext'

const cls = ({ isActive }) => (isActive ? 'navlink active' : 'navlink')

export default function Navbar() {
  const { t, lang, setLang } = useTranslation()
  const { user, logout } = useAuth()

  return (
    <header className="navbar">
      <nav className="navbar-inner">
        <span className="brand">Cuentia<span className="brand-dot">.</span></span>
        <NavLink to="/" className={cls} end>{t('nav.movements')}</NavLink>
        <NavLink to="/dashboard" className={cls}>{t('nav.dashboard')}</NavLink>
        <NavLink to="/taxes" className={cls}>{t('nav.taxes')}</NavLink>
        <NavLink to="/chat" className={cls}>{t('nav.assistant')}</NavLink>

        <NavLink to="/account" className="nav-user" title={t('account.title')}>{user?.email}</NavLink>
        <button className="btn nav-logout" onClick={logout}>{t('auth.logout')}</button>
        <button className="lang-btn" onClick={() => setLang(lang === 'es' ? 'en' : 'es')} title="Change language / Cambiar idioma">
          {lang === 'es' ? 'EN' : 'ES'}
        </button>
      </nav>
    </header>
  )
}
