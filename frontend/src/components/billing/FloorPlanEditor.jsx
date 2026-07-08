import { useRef, useState } from 'react'

const SCALE = 26          // px per metre
const WM = 20, HM = 14    // canvas size in metres
const snap = (v) => Math.round(v * 4) / 4

const DEVICE_TOOLS = ['socket', 'switch', 'light']

/**
 * A lightweight 2D floor-plan editor: rooms as draggable rectangles, the panel and electrical devices
 * placed by clicking, everything in metres. Emits the layout on every change so cable can be measured
 * from real positions. Not a CAD tool — a schematic aid.
 * ES: Editor 2D ligero: estancias como rectángulos arrastrables, cuadro y dispositivos colocados al clic,
 * todo en metros. Emite la planta en cada cambio para medir el cable desde posiciones reales.
 */
export default function FloorPlanEditor({ layout, onChange, designRooms, resultRooms, t }) {
  const svgRef = useRef(null)
  const drag = useRef(null) // { kind, index, dx, dy }
  const [tool, setTool] = useState('drag')

  const lo = { panel: layout?.panel || { x: 0.5, y: 0.5 }, rooms: layout?.rooms || [], devices: layout?.devices || [] }

  const toMetres = (e) => {
    const rect = svgRef.current.getBoundingClientRect()
    return { x: snap((e.clientX - rect.left) / SCALE), y: snap((e.clientY - rect.top) / SCALE) }
  }

  const emit = (patch) => onChange({ ...lo, ...patch })

  const onBackgroundClick = (e) => {
    if (drag.current) return
    const p = toMetres(e)
    if (tool === 'panel') return emit({ panel: p })
    if (DEVICE_TOOLS.includes(tool)) return emit({ devices: [...lo.devices, { type: tool, x: p.x, y: p.y }] })
  }

  const startDrag = (e, kind, index) => {
    e.stopPropagation()
    if (tool === 'delete' && kind === 'device') {
      return emit({ devices: lo.devices.filter((_, i) => i !== index) })
    }
    const p = toMetres(e)
    const target = kind === 'panel' ? lo.panel : kind === 'room' ? lo.rooms[index] : lo.devices[index]
    drag.current = { kind, index, dx: p.x - target.x, dy: p.y - target.y }
  }

  const onMove = (e) => {
    if (!drag.current) return
    const p = toMetres(e)
    const { kind, index, dx, dy } = drag.current
    const x = snap(p.x - dx), y = snap(p.y - dy)
    if (kind === 'panel') emit({ panel: { x, y } })
    else if (kind === 'room') emit({ rooms: lo.rooms.map((r, i) => (i === index ? { ...r, x, y } : r)) })
    else emit({ devices: lo.devices.map((d, i) => (i === index ? { ...d, x, y } : d)) })
  }
  const endDrag = () => { drag.current = null }

  // Lay the rooms out in a flow and drop each room's ITC-BT-25 points as devices around it.
  const autoSeed = () => {
    const rooms = []
    const devices = []
    let x = 1.5, y = 1.5, rowH = 0
    designRooms.forEach((dr, idx) => {
      const area = Number(dr.area) || 6
      let w = snap(Math.max(2, Math.sqrt(area * 1.3)))
      let h = snap(Math.max(2, area / w))
      if (x + w > WM - 1) { x = 1.5; y += rowH + 1; rowH = 0 }
      rooms.push({ type: dr.type, x, y, w, h })
      const pts = resultRooms?.[idx] || { lights: 1, socketsGeneral: 1, socketsC5: 0, switches: 1 }
      const spread = (n, fx, fy) => {
        for (let k = 0; k < n; k++) fx(x + (w * (k + 1)) / (n + 1), fy)
      }
      spread(pts.lights, (px) => devices.push({ type: 'light', x: snap(px), y: snap(y + h * 0.45) }))
      const sockets = (pts.socketsGeneral || 0) + (pts.socketsC5 || 0)
      spread(sockets, (px) => devices.push({ type: 'socket', x: snap(px), y: snap(y + h - 0.3) }))
      for (let k = 0; k < pts.switches; k++) devices.push({ type: 'switch', x: snap(x + 0.4 + k * 0.4), y: snap(y + h - 0.4) })
      x += w + 1
      rowH = Math.max(rowH, h)
    })
    onChange({ panel: { x: 0.5, y: 0.5 }, rooms, devices })
  }

  const Tool = ({ id, label }) => (
    <button type="button" className={`plan-tool${tool === id ? ' active' : ''}`} onClick={() => setTool(id)}>{label}</button>
  )

  return (
    <div>
      <div className="plan-toolbar">
        <Tool id="drag" label={t('inst.plan.tool.drag')} />
        <Tool id="socket" label={t('inst.plan.tool.socket')} />
        <Tool id="switch" label={t('inst.plan.tool.switch')} />
        <Tool id="light" label={t('inst.plan.tool.light')} />
        <Tool id="panel" label={t('inst.plan.tool.panel')} />
        <Tool id="delete" label={t('inst.plan.tool.delete')} />
        <span className="plan-sep" />
        <button type="button" className="link-btn" onClick={autoSeed}>{t('inst.plan.autoseed')}</button>
        <button type="button" className="link-btn" onClick={() => onChange({ panel: lo.panel, rooms: [], devices: [] })}>{t('inst.plan.clear')}</button>
      </div>
      <p className="muted" style={{ fontSize: 12, margin: '4px 0 8px' }}>{t('inst.plan.hint')}</p>

      <div className="table-scroll">
        <svg
          ref={svgRef}
          className="floorplan"
          width={WM * SCALE}
          height={HM * SCALE}
          onClick={onBackgroundClick}
          onPointerMove={onMove}
          onPointerUp={endDrag}
          onPointerLeave={endDrag}
        >
          <defs>
            <pattern id="grid" width={SCALE} height={SCALE} patternUnits="userSpaceOnUse">
              <path d={`M ${SCALE} 0 L 0 0 0 ${SCALE}`} className="grid-line" fill="none" />
            </pattern>
          </defs>
          <rect width={WM * SCALE} height={HM * SCALE} fill="url(#grid)" />

          {lo.rooms.map((r, i) => (
            <g key={`r${i}`} onPointerDown={(e) => startDrag(e, 'room', i)} style={{ cursor: 'move' }}>
              <rect x={r.x * SCALE} y={r.y * SCALE} width={r.w * SCALE} height={r.h * SCALE} className="plan-room" rx="3" />
              <text x={r.x * SCALE + 5} y={r.y * SCALE + 15} className="plan-room-label">{t('inst.room.' + r.type)}</text>
            </g>
          ))}

          {lo.devices.map((d, i) => (
            <g key={`d${i}`} onPointerDown={(e) => startDrag(e, 'device', i)} style={{ cursor: tool === 'delete' ? 'not-allowed' : 'move' }}>
              {d.type === 'socket' && <circle cx={d.x * SCALE} cy={d.y * SCALE} r="7" className="dev-socket" />}
              {d.type === 'switch' && <rect x={d.x * SCALE - 6} y={d.y * SCALE - 6} width="12" height="12" rx="2" className="dev-switch" />}
              {d.type === 'light' && (
                <g className="dev-light">
                  <circle cx={d.x * SCALE} cy={d.y * SCALE} r="8" />
                  <path d={`M ${d.x * SCALE - 5.5} ${d.y * SCALE - 5.5} L ${d.x * SCALE + 5.5} ${d.y * SCALE + 5.5} M ${d.x * SCALE + 5.5} ${d.y * SCALE - 5.5} L ${d.x * SCALE - 5.5} ${d.y * SCALE + 5.5}`} />
                </g>
              )}
            </g>
          ))}

          <g onPointerDown={(e) => startDrag(e, 'panel', 0)} style={{ cursor: 'move' }}>
            <rect x={lo.panel.x * SCALE - 13} y={lo.panel.y * SCALE - 9} width="26" height="18" rx="3" className="plan-panel" />
            <text x={lo.panel.x * SCALE} y={lo.panel.y * SCALE + 4} textAnchor="middle" className="plan-panel-label">CGP</text>
          </g>
        </svg>
      </div>
    </div>
  )
}
