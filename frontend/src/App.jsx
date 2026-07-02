import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Layout from './components/Layout'
import MovementsPage from './pages/MovementsPage'
import DashboardPage from './pages/DashboardPage'
import TaxesPage from './pages/TaxesPage'

// Routing: the Layout (navbar + shared data) wraps the three pages.
// ES: Enrutado: el Layout (navbar + datos compartidos) envuelve las tres páginas.
export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Layout />}>
          <Route path="/" element={<MovementsPage />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/taxes" element={<TaxesPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
