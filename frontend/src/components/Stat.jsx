// A small labelled figure (stat tile). / Una cifra con etiqueta (tarjeta de estadística).
export default function Stat({ label, value, color = '#111' }) {
  return (
    <div>
      <div style={{ fontSize: 12, textTransform: 'uppercase', color: '#999', letterSpacing: 0.5 }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 700, color }}>{value}</div>
    </div>
  )
}
