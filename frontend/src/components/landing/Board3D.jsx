import { useEffect, useMemo, useRef, useState } from 'react'
import { Canvas, useFrame, useThree } from '@react-three/fiber'
import * as THREE from 'three'
import { CIRCUITS, isLive } from '../../lib/board'

// Lazy-loaded on purpose (Three.js is heavy): this module is its own chunk, shared with the floor plan's 3D.
// ES: Cargado en diferido a propósito (Three.js pesa): este módulo es su propio chunk.

const MOD = 1.8 // one DIN module / un módulo DIN
const W = 21.5 // enclosure inner width / ancho interior del cuadro

/** A text label painted on a canvas, as a texture. / Una etiqueta pintada en un canvas, como textura. */
function useLabel(text, bg, fg, w = 256, h = 64, font = '600 36px "IBM Plex Mono", monospace') {
  return useMemo(() => {
    const c = document.createElement('canvas')
    c.width = w; c.height = h
    const x = c.getContext('2d')
    x.fillStyle = bg; x.fillRect(0, 0, w, h)
    x.fillStyle = fg; x.font = font; x.textAlign = 'center'; x.textBaseline = 'middle'
    x.fillText(text, w / 2, h / 2 + 2)
    const tex = new THREE.CanvasTexture(c)
    tex.colorSpace = THREE.SRGBColorSpace // true colours for the labels / colores reales en las etiquetas
    tex.anisotropy = 4
    return tex
  }, [text, bg, fg, w, h, font])
}

function Label({ text, width, height, y, bg = '#d4ff24', fg = '#030407', z = 1.62, transparent = false }) {
  const map = useLabel(text, bg, fg)
  return (
    <mesh position={[0, y, z]}>
      <planeGeometry args={[width, height]} />
      <meshBasicMaterial map={map} transparent={transparent} />
    </mesh>
  )
}

/** One modular device: body, lever (animated), status flag (green I / red O) and LED. */
function Device({ id, x, tag, rating, on, live, hovered, reduce, onAct, onHover, children }) {
  const pivot = useRef()
  const group = useRef()
  const w = 2 * MOD - 0.12
  const flagOn = useLabel('I', '#1e9e4a', '#ffffff', 64, 64, '700 44px "IBM Plex Mono", monospace')
  const flagOff = useLabel('O', '#d63a2f', '#ffffff', 64, 64, '700 44px "IBM Plex Mono", monospace')
  useFrame((_, dt) => {
    const target = on ? 0 : Math.PI * 0.82
    const k = reduce ? 1 : Math.min(1, dt * 18)
    pivot.current.rotation.x += (target - pivot.current.rotation.x) * k
    group.current.position.z += ((hovered ? 0.35 : 0) - group.current.position.z) * (reduce ? 1 : Math.min(1, dt * 12))
  })
  const handlers = {
    onClick: (e) => { e.stopPropagation(); onAct(id) },
    onPointerOver: (e) => { e.stopPropagation(); onHover(id) },
    onPointerOut: () => onHover(null),
  }
  return (
    <group ref={group} position={[x, 0, 0]}>
      <mesh {...handlers}><boxGeometry args={[w, 9.4, 3.2]} /><meshStandardMaterial color="#eef0f3" roughness={0.55} /></mesh>
      <mesh position={[0, 0.2, 1.62]} {...handlers}><boxGeometry args={[w - 0.5, 3.2, 0.1]} /><meshStandardMaterial color="#d7dbe1" roughness={0.5} /></mesh>
      <mesh position={[0, 0.2, 1.68]} {...handlers}><boxGeometry args={[1.2, 2.8, 0.2]} /><meshStandardMaterial color="#9aa1ad" roughness={0.6} /></mesh>
      <group ref={pivot} position={[0, 0.2, 1.75]} rotation={[on ? 0 : Math.PI * 0.82, 0, 0]}>
        <mesh position={[0, 0.75, 0]} {...handlers}><boxGeometry args={[0.95, 1.6, 0.9]} /><meshStandardMaterial color="#030407" roughness={0.4} /></mesh>
      </group>
      <mesh position={[w / 2 - 0.65, 1.25, 1.7]}>
        <planeGeometry args={[0.75, 0.75]} />
        <meshBasicMaterial map={on ? flagOn : flagOff} />
      </mesh>
      <Label text={tag} width={Math.min(w - 0.3, 3.2)} height={0.8} y={3.4} />
      <Label text={rating} width={Math.min(w - 0.3, 3.2)} height={0.7} y={-2.4} bg="#eef0f3" fg="#4f5560" />
      <mesh position={[0, -3.5, 1.7]}>
        <sphereGeometry args={[0.28, 16, 16]} />
        <meshStandardMaterial color={live ? '#9be000' : '#3a404b'} emissive={live ? '#557a00' : '#000000'} roughness={0.3} />
      </mesh>
      {children}
    </group>
  )
}

