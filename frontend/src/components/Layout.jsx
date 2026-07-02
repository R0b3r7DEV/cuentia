import { Outlet } from 'react-router-dom'
import Navbar from './Navbar'
import { useFinanceData } from '../hooks/useFinanceData'

/**
 * App shell: the navbar plus the current page (<Outlet/>). Loads data once and shares
 * it (and reload) with every page via the Outlet context.
 * ES: Estructura de la app: navbar + página actual (<Outlet/>). Carga los datos una vez y
 * los comparte (y reload) con cada página vía el contexto del Outlet.
 */
export default function Layout() {
  const data = useFinanceData()

  return (
    <>
      <Navbar />
      <main className="app-main">
        {data.error && <p className="msg error">API error: {data.error}</p>}
        <Outlet context={data} />
      </main>
    </>
  )
}
