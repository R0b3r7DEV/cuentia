import { useRef, useState } from 'react'
import PlanRectifier from './PlanRectifier'

const SCALES = [30, 42, 58]   // px per metre — zoom steps
const WM = 24, HM = 17        // canvas size in metres
const CLOSE_DIST = 0.4        // click this close to the first vertex to close the polygon (metres)
const snap = (v) => Math.round(v * 4) / 4

const DEVICE_TOOLS = ['socket', 'switch', 'light']
// The circuit a socket hangs from (ITC-BT-25). A socket without one is still accepted: the validator
// credits it against whatever its room still lacks.
const SOCKET_CIRCUITS = ['C2', 'C5', 'C3', 'C4', 'C10']
const ROOM_TYPES = ['salon', 'comedor', 'dormitorio', 'cocina', 'bano', 'pasillo', 'vestibulo', 'terraza', 'garaje', 'trastero']

// ── geometry ────────────────────────────────────────────────────────────────
/** Rooms are polygons. Rectangles saved by older versions are converted on the fly. */
const asPolygon = (r) => (r.points ? r : {
  ...r,
  points: [{ x: r.x, y: r.y }, { x: r.x + r.w, y: r.y }, { x: r.x + r.w, y: r.y + r.h }, { x: r.x, y: r.y + r.h }],
})

/** Shoelace formula — the signed area of any simple polygon. */
const polyArea = (pts) => {
  let s = 0
  for (let i = 0, n = pts.length; i < n; i++) {
    const a = pts[i], b = pts[(i + 1) % n]
    s += a.x * b.y - b.x * a.y
  }
  return Math.round(Math.abs(s / 2) * 10) / 10
}

const centroid = (pts) => ({
  x: pts.reduce((s, p) => s + p.x, 0) / pts.length,
  y: pts.reduce((s, p) => s + p.y, 0) / pts.length,
})

/** Ray casting: is the point inside the polygon? Used to know what a room carries when it moves. */
const pointInPoly = (p, pts) => {
  let inside = false
  for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
    const a = pts[i], b = pts[j]
    if ((a.y > p.y) !== (b.y > p.y) && p.x < ((b.x - a.x) * (p.y - a.y)) / (b.y - a.y) + a.x) inside = !inside
  }
  return inside
}

/** Closest point on segment AB to P, and its squared distance — used to insert a vertex on an edge. */
const projectOnSegment = (p, a, b) => {
  const vx = b.x - a.x, vy = b.y - a.y
  const len2 = vx * vx + vy * vy
  const t = len2 === 0 ? 0 : Math.max(0, Math.min(1, ((p.x - a.x) * vx + (p.y - a.y) * vy) / len2))
  const q = { x: a.x + t * vx, y: a.y + t * vy }
  return { q, d2: (p.x - q.x) ** 2 + (p.y - q.y) ** 2 }
}

const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y)
const svgPoints = (pts, scale) => pts.map((p) => `${p.x * scale},${p.y * scale}`).join(' ')

// ── image helpers ───────────────────────────────────────────────────────────
const readImage = (file) => new Promise((resolve, reject) => {
  const reader = new FileReader()
  reader.onload = () => { const img = new Image(); img.onload = () => resolve(img); img.onerror = reject; img.src = reader.result }
  reader.onerror = reject
  reader.readAsDataURL(file)
})

/**
 * 2D floor-plan editor. Drop the real scanned plan underneath, calibrate its scale against a known
 * dimension, then trace the rooms as **polygons** (an L-shaped living room, a corridor, whatever the plan
 * shows) and place the panel and the devices. Everything in real metres, so cable is measured.
 *
 * ES: Editor 2D. Pon debajo el plano real, calibra su escala con una cota conocida, calca las estancias
 * como **polígonos** (salón en L, pasillo, lo que muestre el plano) y coloca cuadro y dispositivos.
 */
