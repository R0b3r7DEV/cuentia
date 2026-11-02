import { BrowserRouter, Navigate, Routes, Route } from 'react-router-dom'
import Layout from './components/Layout'
import MovementsPage from './pages/MovementsPage'
import DashboardPage from './pages/DashboardPage'
import TaxesPage from './pages/TaxesPage'
import BillingPage from './pages/BillingPage'
import CertificatesPage from './pages/CertificatesPage'
import InstallationPage from './pages/InstallationPage'
import ChatPage from './pages/ChatPage'
import WorkOrdersPage from './pages/WorkOrdersPage'
import WorkOrderPage from './pages/WorkOrderPage'
import MyDataPage from './pages/MyDataPage'
import CertificateGvaPage from './pages/CertificateGvaPage'
import AccountPage from './pages/AccountPage'
import AuthPage from './pages/AuthPage'
import TodayPage from './pages/TodayPage'
import LandingPage from './pages/LandingPage'
import { useAuth } from './auth/AuthContext'

// Logged out: the public landing page, and the login behind its "Entrar". Logged in: the app.
// ES: Sin sesión, la portada pública y el login tras su «Entrar». Con sesión, la app.
export default function App() {
  const { user, loading } = useAuth()

  if (loading) return null
  // A password-reset link opens the reset form even if this browser is still logged in.
  // ES: Un enlace de recuperación abre el formulario aunque este navegador siga con sesión.
  const resetting = window.location.pathname.startsWith('/restablecer')

  if (!user || resetting) {
    return (
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<LandingPage />} />
          <Route path="/entrar" element={<AuthPage />} />
          <Route path="/registro" element={<AuthPage initialMode="register" />} />
          <Route path="/restablecer" element={<AuthPage />} />
          {/* any app address while logged out (an old bookmark, a shared link) asks to log in first */}
          <Route path="*" element={<Navigate to="/entrar" replace />} />
        </Routes>
      </BrowserRouter>
    )
  }

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/entrar" element={<Navigate to="/" replace />} />
        <Route path="/registro" element={<Navigate to="/" replace />} />
        <Route element={<Layout />}>
          <Route path="/" element={<TodayPage />} />
          <Route path="/movements" element={<MovementsPage />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/taxes" element={<TaxesPage />} />
          <Route path="/invoices" element={<BillingPage />} />
          <Route path="/partes" element={<WorkOrdersPage />} />
          <Route path="/partes/:id" element={<WorkOrderPage />} />
          <Route path="/certificates" element={<CertificatesPage />} />
          <Route path="/certificates/:id/gva" element={<CertificateGvaPage />} />
          <Route path="/installation" element={<InstallationPage />} />
          <Route path="/chat" element={<ChatPage />} />
          <Route path="/mis-datos" element={<MyDataPage />} />
          <Route path="/account" element={<AccountPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
