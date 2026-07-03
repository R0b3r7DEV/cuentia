import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Layout from './components/Layout'
import MovementsPage from './pages/MovementsPage'
import DashboardPage from './pages/DashboardPage'
import TaxesPage from './pages/TaxesPage'
import ChatPage from './pages/ChatPage'
import AccountPage from './pages/AccountPage'
import AuthPage from './pages/AuthPage'
import { useAuth } from './auth/AuthContext'

// Gate the app behind authentication: logged-out users see the login/register screen.
// ES: Protege la app tras la autenticación: los no logueados ven la pantalla de login/registro.
export default function App() {
  const { user, loading } = useAuth()

  if (loading) return null
  if (!user) return <AuthPage />

  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Layout />}>
          <Route path="/" element={<MovementsPage />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/taxes" element={<TaxesPage />} />
          <Route path="/chat" element={<ChatPage />} />
          <Route path="/account" element={<AccountPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
