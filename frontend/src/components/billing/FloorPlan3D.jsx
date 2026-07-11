import { Canvas } from '@react-three/fiber'
import { OrbitControls } from '@react-three/drei'
import { isFinitePoint as finite, toPolygonPoints as toPoints } from '../../lib/geometry'

// Lazy-loaded on purpose (Three.js is heavy): this whole module becomes its own chunk.
// ES: Cargado en diferido a propósito (Three.js pesa): este módulo es su propio chunk.

const WALL_H = 2.5, WALL_T = 0.1
const HEIGHTS = { socket: 0.3, switch: 1.1, light: 2.4, panel: 1.2 }
// Matches the app palette: indigo accent, teal "positive", ochre for the lit points.
// ES: Coincide con la paleta de la app: índigo, verde-azulado y ocre para los puntos de luz.
const COLORS = { socket: '#443ea8', switch: '#0f6b54', light: '#d09853', panel: '#56506e' }

/** One wall per polygon edge: a box of the edge's length, spun about Y to lie along it. */
/** ES: Un muro por arista: una caja del largo de la arista, girada sobre Y para tumbarse sobre ella. */
function Walls({ points }) {
  return (
    <group>
      {points.map((a, i) => {
        const b = points[(i + 1) % points.length]
        const dx = b.x - a.x, dz = b.y - a.y
        const len = Math.hypot(dx, dz)
        if (len < 0.01) return null
        return (
          <mesh key={i} position={[(a.x + b.x) / 2, WALL_H / 2, (a.y + b.y) / 2]} rotation={[0, Math.atan2(-dz, dx), 0]}>
            <boxGeometry args={[len, WALL_H, WALL_T]} />
            <meshStandardMaterial color="#d3cde0" transparent opacity={0.3} />
          </mesh>
        )
      })}
    </group>
  )
}

function Device({ d }) {
  const yy = HEIGHTS[d.type] ?? 0.3
  const color = COLORS[d.type] ?? '#888888'
  const size = d.type === 'panel' ? [0.32, 0.42, 0.1] : d.type === 'light' ? [0.18, 0.18, 0.18] : [0.13, 0.13, 0.13]
  const isLight = d.type === 'light'
  return (
    <mesh position={[d.x, yy, d.y]}>
      <boxGeometry args={size} />
      <meshStandardMaterial color={color} emissive={isLight ? color : '#000000'} emissiveIntensity={isLight ? 0.7 : 0} />
    </mesh>
  )
}

export default function FloorPlan3D({ layout }) {
  const polys = (layout?.rooms || []).map(toPoints).filter((p) => p.length >= 3)
  const devices = (layout?.devices || []).filter(finite)
  const panel = finite(layout?.panel) ? layout.panel : null

  // Every coordinate is screened for NaN before it reaches the bounds. A single NaN here propagates to the
  // camera position and Three.js renders nothing at all — a blank canvas, not a missing wall.
  // ES: Se filtra todo NaN antes de los límites. Uno solo llega a la cámara y Three.js no dibuja NADA:
  // el lienzo sale en blanco, no un muro de menos.
  let maxX = 8, maxZ = 6
  polys.forEach((p) => p.forEach((q) => { maxX = Math.max(maxX, q.x); maxZ = Math.max(maxZ, q.y) }))
  devices.forEach((d) => { maxX = Math.max(maxX, d.x); maxZ = Math.max(maxZ, d.y) })
  const cx = maxX / 2, cz = maxZ / 2
  const span = Math.max(maxX, maxZ)
  const grid = Math.ceil(span) + 6

  return (
    <div className="floorplan3d">
      <Canvas camera={{ position: [cx + span * 0.85, span * 0.95, cz + span * 1.15], fov: 45 }}>
        <ambientLight intensity={0.75} />
        <directionalLight position={[cx + 6, 14, cz + 4]} intensity={1.1} />
        <mesh rotation={[-Math.PI / 2, 0, 0]} position={[cx, 0, cz]}>
          <planeGeometry args={[maxX + 4, maxZ + 4]} />
          <meshStandardMaterial color="#f0ecf6" />
        </mesh>
        <gridHelper args={[grid, grid, '#b6acc9', '#ddd6e8']} position={[cx, 0.02, cz]} />
        {polys.map((p, i) => <Walls key={i} points={p} />)}
        {devices.map((d, i) => <Device key={i} d={d} />)}
        {panel && <Device d={{ ...panel, type: 'panel' }} />}
        <OrbitControls target={[cx, 1, cz]} makeDefault />
      </Canvas>
    </div>
  )
}
