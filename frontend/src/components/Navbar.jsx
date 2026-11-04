import { useEffect, useRef, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { useAuth } from '../auth/AuthContext'
import { useTheme } from '../theme/ThemeContext'
import Icon from './Icon'
import { ACCOUNT, GROUPS, MAIN, MORE_GROUPS, isCurrent } from '../lib/nav'
import LogoWord from './LogoWord'

const item = (base) => ({ isActive }) => `${base}${isActive ? ' active' : ''}`

/**
 * Navigation. Computer: a black side menu with the sections grouped (Obra · Facturación · Dinero), your data
 * and settings at its foot; on a tablet it narrows to icons. Phone: a slim header and a tab bar at the bottom,
 * within thumb's reach; "Más" opens a sheet with the rest, in the same groups. Screens with their own action
 * bar at the bottom (a work order) hide the tabs.
 * ES: Ordenador: menú lateral negro con las secciones agrupadas (Obra · Facturación · Dinero) y tus datos y
 * ajustes al pie; en tablet se queda en iconos. Móvil: cabecera fina y barra de pestañas abajo; «Más» abre
 * una hoja con el resto, con los mismos grupos. Las pantallas con su propia barra abajo (un parte) ocultan
 * las pestañas.
 */
export default function Navbar() {
  const { t, lang, setLang } = useTranslation()
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()
  const { pathname } = useLocation()
  const [sheet, setSheet] = useState(false)
  const sheetRef = useRef(null)

  useEffect(() => { setSheet(false) }, [pathname])
  useEffect(() => {
    if (!sheet) return
    sheetRef.current?.focus()
    const onKey = (e) => { if (e.key === 'Escape') setSheet(false) }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [sheet])

  const inMore = MORE_GROUPS.some((g) => g.items.some(([to]) => isCurrent(to, pathname)))
  const hideTabs = /^\/partes\/[^/]+$/.test(pathname) // the work order has its own bottom action bar
  const switchLang = () => setLang(lang === 'es' ? 'en' : 'es')
  const themeLabel = t(theme === 'dark' ? 'nav.themeLight' : 'nav.themeDark')

  return (
    <>
      {/* Computer: side menu / Ordenador: menú lateral */}
      <aside className="sidebar" aria-label={t('nav.main')}>
        <NavLink to="/" className="sidebar-brand" aria-label="Cuentia">
          <LogoWord /><small>{t('landing.tag')}</small>
        </NavLink>
        <nav className="sidebar-nav">
          {GROUPS.map((g, i) => (
            <div key={g.key ?? `g${i}`} className="sidebar-group">
              {g.key && <span className="sidebar-heading">{t(g.key)}</span>}
              {g.items.map(([to, key, icon]) => (
                <NavLink key={to} to={to} end={to === '/'} className={item('sidebar-link')} title={t(key)}>
                  <Icon name={icon} size={20} /><span>{t(key)}</span>
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
        <div className="sidebar-foot">
          {ACCOUNT.map(([to, key, icon]) => (
            <NavLink key={to} to={to} className={item('sidebar-link')} title={t(key)}><Icon name={icon} size={20} /><span>{t(key)}</span></NavLink>
          ))}
          <div className="sidebar-actions">
            <button type="button" className="sidebar-btn" onClick={toggle} aria-label={themeLabel} title={themeLabel}><Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} /></button>
            <button type="button" className="sidebar-btn" onClick={switchLang} title="Change language / Cambiar idioma">{lang === 'es' ? 'EN' : 'ES'}</button>
            <button type="button" className="sidebar-btn" onClick={logout} aria-label={t('auth.logout')} title={t('auth.logout')}><Icon name="logout" size={18} /></button>
          </div>
          <span className="sidebar-user mono" title={user?.email}>{user?.email}</span>
        </div>
      </aside>

      {/* Phone: slim header + bottom tabs + "Más" sheet / Móvil */}
      <header className="navbar">
        <div className="navbar-inner">
          <NavLink to="/" className="brand" aria-label="Cuentia">Cuentia<span className="brand-dot">.</span></NavLink>
          <div className="nav-actions">
            <button className="btn btn-sm icon-btn" onClick={toggle} aria-label={themeLabel} title={themeLabel}>
              <Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />
            </button>
          </div>
        </div>
      </header>

      {!hideTabs && (
        <nav className="tabbar" aria-label={t('nav.main')}>
          {MAIN.map(([to, key, icon]) => (
            <NavLink key={to} to={to} end={to === '/'} className={item('tab')}><Icon name={icon} size={22} /><span>{t(key)}</span></NavLink>
          ))}
          <button type="button" className={`tab${inMore ? ' active' : ''}`} aria-haspopup="dialog" aria-expanded={sheet} onClick={() => setSheet(true)}>
            <Icon name="more" size={22} /><span>{t('nav.more')}</span>
          </button>
        </nav>
      )}

      {sheet && (
        <div className="sheet-backdrop" onClick={() => setSheet(false)}>
          <div className="sheet" role="dialog" aria-modal="true" aria-label={t('nav.more')} tabIndex={-1} ref={sheetRef} onClick={(e) => e.stopPropagation()}>
            <div className="sheet-head">
              <strong>{t('nav.more')}</strong>
              <button type="button" className="btn btn-sm icon-btn" onClick={() => setSheet(false)} aria-label={t('nav.close')}><Icon name="close" size={18} /></button>
            </div>
            {MORE_GROUPS.map((g, i) => (
              <section key={g.key ?? `m${i}`} className="sheet-group">
                {g.key && <h3 className="sheet-heading">{t(g.key)}</h3>}
                <div className="sheet-grid">
                  {g.items.map(([to, key, icon]) => (
                    <NavLink key={to} to={to} className={item('sheet-item')}><Icon name={icon} size={22} />{t(key)}</NavLink>
                  ))}
                </div>
              </section>
            ))}
            <div className="sheet-actions">
              <button type="button" className="btn" onClick={toggle}><Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />{themeLabel}</button>
              <button type="button" className="btn" onClick={switchLang}><Icon name="globe" size={18} />{lang === 'es' ? 'English' : 'Español'}</button>
              <button type="button" className="btn btn-danger-glass" onClick={logout}><Icon name="logout" size={18} />{t('auth.logout')}</button>
            </div>
            <p className="muted sheet-user mono">{user?.email}</p>
          </div>
        </div>
      )}
    </>
  )
}