/** A cable from a breaker, with current (lime dots) running along it while the circuit is live. */
function Cable({ from, to, live, reduce }) {
  const curve = useMemo(() => new THREE.CatmullRomCurve3([
    new THREE.Vector3(from, -4.7, 0.6), new THREE.Vector3(from, -7.5, 0.4),
    new THREE.Vector3((from + to) / 2, -10.5, 2), new THREE.Vector3(to, -16, 3),
  ]), [from, to])
  const tube = useMemo(() => new THREE.TubeGeometry(curve, 40, 0.32, 10, false), [curve])
  const dots = useRef([])
  const phase = useRef(Array.from({ length: 7 }, (_, i) => i / 7))
  useFrame((_, dt) => {
    dots.current.forEach((d, i) => {
      if (!d) return
      d.visible = live
      if (!live) return
      if (!reduce) phase.current[i] = (phase.current[i] + dt * 0.28) % 1
      d.position.copy(curve.getPointAt(phase.current[i]))
    })
  })
  return (
    <group>
      <mesh geometry={tube}><meshStandardMaterial color="#1c2230" roughness={0.5} emissive={live ? '#1d2600' : '#000000'} /></mesh>
      {phase.current.map((_, i) => (
        <mesh key={i} ref={(m) => { dots.current[i] = m }}>
          <sphereGeometry args={[0.34, 12, 12]} />
          <meshBasicMaterial color="#d4ff24" />
        </mesh>
      ))}
    </group>
  )
}

/** Keeps the whole enclosure in the box's width, centred. / Mantiene el cuadro entero y centrado. */
function FitCamera() {
  const { camera, size } = useThree()
  useEffect(() => {
    const half = ((W + 3.4) / 2) * 1.06
    const fit = half / (Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * (size.width / size.height))
    camera.position.set(0, 0, Math.max(36, fit))
    camera.lookAt(0, 0, 0)
    camera.updateProjectionMatrix()
  }, [camera, size])
  return null
}

