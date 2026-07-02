import { Outlet } from 'react-router-dom'
import Navbar from './Navbar'
import { useFinanceData } from '../hooks/useFinanceData'

/**
 * App shell: the navbar plus the current page (<Outlet/>). It loads the data once and
 * shares it (and reload) with every page via the Outlet context, so navigating between
 * pages doesn't refetch and an import on one page updates them all.
 * ES: Estructura de la app: la navbar más la página actual (<Outlet/>). Carga los datos una
 * vez y los comparte (y reload) con cada página vía el contexto del Outlet.
 */
export default function Layout() {
  const data = useFinanceData()

  return (
    <>
      <Navbar />
      <main style={{ fontFamily: 'system-ui, sans-serif', maxWidth: 1000, margin: '0 auto', padding: '2rem' }}>
        {data.error && <p style={{ color: '#dc2626' }}>API error: {data.error}</p>}
        <Outlet context={data} />
      </main>
    </>
  )
}
