import { NavLink } from 'react-router-dom'

// Active link is highlighted so the user always knows where they are.
// ES: El enlace activo se resalta para que el usuario sepa siempre dónde está.
const linkStyle = ({ isActive }) => ({
  padding: '6px 12px',
  borderRadius: 8,
  textDecoration: 'none',
  fontSize: 14,
  fontWeight: 600,
  color: isActive ? '#111' : '#666',
  background: isActive ? '#eef2ff' : 'transparent',
})

export default function Navbar() {
  return (
    <header style={{ borderBottom: '1px solid #eee', background: '#fff' }}>
      <nav style={{ maxWidth: 1000, margin: '0 auto', padding: '0.75rem 2rem', display: 'flex', alignItems: 'center', gap: 16 }}>
        <span style={{ fontWeight: 800, fontSize: 18, marginRight: 8 }}>Cuentia</span>
        <NavLink to="/" style={linkStyle} end>Movements</NavLink>
        <NavLink to="/dashboard" style={linkStyle}>Dashboard</NavLink>
        <NavLink to="/taxes" style={linkStyle}>Taxes</NavLink>
      </nav>
    </header>
  )
}
