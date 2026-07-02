// A small labelled figure (stat tile). / Una cifra con etiqueta (tarjeta de estadística).
export default function Stat({ label, value, color }) {
  return (
    <div>
      <div className="stat-label">{label}</div>
      <div className="stat-value" style={color ? { color } : undefined}>{value}</div>
    </div>
  )
}
