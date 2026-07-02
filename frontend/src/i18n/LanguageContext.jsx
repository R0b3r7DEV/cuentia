import { createContext, useContext, useEffect, useState } from 'react'
import { translations } from './translations'

const LanguageContext = createContext(null)

// Replace {placeholders} in a string with values from `params`.
// ES: Sustituye {marcadores} en una cadena por los valores de `params`.
function format(str, params) {
  if (!params) return str
  return str.replace(/\{(\w+)\}/g, (_, k) => (params[k] ?? `{${k}}`))
}

export function LanguageProvider({ children }) {
  // Remember the choice across visits. / Recuerda la elección entre visitas.
  const [lang, setLang] = useState(() => localStorage.getItem('lang') || 'es')

  useEffect(() => { localStorage.setItem('lang', lang) }, [lang])

  // t('key', { param }) → translated string. Falls back to the key if missing.
  // ES: t('clave', { param }) → cadena traducida. Si falta, devuelve la clave.
  const t = (key, params) => format(translations[lang]?.[key] ?? key, params)

  return (
    <LanguageContext.Provider value={{ lang, setLang, t }}>
      {children}
    </LanguageContext.Provider>
  )
}

export function useTranslation() {
  const ctx = useContext(LanguageContext)
  if (!ctx) throw new Error('useTranslation must be used within a LanguageProvider')
  return ctx
}
