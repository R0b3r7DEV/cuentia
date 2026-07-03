import { createContext, useContext, useEffect, useState } from 'react'

const AuthContext = createContext(null)

/**
 * Holds the current user and the login/register/logout actions.
 * On mount it asks the backend who's logged in (session cookie).
 * ES: Guarda el usuario actual y las acciones login/register/logout.
 * Al montar, pregunta al backend quién está logueado (cookie de sesión).
 */
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetch('/api/me')
      .then((r) => (r.ok ? r.json() : null))
      .then(setUser)
      .catch(() => setUser(null))
      .finally(() => setLoading(false))
  }, [])

  const login = async (email, password) => {
    const r = await fetch('/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })
    if (!r.ok) throw new Error('invalid')
    setUser(await r.json())
  }

  const register = async (email, password) => {
    const r = await fetch('/api/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })
    const data = await r.json().catch(() => ({}))
    if (!r.ok) throw new Error(data.error || 'error')
    await login(email, password) // auto-login after registering
  }

  const logout = async () => {
    await fetch('/api/logout', { method: 'POST' })
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within an AuthProvider')
  return ctx
}
