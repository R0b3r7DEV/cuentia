import { createContext, useContext, useEffect, useState } from 'react'

const ThemeContext = createContext(null)

/**
 * Light/dark theme with a manual toggle that persists. Dark is the default: the app is used at home on a
 * computer, and its glass style shines on black. Light stays one tap away.
 * ES: Tema claro/oscuro con un botón que se recuerda. Oscuro por defecto: la app se usa en casa, en el
 * ordenador, y el estilo de cristal luce sobre negro. El claro sigue a un toque.
 */
export function ThemeProvider({ children }) {
  const [theme, setTheme] = useState(() => {
    try {
      const saved = localStorage.getItem('theme')
      if (saved === 'light' || saved === 'dark') return saved
    } catch { /* storage blocked: fall back to dark */ }
    return 'dark'
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
