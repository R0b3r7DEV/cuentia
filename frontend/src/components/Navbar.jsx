import { useEffect, useRef, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { useAuth } from '../auth/AuthContext'
import { useTheme } from '../theme/ThemeContext'
import Icon from './Icon'

const cls = ({ isActive }) => (isActive ? 'navlink active' : 'navlink')
const menuCls = ({ isActive }) => (isActive ? 'nav-menu-item active' : 'nav-menu-item')

// The everyday accounting screens live under one "Panel" menu; the electrician's tools stand on their own.
// ES: Las pantallas de contabilidad diaria viven bajo un único menú "Panel"; las herramientas de
// electricista van sueltas, que es como se usan.
const PANEL = [
  ['/dashboard', 'nav.overview'],
  ['/', 'nav.movements'],
  ['/taxes', 'nav.taxes'],
  ['/invoices', 'nav.invoices'],
]
const TOP = [
  ['/partes', 'nav.workOrders', 'wrench'],
  ['/certificates', 'bill.certificates', 'certificate'],
  ['/installation', 'bill.installation', 'zap'],
  ['/chat', 'nav.assistant', 'chat'],
  ['/mis-datos', 'nav.myData', 'building'],
]

export default function Navbar() {
  const { t, lang, setLang } = useTranslation()
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()
  const { pathname } = useLocation()
  const [open, setOpen] = useState(false)
  const groupRef = useRef(null)

  useEffect(() => { setOpen(false) }, [pathname])

  useEffect(() => {
    if (!open) return
    const onDown = (e) => { if (!groupRef.current?.contains(e.target)) setOpen(false) }
    const onKey = (e) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('pointerdown', onDown)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('pointerdown', onDown); document.removeEventListener('keydown', onKey) }
  }, [open])

  // The trigger lights up whenever any of its children is the current route.
  const inPanel = PANEL.some(([to]) => (to === '/' ? pathname === '/' : pathname.startsWith(to)))

  return (
    <header className="navbar">
      <nav className="navbar-inner">
        <div className="nav-links">
          <div className="nav-group" ref={groupRef}>
            <button
              type="button"
              className={`navlink nav-trigger${inPanel ? ' active' : ''}`}
              aria-haspopup="menu" aria-expanded={open}
              onClick={() => setOpen((o) => !o)}
              title={t('nav.dashboard')}
            >
              <Icon name="chart" className="nav-ico" />
              <span className="nav-label">{t('nav.dashboard')}</span>
              <Icon name="chevronDown" size={14} className="nav-caret" />
            </button>

            {open && (
              <div className="nav-menu" role="menu">
                {PANEL.map(([to, key]) => (
                  <NavLink key={to} to={to} role="menuitem" end={to === '/'} className={menuCls}>
                    {t(key)}
                  </NavLink>
                ))}
              </div>
            )}
          </div>

          {TOP.map(([to, key, icon]) => (
            <NavLink key={to} to={to} className={cls} title={t(key)}>
              <Icon name={icon} className="nav-ico" />
              <span className="nav-label">{t(key)}</span>
            </NavLink>
          ))}
        </div>

        <div className="nav-actions">
          <NavLink to="/account" className="nav-user" title={user?.email}>
            <Icon name="user" className="nav-ico" />
            <span className="nav-label nav-email">{user?.email}</span>
          </NavLink>
          <button className="btn btn-glass btn-sm" onClick={logout} title={t('auth.logout')}>
            <Icon name="logout" className="nav-ico" />
            <span className="nav-label">{t('auth.logout')}</span>
          </button>
          <button className="btn btn-glass btn-sm icon-btn" onClick={toggle} title="Light / dark" aria-label="Toggle theme">
            <Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />
          </button>
          <button className="btn btn-glass btn-sm icon-btn" onClick={() => setLang(lang === 'es' ? 'en' : 'es')} title="Change language / Cambiar idioma">
            {lang === 'es' ? 'EN' : 'ES'}
          </button>
        </div>
      </nav>
    </header>
  )
}
