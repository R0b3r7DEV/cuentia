import { NavLink } from 'react-router-dom'

// Active link is highlighted so the user always knows where they are.
// ES: El enlace activo se resalta para que el usuario sepa siempre dónde está.
const cls = ({ isActive }) => (isActive ? 'navlink active' : 'navlink')

export default function Navbar() {
  return (
    <header className="navbar">
      <nav className="navbar-inner">
        <span className="brand">Cuentia<span className="brand-dot">.</span></span>
        <NavLink to="/" className={cls} end>Movements</NavLink>
        <NavLink to="/dashboard" className={cls}>Dashboard</NavLink>
        <NavLink to="/taxes" className={cls}>Taxes</NavLink>
      </nav>
    </header>
  )
}
