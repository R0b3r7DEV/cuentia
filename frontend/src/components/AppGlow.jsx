/**
 * The light behind the glass: blurred cobalt and lime sources and a faint single-line diagram (conductors and
 * junctions), fixed behind every screen. Without colour behind it, glass does not show. Decorative only.
 * ES: La luz detrás del cristal: focos difuminados en cobalto y lima y un esquema unifilar tenue (conductores
 * y empalmes), fijo detrás de cada pantalla. Sin color detrás, el cristal no se nota. Solo decorativo.
 */
export default function AppGlow() {
  return (
    <div className="app-glow" aria-hidden="true">
      <i className="glow-a" /><i className="glow-b" /><i className="glow-c" />
      <svg viewBox="0 0 1200 900" preserveAspectRatio="xMidYMid slice" fill="none" strokeWidth="1.2">
        <path d="M0 120 H420 V420 H980 V700 H1200" /><path d="M80 0 V260 H260 V900" />
        <path d="M1200 300 H860 V560 H520 V900" /><path d="M0 640 H360 V820 H760" />
        <circle cx="420" cy="420" r="4" /><circle cx="980" cy="700" r="4" /><circle cx="260" cy="260" r="4" />
        <circle cx="860" cy="560" r="4" /><circle cx="360" cy="820" r="4" />
      </svg>
    </div>
  )
}
