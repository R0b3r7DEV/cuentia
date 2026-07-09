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

  /** POST a JSON body; on failure throw an Error carrying the HTTP status and the backend's `code`. */
  const post = async (url, body) => {
    let r
    try {
      r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      })
    } catch {
      const err = new Error('network')
      err.code = 'network_error'
      throw err
    }
    if (!r.ok) {
      const data = await r.json().catch(() => ({}))
      const err = new Error(data.error || 'error')
      err.status = r.status
      err.code = data.code
      throw err
    }
    return r
  }

  const login = async (email, password) => {
    const r = await post('/api/login', { email, password })
    setUser(await r.json())
  }

  const register = async (email, password) => {
    await post('/api/register', { email, password })
    await login(email, password) // auto-login after registering
  }

  const logout = async () => {
    await fetch('/api/logout', { method: 'POST' })
    setUser(null)
  }

  const deleteAccount = async () => {
    await fetch('/api/account', { method: 'DELETE' })
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, deleteAccount }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within an AuthProvider')
  return ctx
}
