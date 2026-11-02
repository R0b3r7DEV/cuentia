import { createContext, useContext, useEffect, useState } from 'react'

const ThemeContext = createContext(null)

/**
 * Light/dark theme with a manual toggle that persists. Light is the default whatever the OS prefers: the app
 * is used on site, often in the sun, where the light theme reads best.
 * ES: Tema claro/oscuro con un botón que se recuerda. Claro por defecto aunque el SO prefiera oscuro: la app
 * se usa en la obra, muchas veces al sol, y ahí se lee mejor el claro.
 */
export function ThemeProvider({ children }) {
  const [theme, setTheme] = useState(() => {
    try {
      const saved = localStorage.getItem('theme')
      if (saved === 'light' || saved === 'dark') return saved
    } catch { /* storage blocked: fall back to light */ }
    return 'light'
  })

  useEffect(() => {
    document.documentElement.dataset.theme = theme // drives the [data-theme] CSS
    localStorage.setItem('theme', theme)
  }, [theme])

  const toggle = () => setTheme((t) => (t === 'dark' ? 'light' : 'dark'))

  return <ThemeContext.Provider value={{ theme, toggle }}>{children}</ThemeContext.Provider>
}

export function useTheme() {
  const ctx = useContext(ThemeContext)
  if (!ctx) throw new Error('useTheme must be used within a ThemeProvider')
  return ctx
}
