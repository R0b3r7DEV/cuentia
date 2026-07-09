import { Canvas } from '@react-three/fiber'
import { OrbitControls } from '@react-three/drei'

// Lazy-loaded on purpose (Three.js is heavy): this whole module becomes its own chunk.
// ES: Cargado en diferido a propósito (Three.js pesa): este módulo es su propio chunk.

const WALL_H = 2.5, WALL_T = 0.1
const HEIGHTS = { socket: 0.3, switch: 1.1, light: 2.4, panel: 1.2 }
const COLORS = { socket: '#2a78d6', switch: '#12a150', light: '#e8a13a', panel: '#5b6472' }

function Walls({ room }) {
  const { x, y, w, h } = room
  const wall = (cx, cz, sx, sz, key) => (
    <mesh key={key} position={[cx, WALL_H / 2, cz]}>
      <boxGeometry args={[sx, WALL_H, sz]} />
      <meshStandardMaterial color="#c9d2df" transparent opacity={0.3} />
    </mesh>
  )
  return (
    <group>
      {wall(x + w / 2, y, w, WALL_T, 'n')}
      {wall(x + w / 2, y + h, w, WALL_T, 's')}
      {wall(x, y + h / 2, WALL_T, h, 'w')}
      {wall(x + w, y + h / 2, WALL_T, h, 'e')}
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
  const rooms = layout?.rooms || []
  const devices = layout?.devices || []
  const panel = layout?.panel

  let maxX = 8, maxZ = 6
  rooms.forEach((r) => { maxX = Math.max(maxX, r.x + r.w); maxZ = Math.max(maxZ, r.y + r.h) })
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
          <meshStandardMaterial color="#eef2f8" />
        </mesh>
        <gridHelper args={[grid, grid, '#a9b4c6', '#d3dae6']} position={[cx, 0.02, cz]} />
        {rooms.map((r, i) => <Walls key={i} room={r} />)}
        {devices.map((d, i) => <Device key={i} d={d} />)}
        {panel && <Device d={{ ...panel, type: 'panel' }} />}
        <OrbitControls target={[cx, 1, cz]} makeDefault />
      </Canvas>
    </div>
  )
}