export default function FloorPlanEditor({ layout, background, planTitle, onChange, onBackgroundChange, onRoomsChange, designRooms, resultRooms, t }) {
  const svgRef = useRef(null)
  const drag = useRef(null)
  const suppressClick = useRef(false)
  const [tool, setTool] = useState('drag')
  const [newRoomType, setNewRoomType] = useState('dormitorio')
  const [socketCircuit, setSocketCircuit] = useState('C2')
  const [scale, setScale] = useState(SCALES[1])
  const [calib, setCalib] = useState(null)
  const [realInput, setRealInput] = useState('')
  const [drawing, setDrawing] = useState(null)   // vertices of the polygon being traced
  const [cursor, setCursor] = useState(null)     // rubber-band endpoint while tracing
  const [pending, setPending] = useState(null)   // photo waiting to be de-skewed

  const rooms = (layout?.rooms || []).map(asPolygon)
  const lo = { panel: layout?.panel || { x: 0.5, y: 0.5 }, rooms, devices: layout?.devices || [] }
  const bg = background?.src ? background : null

  const toMetresRaw = (e) => {
    const rect = svgRef.current.getBoundingClientRect()
    return { x: (e.clientX - rect.left) / scale, y: (e.clientY - rect.top) / scale }
  }
  const toMetres = (e) => { const p = toMetresRaw(e); return { x: snap(p.x), y: snap(p.y) } }

  const emit = (patch, roomsChanged = false) => {
    const next = { ...lo, ...patch }
    onChange(next)
    if (roomsChanged && next.rooms.length > 0) {
      onRoomsChange?.(next.rooms.map((r) => ({ type: r.type, area: polyArea(r.points) })))
    }
  }

  // --- background & calibration ---------------------------------------------
  const pickFile = async (e) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    setPending(await readImage(file))
  }

  /** The rectifier hands back a de-skewed (or merely downscaled) image. Drop it in and go calibrate. */
  const useImage = ({ src, w: pxW, h: pxH }) => {
    const w = 12
    setPending(null)
    onBackgroundChange({ src, x: 1, y: 1, w, h: Math.round((w * pxH / pxW) * 100) / 100, opacity: 0.6 })
    setCalib(null)
    setTool('calibrate')
  }

  const calibClick = (p) => {
    if (!calib || calib.b) return setCalib({ a: p, b: null })
    setCalib({ ...calib, b: p })
    setRealInput('')
  }
  const measured = calib?.b ? dist(calib.a, calib.b) : 0
  const cancelCalib = () => { setCalib(null); setRealInput('') }
  const applyCalibration = () => {
    const real = parseFloat(realInput.replace(',', '.'))
    if (!Number.isFinite(real) || real <= 0 || measured < 0.05) return
    const k = real / measured
    const { a } = calib
    const r3 = (v) => Math.round(v * 1000) / 1000
    onBackgroundChange({ ...bg, x: r3(a.x - (a.x - bg.x) * k), y: r3(a.y - (a.y - bg.y) * k), w: r3(bg.w * k), h: r3(bg.h * k) })
    cancelCalib()
    setTool('drag')
  }

  // --- tracing polygons ------------------------------------------------------
  const cancelDraw = () => { setDrawing(null); setCursor(null) }
  const closeDraw = () => {
    if (!drawing || drawing.length < 3) return
    emit({ rooms: [...lo.rooms, { type: newRoomType, points: drawing }] }, true)
    cancelDraw()
  }
  const drawClick = (p) => {
    if (!drawing) return setDrawing([p])
    if (drawing.length >= 3 && dist(p, drawing[0]) < CLOSE_DIST) return closeDraw()
    setDrawing([...drawing, p])
  }

  /** Double-clicking a room inserts a vertex on the nearest edge. */
  const insertVertex = (roomIndex, p) => {
    const pts = lo.rooms[roomIndex].points
    let best = { i: 0, d2: Infinity, q: null }
    for (let i = 0; i < pts.length; i++) {
      const { q, d2 } = projectOnSegment(p, pts[i], pts[(i + 1) % pts.length])
      if (d2 < best.d2) best = { i, d2, q }
    }
    const next = [...pts]
    next.splice(best.i + 1, 0, { x: snap(best.q.x), y: snap(best.q.y) })
    emit({ rooms: lo.rooms.map((r, i) => (i === roomIndex ? { ...r, points: next } : r)) }, true)
  }

  /**
   * Grab the dot in the middle of an edge and a vertex is born there, already being dragged. Two pulls on
   * a rectangle give you an L. / Agarra el punto medio de un lado y nace un vértice ya en arrastre. Dos
   * tirones sobre un rectángulo te dan una L.
   */
  const startMidpoint = (e, roomIndex, edgeIndex) => {
    e.stopPropagation()
    if (tool === 'delete') return
    const pts = lo.rooms[roomIndex].points
    const a = pts[edgeIndex], b = pts[(edgeIndex + 1) % pts.length]
    const mid = { x: snap((a.x + b.x) / 2), y: snap((a.y + b.y) / 2) }
    const next = [...pts]
    next.splice(edgeIndex + 1, 0, mid)
    emit({ rooms: lo.rooms.map((r, i) => (i === roomIndex ? { ...r, points: next } : r)) }, true)
    drag.current = { kind: 'vertex', index: roomIndex, vertexIndex: edgeIndex + 1 }
  }

  /** Double-click a vertex to remove it (a polygon needs at least three). */
  const removeVertex = (e, roomIndex, vertexIndex) => {
    e.stopPropagation()
    const pts = lo.rooms[roomIndex].points
    if (pts.length <= 3) return
    const next = pts.filter((_, i) => i !== vertexIndex)
    emit({ rooms: lo.rooms.map((r, i) => (i === roomIndex ? { ...r, points: next } : r)) }, true)
  }

  // --- interaction -----------------------------------------------------------
  const onCanvasClick = (e) => {
    if (suppressClick.current) { suppressClick.current = false; return }
    if (drag.current) return
    if (tool === 'calibrate') return bg && calibClick(toMetresRaw(e))
    if (tool === 'bg') return
    const p = toMetres(e)
    if (tool === 'poly') return drawClick(p)
    if (tool === 'panel') return emit({ panel: p })
    if (tool === 'room') {
      const rect = [{ x: p.x, y: p.y }, { x: p.x + 4, y: p.y }, { x: p.x + 4, y: p.y + 3 }, { x: p.x, y: p.y + 3 }]
      return emit({ rooms: [...lo.rooms, { type: newRoomType, points: rect }] }, true)
    }
    if (DEVICE_TOOLS.includes(tool)) {
      const device = { type: tool, x: p.x, y: p.y }
      if (tool === 'socket') device.circuit = socketCircuit
      return emit({ devices: [...lo.devices, device] })
    }
  }

  const startDrag = (e, kind, index, vertexIndex) => {
    e.stopPropagation()
    if (tool === 'delete') {
      if (kind === 'device') return emit({ devices: lo.devices.filter((_, i) => i !== index) })
      if (kind === 'room') return emit({ rooms: lo.rooms.filter((_, i) => i !== index) }, true)
    }
    if (kind === 'calibA' || kind === 'calibB') { drag.current = { kind }; return }
    if (kind === 'vertex') { drag.current = { kind, index, vertexIndex }; return }

    const p = toMetres(e)
    if (kind === 'bg') { drag.current = { kind, dx: p.x - bg.x, dy: p.y - bg.y }; return }

    // Moving a room carries whatever sits inside its polygon. Snapshot the originals so each pointer
    // move is computed from them — accumulating deltas would drift with the 25 cm snapping.
    if (kind === 'room') {
      const r = lo.rooms[index]
      drag.current = {
        kind, index, origin: p, points: r.points.map((q) => ({ ...q })),
        members: lo.devices.map((d, i) => ({ i, x: d.x, y: d.y })).filter((d) => pointInPoly(d, r.points)),
        panel: pointInPoly(lo.panel, r.points) ? { ...lo.panel } : null,
      }
      return
    }

    const target = kind === 'panel' ? lo.panel : lo.devices[index]
    drag.current = { kind, index, dx: p.x - target.x, dy: p.y - target.y }
  }

  const onMove = (e) => {
    if (tool === 'poly' && drawing) setCursor(toMetres(e))
    if (!drag.current) return
    drag.current.moved = true
    const g = drag.current
    const { kind, index, dx, dy } = g

    if (kind === 'calibA') return setCalib((c) => ({ ...c, a: toMetresRaw(e) }))
    if (kind === 'calibB') return setCalib((c) => ({ ...c, b: toMetresRaw(e) }))

    const p = toMetres(e)
    if (kind === 'bg') return onBackgroundChange({ ...bg, x: snap(p.x - dx), y: snap(p.y - dy) })

    if (kind === 'vertex') {
      const pts = lo.rooms[index].points.map((q, i) => (i === g.vertexIndex ? p : q))
      return emit({ rooms: lo.rooms.map((r, i) => (i === index ? { ...r, points: pts } : r)) }, true)
    }

    if (kind === 'room') {
      const ox = snap(p.x - g.origin.x), oy = snap(p.y - g.origin.y)
      const points = g.points.map((q) => ({ x: snap(q.x + ox), y: snap(q.y + oy) }))
      const devices = lo.devices.map((d, i) => {
        const m = g.members.find((mm) => mm.i === i)
        return m ? { ...d, x: snap(m.x + ox), y: snap(m.y + oy) } : d
      })
      const patch = { rooms: lo.rooms.map((r, i) => (i === index ? { ...r, points } : r)), devices }
      if (g.panel) patch.panel = { x: snap(g.panel.x + ox), y: snap(g.panel.y + oy) }
      return emit(patch)
    }

    const x = snap(p.x - dx), y = snap(p.y - dy)
    if (kind === 'panel') emit({ panel: { x, y } })
    else emit({ devices: lo.devices.map((d, i) => (i === index ? { ...d, x, y } : d)) })
  }
  const endDrag = () => {
    if (drag.current?.moved) suppressClick.current = true
    drag.current = null
  }

  /** Lay the design's rooms out in a flow (as rectangles) and drop their ITC-BT-25 points as devices. */
  const autoSeed = () => {
    const out = []
    const devices = []
    let x = 1.5, y = 1.5, rowH = 0
    designRooms.forEach((dr, idx) => {
      const a = Number(dr.area) || 6
      const w = snap(Math.max(2.5, Math.sqrt(a * 1.35)))
      const h = snap(Math.max(2, a / w))
      if (x + w > WM - 1) { x = 1.5; y += rowH + 1; rowH = 0 }
      out.push({ type: dr.type, points: [{ x, y }, { x: x + w, y }, { x: x + w, y: y + h }, { x, y: y + h }] })
      const pts = resultRooms?.[idx] || { lights: 1, socketsGeneral: 1, socketsC5: 0, switches: 1 }
      const spread = (n, fx) => { for (let k = 0; k < n; k++) fx(x + (w * (k + 1)) / (n + 1), k) }
      spread(pts.lights, (px) => devices.push({ type: 'light', x: snap(px), y: snap(y + h * 0.45) }))

      // Every socket the regulation asks of this room, each tagged with the circuit that feeds it.
      // ES: Cada toma que la norma exige a esta estancia, etiquetada con el circuito que la alimenta.
      const sockets = []
      const add = (n, circuit) => { for (let k = 0; k < (n || 0); k++) sockets.push(circuit) }
      add(pts.socketsGeneral, 'C2')
      add(pts.socketsC5, 'C5')
      add(pts.socketsC3, 'C3')
      add(pts.socketsC4, 'C4')
      add(pts.socketsC10, 'C10')
      spread(sockets.length, (px, k) => devices.push({ type: 'socket', circuit: sockets[k], x: snap(px), y: snap(y + h - 0.4) }))

      for (let k = 0; k < pts.switches; k++) devices.push({ type: 'switch', x: snap(x + 0.5 + k * 0.5), y: snap(y + h - 0.5) })
      x += w + 1
      rowH = Math.max(rowH, h)
    })
    onChange({ panel: { x: 0.5, y: 0.5 }, rooms: out, devices })
    onRoomsChange?.(out.map((r) => ({ type: r.type, area: polyArea(r.points) })))
  }

  // --- export ---------------------------------------------------------------
  const NS = 'http://www.w3.org/2000/svg'
  const el = (tag, attrs = {}, text) => {
    const n = document.createElementNS(NS, tag)
    Object.entries(attrs).forEach(([k, v]) => n.setAttribute(k, v))
    if (text !== undefined) n.textContent = text
    return n
  }
  const cssVar = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback

  /**
   * Export the finished plan as a PNG: clone the live SVG, inline the styles as concrete colours (the
   * stylesheet doesn't travel with a serialized SVG), hide the editing handles, add a legend, rasterise.
   * ES: Exporta el plano a PNG: clona el SVG vivo, incrusta los estilos con colores concretos (la hoja de
   * estilos no viaja con un SVG serializado), oculta los tiradores de edición, añade leyenda y rasteriza.
   */
  const exportPng = () => {
    const W = WM * scale, H = HM * scale, LEGEND = 70
    const clone = svgRef.current.cloneNode(true)
    clone.setAttribute('xmlns', NS)
    clone.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink')
    clone.setAttribute('width', W)
    clone.setAttribute('height', H + LEGEND)
    clone.setAttribute('viewBox', `0 0 ${W} ${H + LEGEND}`)

    // Some browsers only rasterise <image> from xlink:href when the SVG is loaded as an <img>.
    const bgImage = clone.querySelector('image')
    if (bgImage) bgImage.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', bgImage.getAttribute('href'))

    const ink = cssVar('--text', '#101828'), muted = cssVar('--text-muted', '#475467')
    const accent = cssVar('--accent', '#443ea8'), pos = cssVar('--pos', '#0f6b54')
    const warn = cssVar('--warn-text', '#8a5a16'), border = cssVar('--border', '#e2deea')

    const style = el('style')
    style.textContent = `
      text { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
      .grid-line { stroke:${border}; stroke-width:1; }
      .plan-room { fill:none; stroke:${ink}; stroke-width:3; }
      .plan-room-label { fill:${ink}; font-size:12px; font-weight:700; }
      .plan-room-dim { fill:${muted}; font-size:10px; }
      .dev-socket { fill:#fff; stroke:${accent}; stroke-width:2; }
      .dev-switch { fill:#fff; stroke:${pos}; stroke-width:2; }
      .dev-light circle { fill:#fff; stroke:${warn}; stroke-width:2; }
      .dev-light path { stroke:${warn}; stroke-width:1.5; fill:none; }
      .plan-panel { fill:${accent}; }
      .plan-panel-label { fill:#fff; font-size:9px; font-weight:700; }
      .plan-vertex, .plan-mid, .calib-mark, .poly-draft { display:none; }
      .lg { fill:${ink}; font-size:12px; }
      .lg-title { fill:${ink}; font-size:14px; font-weight:700; }
    `
    clone.insertBefore(el('rect', { x: 0, y: 0, width: W, height: H + LEGEND, fill: '#ffffff' }), clone.firstChild)
    clone.insertBefore(style, clone.firstChild)

    // legend strip under the plan
    const g = el('g')
    g.appendChild(el('line', { x1: 0, y1: H + 1, x2: W, y2: H + 1, stroke: border, 'stroke-width': 2 }))
    g.appendChild(Object.assign(el('text', { x: 14, y: H + 26, class: 'lg-title' }, planTitle || t('inst.plan.title')), {}))
    let x = 14
    const item = (draw, label) => {
      draw(x + 8)
      g.appendChild(el('text', { x: x + 22, y: H + 55, class: 'lg' }, label))
      x += 26 + label.length * 7.2
    }
    item((cx) => g.appendChild(el('circle', { cx, cy: H + 50, r: 7, class: 'dev-socket' })), t('inst.plan.tool.socket'))
    item((cx) => g.appendChild(el('rect', { x: cx - 6, y: H + 44, width: 12, height: 12, rx: 2, class: 'dev-switch' })), t('inst.plan.tool.switch'))
    item((cx) => {
      const lg = el('g', { class: 'dev-light' })
      lg.appendChild(el('circle', { cx, cy: H + 50, r: 8 }))
      lg.appendChild(el('path', { d: `M ${cx - 5.5} ${H + 44.5} L ${cx + 5.5} ${H + 55.5} M ${cx + 5.5} ${H + 44.5} L ${cx - 5.5} ${H + 55.5}` }))
      g.appendChild(lg)
    }, t('inst.plan.tool.light'))
    item((cx) => {
      g.appendChild(el('rect', { x: cx - 14, y: H + 40, width: 28, height: 20, rx: 3, class: 'plan-panel' }))
      g.appendChild(el('text', { x: cx, y: H + 54, 'text-anchor': 'middle', class: 'plan-panel-label' }, 'CGP'))
    }, t('inst.plan.tool.panel'))
    g.appendChild(el('text', { x: W - 14, y: H + 55, 'text-anchor': 'end', class: 'lg' }, `1 m = ${scale} px`))
    clone.appendChild(g)

    const url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml;charset=utf-8' }))
    const img = new Image()
    img.onload = () => {
      const k = 2 // render at 2× so text and symbols stay crisp when printed
      const canvas = document.createElement('canvas')
      canvas.width = W * k
      canvas.height = (H + LEGEND) * k
      const ctx = canvas.getContext('2d')
      ctx.fillStyle = '#ffffff'
      ctx.fillRect(0, 0, canvas.width, canvas.height)
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
      URL.revokeObjectURL(url)
      canvas.toBlob((b) => {
        const a = document.createElement('a')
        a.href = URL.createObjectURL(b)
        a.download = `plano-${(planTitle || 'instalacion').replace(/[^\w-]+/g, '-').toLowerCase()}.png`
        a.click()
        URL.revokeObjectURL(a.href)
      }, 'image/png')
    }
    img.onerror = () => URL.revokeObjectURL(url)
    img.src = url
  }

  const Tool = ({ id, label }) => (
    <button type="button" className={`plan-tool${tool === id ? ' active' : ''}`}
      onClick={() => { setTool(id); cancelCalib(); if (id !== 'poly') cancelDraw() }}>{label}</button>
  )
  const zoom = (dir) => {
    const i = SCALES.indexOf(scale)
    setScale(SCALES[Math.min(SCALES.length - 1, Math.max(0, i + dir))])
  }

  const showRoomType = tool === 'room' || tool === 'poly'

  return (
    <div>
      {pending && <PlanRectifier img={pending} t={t} onDone={useImage} onCancel={() => setPending(null)} />}

      <div className="plan-toolbar">
        <label className="plan-tool" style={{ cursor: 'pointer' }}>
          {t('inst.plan.bgUpload')}
          <input type="file" accept="image/*" className="file-input-hidden" onChange={pickFile} />
        </label>
        {bg && (
          <>
            <Tool id="calibrate" label={t('inst.plan.calibrate')} />
            <Tool id="bg" label={t('inst.plan.bgMove')} />
            <label className="plan-opacity" title={t('inst.plan.opacity')}>
              <input type="range" min="0.1" max="1" step="0.05" value={bg.opacity}
                onChange={(e) => onBackgroundChange({ ...bg, opacity: Number(e.target.value) })} />
            </label>
            <button type="button" className="link-btn danger-link" onClick={() => onBackgroundChange(null)}>{t('inst.plan.bgRemove')}</button>
            <span className="plan-sep" />
          </>
        )}
        <Tool id="drag" label={t('inst.plan.tool.drag')} />
        <Tool id="poly" label={t('inst.plan.tool.poly')} />
        <Tool id="room" label={t('inst.plan.tool.room')} />
        <Tool id="socket" label={t('inst.plan.tool.socket')} />
        <Tool id="switch" label={t('inst.plan.tool.switch')} />
        <Tool id="light" label={t('inst.plan.tool.light')} />
        <Tool id="panel" label={t('inst.plan.tool.panel')} />
        <Tool id="delete" label={t('inst.plan.tool.delete')} />
        {showRoomType && (
          <select className="bank-select" value={newRoomType} onChange={(e) => setNewRoomType(e.target.value)}>
            {ROOM_TYPES.map((rt) => <option key={rt} value={rt}>{t('inst.room.' + rt)}</option>)}
          </select>
        )}
        {tool === 'socket' && (
          <select className="bank-select" value={socketCircuit} onChange={(e) => setSocketCircuit(e.target.value)}
            title={t('inst.plan.socketCircuit')}>
            {SOCKET_CIRCUITS.map((c) => <option key={c} value={c}>{c} — {t('inst.circuit.' + c)}</option>)}
          </select>
        )}
        {tool === 'poly' && drawing && (
          <>
            <button type="button" className="btn btn-primary btn-sm" onClick={closeDraw} disabled={drawing.length < 3}>{t('inst.plan.polyClose')}</button>
            <button type="button" className="link-btn" onClick={cancelDraw}>{t('inst.plan.polyCancel')}</button>
          </>
        )}
        <span className="plan-sep" />
        <button type="button" className="plan-tool" onClick={() => zoom(-1)} disabled={scale === SCALES[0]} title={t('inst.plan.zoomOut')}>−</button>
        <button type="button" className="plan-tool" onClick={() => zoom(1)} disabled={scale === SCALES[SCALES.length - 1]} title={t('inst.plan.zoomIn')}>＋</button>
        <span className="plan-sep" />
        <button type="button" className="link-btn" onClick={autoSeed}>{t('inst.plan.autoseed')}</button>
        <button type="button" className="link-btn" onClick={exportPng}>{t('inst.plan.download')}</button>
        <button type="button" className="link-btn" onClick={() => onChange({ panel: lo.panel, rooms: [], devices: [] })}>{t('inst.plan.clear')}</button>
      </div>

      <p className="muted" style={{ fontSize: 12, margin: '4px 0 8px' }}>
        {tool === 'calibrate'
          ? <strong>{!calib ? t('inst.plan.calibStep1') : !calib.b ? t('inst.plan.calibStep2') : t('inst.plan.calibStep3')}</strong>
          : tool === 'poly' ? <strong>{t('inst.plan.polyHint')}</strong>
            : t('inst.plan.hint')}
      </p>

      {tool === 'calibrate' && calib?.b && (
        <div className="calib-panel">
          <span className="muted num">{t('inst.plan.calibMeasured', { n: measured.toFixed(2) })}</span>
          <label className="field field-sm" style={{ margin: 0 }}>
            <span>{t('inst.plan.calibRealLabel')}</span>
            <input autoFocus type="text" inputMode="decimal" placeholder="8.19" value={realInput}
              onChange={(e) => setRealInput(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); applyCalibration() } }} />
          </label>
          <button type="button" className="btn btn-primary btn-sm" onClick={applyCalibration} disabled={!parseFloat(realInput.replace(',', '.'))}>
            {t('inst.plan.calibApply')}
          </button>
          <button type="button" className="link-btn" onClick={cancelCalib}>{t('inst.plan.calibCancel')}</button>
        </div>
      )}

      <div className="table-scroll">
        <svg ref={svgRef} className="floorplan" width={WM * scale} height={HM * scale}
          onClick={onCanvasClick} onPointerMove={onMove} onPointerUp={endDrag} onPointerLeave={endDrag}>
          <defs>
            <pattern id="grid" width={scale} height={scale} patternUnits="userSpaceOnUse">
              <path d={`M ${scale} 0 L 0 0 0 ${scale}`} className="grid-line" fill="none" />
            </pattern>
          </defs>
          <rect width={WM * scale} height={HM * scale} fill="url(#grid)" />

          {bg && (
            <image href={bg.src} x={bg.x * scale} y={bg.y * scale} width={bg.w * scale} height={bg.h * scale}
              opacity={bg.opacity} preserveAspectRatio="none"
              style={{ pointerEvents: tool === 'bg' ? 'auto' : 'none', cursor: 'move' }}
              onPointerDown={(e) => startDrag(e, 'bg', 0)} />
          )}

          {calib && (
            <g className="calib-mark">
              {calib.b && <line className="calib-line" style={{ pointerEvents: 'none' }}
                x1={calib.a.x * scale} y1={calib.a.y * scale} x2={calib.b.x * scale} y2={calib.b.y * scale} />}
              {[calib.a, calib.b].filter(Boolean).map((q, i) => (
                <g key={i}>
                  <g style={{ pointerEvents: 'none' }}>
                    <line x1={q.x * scale - 9} y1={q.y * scale} x2={q.x * scale + 9} y2={q.y * scale} />
                    <line x1={q.x * scale} y1={q.y * scale - 9} x2={q.x * scale} y2={q.y * scale + 9} />
                    <text x={q.x * scale + 12} y={q.y * scale - 7} className="calib-tag">{i === 0 ? 'A' : 'B'}</text>
                  </g>
                  <circle cx={q.x * scale} cy={q.y * scale} r="12" className="calib-grab"
                    onPointerDown={(e) => startDrag(e, i === 0 ? 'calibA' : 'calibB')} />
                </g>
              ))}
            </g>
          )}

          {lo.rooms.map((r, i) => {
            const c = centroid(r.points)
            return (
              <g key={`r${i}`}>
                <polygon
                  points={svgPoints(r.points, scale)} className="plan-room"
                  style={{ cursor: tool === 'delete' ? 'not-allowed' : 'move' }}
                  onPointerDown={(e) => startDrag(e, 'room', i)}
                  onDoubleClick={(e) => { e.stopPropagation(); insertVertex(i, toMetres(e)) }}
                />
                <text x={c.x * scale} y={c.y * scale - 3} textAnchor="middle" className="plan-room-label">{t('inst.room.' + r.type)}</text>
                <text x={c.x * scale} y={c.y * scale + 12} textAnchor="middle" className="plan-room-dim">{polyArea(r.points)} m²</text>
                {/* midpoint of every edge: drag it and a new vertex appears there */}
                {r.points.map((q, vi) => {
                  const b = r.points[(vi + 1) % r.points.length]
                  return (
                    <circle key={`m${vi}`} cx={((q.x + b.x) / 2) * scale} cy={((q.y + b.y) / 2) * scale} r="4"
                      className="plan-mid" onPointerDown={(e) => startMidpoint(e, i, vi)} />
                  )
                })}
                {r.points.map((q, vi) => (
                  <circle key={vi} cx={q.x * scale} cy={q.y * scale} r="5" className="plan-vertex"
                    onPointerDown={(e) => startDrag(e, 'vertex', i, vi)}
                    onDoubleClick={(e) => removeVertex(e, i, vi)} />
                ))}
              </g>
            )
          })}

          {drawing && (
            <g className="poly-draft" style={{ pointerEvents: 'none' }}>
              <polyline points={svgPoints(cursor ? [...drawing, cursor] : drawing, scale)} />
              {drawing.map((q, i) => <circle key={i} cx={q.x * scale} cy={q.y * scale} r="4" />)}
            </g>
          )}

          {lo.devices.map((d, i) => (
            <g key={`d${i}`} onPointerDown={(e) => startDrag(e, 'device', i)} style={{ cursor: tool === 'delete' ? 'not-allowed' : 'move' }}>
              {d.type === 'socket' && (
                <>
                  <circle cx={d.x * scale} cy={d.y * scale} r="7" className="dev-socket">
                    <title>{d.circuit ? `${d.circuit} — ${t('inst.circuit.' + d.circuit)}` : t('inst.plan.socketNoCircuit')}</title>
                  </circle>
                  {d.circuit && d.circuit !== 'C2' && (
                    <text x={d.x * scale} y={d.y * scale + 3} className="dev-socket-tag">{d.circuit.slice(1)}</text>
                  )}
                </>
              )}
              {d.type === 'switch' && <rect x={d.x * scale - 6} y={d.y * scale - 6} width="12" height="12" rx="2" className="dev-switch" />}
              {d.type === 'light' && (
                <g className="dev-light">
                  <circle cx={d.x * scale} cy={d.y * scale} r="8" />
                  <path d={`M ${d.x * scale - 5.5} ${d.y * scale - 5.5} L ${d.x * scale + 5.5} ${d.y * scale + 5.5} M ${d.x * scale + 5.5} ${d.y * scale - 5.5} L ${d.x * scale - 5.5} ${d.y * scale + 5.5}`} />
                </g>
              )}
            </g>
          ))}

          <g onPointerDown={(e) => startDrag(e, 'panel', 0)} style={{ cursor: 'move' }}>
            <rect x={lo.panel.x * scale - 14} y={lo.panel.y * scale - 10} width="28" height="20" rx="3" className="plan-panel" />
            <text x={lo.panel.x * scale} y={lo.panel.y * scale + 4} textAnchor="middle" className="plan-panel-label">CGP</text>
          </g>
        </svg>
      </div>
    </div>
  )
}