function Board({ state, onAct, reduce, labels, shakeAt }) {
  const root = useRef()
  const lime = useRef()
  const [hover, setHover] = useState(null)
  const x0 = -W / 2 + 0.25
  const xs = { iga: x0 + MOD + 0.1, id: x0 + 3 * MOD + 0.3 }
  CIRCUITS.forEach((k, i) => { xs[k] = x0 + 4 * MOD + 0.6 + i * 2 * MOD + MOD + i * 0.25 })
  const ratings = { iga: '25 A · 2P', id: '40 A · 30 mA', wo: 'C1 · 16 A', inv: 'C2 · 16 A', cie: 'C3 · 16 A' }
  const lit = CIRCUITS.filter((k) => isLive(state, k)).length

  useEffect(() => { document.body.style.cursor = hover ? 'pointer' : '' ; return () => { document.body.style.cursor = '' } }, [hover])

  useFrame(({ pointer, clock }, dt) => {
    const t = clock.elapsedTime
    // shakeAt holds performance.now() of the trip / momento del disparo
    const since = shakeAt.current ? (performance.now() - shakeAt.current) / 1000 : 99
    const shake = since < 0.35 && !reduce ? Math.sin(t * 90) * 0.06 * (1 - since / 0.35) : 0
    const ax = reduce ? 0 : -pointer.y * 0.12, ay = reduce ? 0 : pointer.x * 0.22
    root.current.rotation.x += (ax + (reduce ? 0 : Math.sin(t * 0.5) * 0.02) - root.current.rotation.x) * Math.min(1, dt * 3)
    root.current.rotation.y += (ay - 0.05 - root.current.rotation.y) * Math.min(1, dt * 3)
    root.current.rotation.y += shake
    lime.current.intensity += ((lit ? 12 + lit * 8 : 0) - lime.current.intensity) * Math.min(1, dt * 6)
  })

  return (
    <>
      {/* lights tuned for three's physical units / luces ajustadas a las unidades físicas de three */}
      <ambientLight color="#c8d0ff" intensity={1.1} />
      <directionalLight position={[-12, 18, 24]} intensity={2.4} />
      <pointLight position={[-20, -6, 10]} color="#1558c9" intensity={60} distance={80} decay={1.2} />
      <pointLight ref={lime} position={[6, -8, 10]} color="#d4ff24" intensity={0} distance={40} decay={1.2} />
      <group ref={root} onPointerMissed={() => setHover(null)}>
        <mesh position={[0, 0, -1.9]}><boxGeometry args={[W + 1.6, 13.5, 0.6]} /><meshStandardMaterial color="#12161e" roughness={0.85} /></mesh>
        {[[0, 7.1, W + 2.4, 0.9], [0, -7.1, W + 2.4, 0.9]].map(([x, y, w, h]) => (
          <mesh key={y} position={[x, y, -1.2]}><boxGeometry args={[w, h, 1.6]} /><meshStandardMaterial color="#232a36" roughness={0.5} metalness={0.2} /></mesh>
        ))}
        {[-(W / 2 + 0.75), W / 2 + 0.75].map((x) => (
          <mesh key={x} position={[x, 0, -1.2]}><boxGeometry args={[0.9, 15.1, 1.6]} /><meshStandardMaterial color="#232a36" roughness={0.5} metalness={0.2} /></mesh>
        ))}
        <mesh position={[0, 0, -1.45]}><boxGeometry args={[W, 1.3, 0.3]} /><meshStandardMaterial color="#b9c0cc" roughness={0.35} metalness={0.8} /></mesh>

        <Device id="iga" x={xs.iga} tag="IGA" rating={ratings.iga} on={state.iga} live={isLive(state, 'iga')}
          hovered={hover === 'iga'} reduce={reduce} onAct={onAct} onHover={setHover} />
        <Device id="id" x={xs.id} tag="ID" rating={ratings.id} on={state.id} live={isLive(state, 'id')}
          hovered={hover === 'id' || hover === 'test'} reduce={reduce} onAct={onAct} onHover={setHover}>
          {/* the RCD's test button / botón de prueba del diferencial */}
          <mesh position={[0, -1.4, 1.75]} onClick={(e) => { e.stopPropagation(); onAct('test') }}
            onPointerOver={(e) => { e.stopPropagation(); setHover('test') }} onPointerOut={() => setHover(null)}>
            <boxGeometry args={[0.9, 0.7, 0.5]} />
            <meshStandardMaterial color="#f2c500" roughness={0.4} />
          </mesh>
          <Label text="T" width={0.7} height={0.55} y={-1.4} z={2.02} bg="#f2c500" />
        </Device>
        {CIRCUITS.map((k) => (
          <Device key={k} id={k} x={xs[k]} tag={labels[k]} rating={ratings[k]} on={state[k]} live={isLive(state, k)}
            hovered={hover === k} reduce={reduce} onAct={onAct} onHover={setHover} />
        ))}
        {CIRCUITS.map((k, i) => <Cable key={k} from={xs[k]} to={-9 + i * 9} live={isLive(state, k)} reduce={reduce} />)}
      </group>
    </>
  )
}

/**
 * The landing page's distribution board in 3D. Purely visual: the same actions are buttons in the page, for
 * the keyboard and screen readers. / El cuadro de la portada en 3D. Solo visual: las mismas acciones son
 * botones en la página, para teclado y lectores de pantalla.
 */
export default function Board3D({ state, onAct, labels, shakeAt }) {
  const reduce = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  return (
    <Canvas flat className="board3d" aria-hidden="true" dpr={[1, 2]} camera={{ fov: 32, near: 0.1, far: 200, position: [0, 0, 36] }}
      gl={{ antialias: true, alpha: true }}>
      <FitCamera />
      <Board state={state} onAct={onAct} reduce={reduce} labels={labels} shakeAt={shakeAt} />
    </Canvas>
  )
}
