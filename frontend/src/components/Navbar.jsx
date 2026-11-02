import { useEffect, useRef, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { useAuth } from '../auth/AuthContext'
import { useTheme } from '../theme/ThemeContext'
import Icon from './Icon'
import { MAIN, MORE, isCurrent } from '../lib/nav'

const cls = ({ isActive }) => (isActive ? 'navlink active' : 'navlink')

/**
 * Navigation. On a computer: one top bar with the four daily sections and a "Más" menu. On a phone: a slim
 * header and a tab bar at the bottom, within thumb's reach; "Más" opens a sheet with the rest, the theme,
 * the language and logging out. Screens with their own action bar at the bottom (a work order) hide the tabs.
 * ES: En el ordenador, una barra arriba con las cuatro secciones diarias y un menú «Más». En el móvil, una
 * cabecera fina y una barra de pestañas abajo, a mano del pulgar; «Más» abre una hoja con el resto, el tema,
 * el idioma y salir. Las pantallas con su propia barra de acciones abajo (un parte) ocultan las pestañas.
 */
export default function Navbar() {
  const { t, lang, setLang } = useTranslation()
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()
  const { pathname } = useLocation()
  const [menu, setMenu] = useState(false) // desktop "Más" dropdown
  const [sheet, setSheet] = useState(false) // mobile "Más" sheet
  const groupRef = useRef(null)
  const sheetRef = useRef(null)

  useEffect(() => { setMenu(false); setSheet(false) }, [pathname])

  useEffect(() => {
    if (!menu && !sheet) return
    const onDown = (e) => { if (menu && !groupRef.current?.contains(e.target)) setMenu(false) }
    const onKey = (e) => { if (e.key === 'Escape') { setMenu(false); setSheet(false) } }
    document.addEventListener('pointerdown', onDown)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('pointerdown', onDown); document.removeEventListener('keydown', onKey) }
  }, [menu, sheet])

  useEffect(() => { if (sheet) sheetRef.current?.focus() }, [sheet])

  const inMore = MORE.some(([to]) => isCurrent(to, pathname))
  const hideTabs = /^\/partes\/[^/]+$/.test(pathname) // the work order has its own bottom action bar
  const switchLang = () => setLang(lang === 'es' ? 'en' : 'es')

  return (
    <>
      <header className="navbar">
        <nav className="navbar-inner" aria-label={t('nav.main')}>
          <NavLink to="/" className="brand" aria-label="Cuentia">Cuentia<span className="brand-dot">.</span></NavLink>

          <div className="nav-links nav-desktop">
            {MAIN.map(([to, key, icon]) => (
              <NavLink key={to} to={to} end={to === '/'} className={cls}><Icon name={icon} size={18} />{t(key)}</NavLink>
            ))}
            <div className="nav-group" ref={groupRef}>
              <button type="button" className={`navlink nav-trigger${inMore ? ' active' : ''}`} aria-haspopup="menu" aria-expanded={menu} onClick={() => setMenu((o) => !o)}>
                {t('nav.more')}<Icon name="chevronDown" size={14} className="nav-caret" />
              </button>
              {menu && (
                <div className="nav-menu" role="menu">
                  {MORE.map(([to, key, icon]) => (
                    <NavLink key={to} to={to} role="menuitem" className={({ isActive }) => (isActive ? 'nav-menu-item active' : 'nav-menu-item')}>
                      <Icon name={icon} size={18} />{t(key)}
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="nav-actions">
            <span className="nav-user nav-desktop" title={user?.email}>{user?.email}</span>
            <button className="btn btn-sm icon-btn" onClick={toggle} aria-label={t('nav.theme')} title={t('nav.theme')}>
              <Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />
            </button>
            <button className="btn btn-sm icon-btn nav-desktop" onClick={switchLang} title="Change language / Cambiar idioma">{lang === 'es' ? 'EN' : 'ES'}</button>
            <button className="btn btn-sm nav-desktop" onClick={logout}><Icon name="logout" size={18} />{t('auth.logout')}</button>
          </div>
        </nav>
      </header>

      {!hideTabs && (
        <nav className="tabbar" aria-label={t('nav.main')}>
          {MAIN.map(([to, key, icon]) => (
            <NavLink key={to} to={to} end={to === '/'} className={({ isActive }) => `tab${isActive ? ' active' : ''}`}>
              <Icon name={icon} size={22} /><span>{t(key)}</span>
            </NavLink>
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
            <div className="sheet-grid">
              {MORE.map(([to, key, icon]) => (
                <NavLink key={to} to={to} className={({ isActive }) => `sheet-item${isActive ? ' active' : ''}`}><Icon name={icon} size={22} />{t(key)}</NavLink>
              ))}
            </div>
            <div className="sheet-actions">
              <button type="button" className="btn" onClick={toggle}><Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />{t(theme === 'dark' ? 'nav.themeLight' : 'nav.themeDark')}</button>
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
