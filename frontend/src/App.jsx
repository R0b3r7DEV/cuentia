import { useEffect, useState } from 'react'
import './App.css'

function App() {
  // health = the JSON returned by the backend; error = a message if the call failed.
  // ES: health = el JSON que devuelve el backend; error = un mensaje si la llamada falla.
  const [health, setHealth] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    // Call the backend through the Vite proxy: /api -> http://127.0.0.1:8000
    // ES: Llama al backend a través del proxy de Vite: /api -> http://127.0.0.1:8000
    fetch('/api/health')
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        return res.json()
      })
      .then(setHealth)
      .catch((err) => setError(err.message))
  }, [])

  return (
    <main style={{ fontFamily: 'system-ui, sans-serif', padding: '3rem', textAlign: 'center' }}>
      <h1 style={{ marginBottom: '0.25rem' }}>Cuentia</h1>
      <p style={{ color: '#666' }}>AI cash-flow &amp; tax copilot — work in progress</p>

      {health && (
        <p style={{ color: '#16a34a', fontWeight: 700 }}>
          ✅ API OK — {health.service} ({health.status})
        </p>
      )}
      {error && (
        <p style={{ color: '#dc2626', fontWeight: 700 }}>
          ❌ API not reachable: {error}
        </p>
      )}
      {!health && !error && <p>Checking API…</p>}
    </main>
  )
}

export default App
