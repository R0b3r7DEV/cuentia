import { NavLink } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { useAuth } from '../auth/AuthContext'
import { useTheme } from '../theme/ThemeContext'

const cls = ({ isActive }) => (isActive ? 'navlink active' : 'navlink')

// [path, translation key, icon, exact-match]. Icon shows on mobile, label on desktop.
const NAV = [
  ['/', 'nav.movements', '💳', true],
  ['/dashboard', 'nav.dashboard', '📊', false],
  ['/taxes', 'nav.taxes', '🧮', false],
  ['/invoices', 'nav.invoices', '🧾', false],
  ['/chat', 'nav.assistant', '💬', false],
]

export default function Navbar() {
  const { t, lang, setLang } = useTranslation()
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()

  return (
    <header className="navbar">
      <nav className="navbar-inner">
        <div className="nav-links">
          {NAV.map(([to, key, icon, end]) => (
            <NavLink key={to} to={to} className={cls} end={end} title={t(key)}>
              <span className="nav-ico" aria-hidden="true">{icon}</span>
              <span className="nav-label">{t(key)}</span>
            </NavLink>
          ))}
        </div>

        <div className="nav-actions">
          <NavLink to="/account" className="nav-user" title={user?.email}>
            <span className="nav-ico" aria-hidden="true">👤</span>
            <span className="nav-label nav-email">{user?.email}</span>
          </NavLink>
          <button className="btn btn-glass btn-sm" onClick={logout} title={t('auth.logout')}>
            <span className="nav-ico" aria-hidden="true">🚪</span>
            <span className="nav-label">{t('auth.logout')}</span>
          </button>
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
