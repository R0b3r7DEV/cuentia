import { lazy, Suspense, useEffect, useState } from 'react'
import { useTranslation } from '../../i18n/LanguageContext'
import FloorPlanEditor from './FloorPlanEditor'

// Three.js is heavy, so the 3D view is loaded only when the user opens it (its own chunk).
const FloorPlan3D = lazy(() => import('./FloorPlan3D'))

const ROOM_TYPES = ['salon', 'comedor', 'dormitorio', 'cocina', 'bano', 'pasillo', 'vestibulo', 'terraza', 'garaje', 'trastero']
const LOADS = ['cocina', 'lavadora', 'calefaccion', 'aire', 'secadora', 'domotica', 'vehiculo']
const emptyLayout = () => ({ panel: { x: 0.5, y: 0.5 }, rooms: [], devices: [] })
const blank = () => ({ name: '', grade: 'auto', supplyType: 'monofasico', loads: {}, rooms: [{ type: 'salon', area: 20 }], layout: emptyLayout() })

/** Single-line diagram: IGA → one row per differential → its circuits as breaker boxes. */
function Unifilar({ result, t }) {
  const groups = {}
  result.circuits.forEach((c) => { (groups[c.differential] ??= []).push(c) })
  const diffs = Object.keys(groups).map(Number).sort((a, b) => a - b)
  const maxPer = Math.max(1, ...diffs.map((d) => groups[d].length))
  const boxW = 80, gap = 14, rowH = 66, x0 = 118, top = 62
  const width = x0 + maxPer * (boxW + gap) + 8
  const height = top + diffs.length * rowH + 6

  return (
    <div className="table-scroll">
      <svg className="unifilar" width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
        <text x="12" y="16" className="uni-sub">{result.supplyType === 'trifasico' ? '400 V · 3F' : '230 V · 1F'}</text>
        <rect x="12" y="24" width="92" height="26" rx="5" className="uni-iga" />
        <text x="58" y="41" textAnchor="middle" className="uni-code">IGA {result.grade === 'elevado' ? '40A' : '25A'}</text>
        <line x1="24" y1="50" x2="24" y2={top + (diffs.length - 1) * rowH + 20} className="uni-line" />

        {diffs.map((d, gi) => {
          const rowY = top + gi * rowH
          return (
            <g key={d}>
              <line x1="24" y1={rowY + 20} x2="12" y2={rowY + 20} className="uni-line" />
              <rect x="12" y={rowY} width="92" height="40" rx="5" className="uni-id" />
              <text x="58" y={rowY + 17} textAnchor="middle" className="uni-code">ID {d}</text>
              <text x="58" y={rowY + 31} textAnchor="middle" className="uni-sub">40A · 30mA</text>
              {groups[d].map((c, i) => {
                const x = x0 + i * (boxW + gap)
                return (
                  <g key={i}>
                    <line x1="104" y1={rowY + 20} x2={x} y2={rowY + 20} className="uni-line" />
                    <rect x={x} y={rowY} width={boxW} height="40" rx="5" className="uni-box" />
                    <text x={x + boxW / 2} y={rowY + 16} textAnchor="middle" className="uni-code">{c.code}</text>
                    <text x={x + boxW / 2} y={rowY + 31} textAnchor="middle" className="uni-sub">{c.section} · {c.breaker}A</text>
                  </g>
                )
              })}
            </g>
          )
        })}
      </svg>
    </div>
  )
}

export default function InstallationTab({ onNavigate }) {
  const { t } = useTranslation()
  const [form, setForm] = useState(blank())
  const [result, setResult] = useState(null)
  const [saved, setSaved] = useState([])
  const [currentId, setCurrentId] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)
  const [show3d, setShow3d] = useState(false)

  const loadSaved = async () => {
    const r = await fetch('/api/installations')
    if (r.ok) setSaved(await r.json())
  }
  useEffect(() => { loadSaved() }, [])

  // Recompute (debounced) whenever the design changes.
  useEffect(() => {
    const payload = {
      grade: form.grade, supplyType: form.supplyType, loads: form.loads,
      rooms: form.rooms.map((r) => ({ type: r.type, area: Number(r.area) || 0 })),
      layout: form.layout,
    }
    const id = setTimeout(async () => {
      const res = await fetch('/api/installations/compute', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
      })
      if (res.ok) setResult(await res.json())
    }, 300)
    return () => clearTimeout(id)
  }, [form.grade, form.supplyType, form.loads, form.rooms, form.layout])

  const set = (patch) => setForm((f) => ({ ...f, ...patch }))
  const setRoom = (i, patch) => set({ rooms: form.rooms.map((r, j) => (j === i ? { ...r, ...patch } : r)) })
  const toggleLoad = (k) => set({ loads: { ...form.loads, [k]: !form.loads[k] } })

  const save = async () => {
    if (!form.name.trim()) { setMessage(t('common.errorMsg', { msg: t('inst.name') })); return }
    setSaving(true); setMessage(null)
    try {
      const res = await fetch(currentId ? `/api/installations/${currentId}` : '/api/installations', {
        method: currentId ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setCurrentId(data.id)
      setMessage(t('inst.savedMsg'))
      await loadSaved()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setSaving(false)
    }
  }

  const open = async (id) => {
    const res = await fetch(`/api/installations/${id}`)
    if (!res.ok) return
    const d = await res.json()
    setForm({
      name: d.name, grade: d.grade, supplyType: d.supplyType, loads: d.loads || {},
      rooms: d.rooms.length ? d.rooms : blank().rooms,
      layout: d.layout && d.layout.panel ? { rooms: [], devices: [], ...d.layout } : emptyLayout(),
    })
    setCurrentId(id)
    setMessage(null)
  }

  const remove = async (id) => {
    if (!window.confirm(t('inst.deleteConfirm'))) return
    await fetch(`/api/installations/${id}`, { method: 'DELETE' })
    if (currentId === id) { setForm(blank()); setCurrentId(null) }
    await loadSaved()
  }

  // Hand the computed design to another tab: prefill a CIE, or a quote from the materials.
  const toCertificate = () => onNavigate?.('certificates', {
    installationType: 'nueva',
    address: form.name || '',
    maxPower: (result.contractedPower / 1000).toFixed(3),
    voltage: result.voltage,
    supplyType: result.supplyType,
    earthingScheme: 'TT',
    circuits: result.totals.circuits,
    igaCurrent: result.grade === 'elevado' ? '40' : '25',
    differentialSensitivity: '30',
  })
  const toQuote = () => onNavigate?.('quotes', {
    lines: result.materials.map((m) => ({ description: m.item, quantity: Number(m.qty) || 1, unitPrice: '', vatRate: '21' })),
  })

  return (
    <>
      <p className="page-subtitle">{t('inst.subtitle')}</p>
      <div className="card cie-note">{t('inst.note')}</div>

      <div className="card">
        <div className="field-row">
          <label className="field"><span>{t('inst.name')}</span>
            <input value={form.name} onChange={(e) => set({ name: e.target.value })} /></label>
          <label className="field field-sm"><span>{t('inst.grade')}</span>
            <select className="bank-select" value={form.grade} onChange={(e) => set({ grade: e.target.value })}>
              {['auto', 'basico', 'elevado'].map((g) => <option key={g} value={g}>{t('inst.grade.' + g)}</option>)}
            </select></label>
          <label className="field field-sm"><span>{t('inst.supply')}</span>
            <select className="bank-select" value={form.supplyType} onChange={(e) => set({ supplyType: e.target.value })}>
              {['monofasico', 'trifasico'].map((s) => <option key={s} value={s}>{t('inst.supply.' + s)}</option>)}
            </select></label>
        </div>

        <div className="form-sec">{t('inst.rooms')}</div>
        <table className="table lines-table">
          <tbody>
            {form.rooms.map((r, i) => (
              <tr key={i}>
                <td>
                  <select className="bank-select" value={r.type} onChange={(e) => setRoom(i, { type: e.target.value })}>
                    {ROOM_TYPES.map((rt) => <option key={rt} value={rt}>{t('inst.room.' + rt)}</option>)}
                  </select>
                </td>
                <td className="right">
                  <input className="num-input" type="number" min="0" step="0.5" value={r.area}
                    onChange={(e) => setRoom(i, { area: e.target.value })} /> <span className="muted">{t('inst.area')}</span>
                </td>
                <td className="right">
                  {form.rooms.length > 1 && (
                    <button type="button" className="link-btn" onClick={() => set({ rooms: form.rooms.filter((_, j) => j !== i) })}>
                      {t('inst.remove')}
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <button type="button" className="link-btn" onClick={() => set({ rooms: [...form.rooms, { type: 'dormitorio', area: 12 }] })}>
          {t('inst.addRoom')}
        </button>

        <div className="form-sec">{t('inst.loads')}</div>
        <div className="loads-grid">
          {LOADS.map((k) => (
            <label key={k} className="load-check">
              <input type="checkbox" checked={!!form.loads[k]} onChange={() => toggleLoad(k)} /> {t('inst.load.' + k)}
            </label>
          ))}
        </div>

        <div className="verify-bar" style={{ marginTop: 14 }}>
          <button className="btn btn-glass btn-sm" onClick={save} disabled={saving}>{saving ? t('inst.saving') : t('inst.save')}</button>
          <button type="button" className="link-btn" onClick={() => { setForm(blank()); setCurrentId(null) }}>{t('inst.new')}</button>
        </div>
        {message && <p className="msg">{message}</p>}
      </div>

      {result && (
        <>
          <div className="card">
            <div className="verify-bar">
              <span className={`chain-badge status-${result.grade === 'elevado' ? 'accepted' : 'draft'}`}>
                {t('inst.resGrade')}: {t('inst.grade.' + result.grade)}
              </span>
              <span className="muted num">{t('inst.resPower')}: {result.contractedPower} W · {result.voltage} V</span>
              {result.layoutCable
                ? <span className="chain-badge chain-ok num">🔌 {t('inst.plan.exactCable')}: {result.layoutCable.totalM} m</span>
                : <span className="muted num">{t('inst.cable')}: ~{result.cable.totalM} m</span>}
            </div>
            {result.notes?.map((n, i) => <p key={i} className="msg" style={{ color: 'var(--warn-text)' }}>⚠️ {n}</p>)}
            {onNavigate && (
              <div className="verify-bar" style={{ marginTop: 12 }}>
                <button className="btn btn-glass btn-sm" onClick={toCertificate}>{t('inst.createCie')}</button>
                <button className="btn btn-glass btn-sm" onClick={toQuote}>{t('inst.createQuote')}</button>
              </div>
            )}
          </div>

          <div className="card">
            <div className="form-sec">{t('inst.unifilar')}</div>
            <Unifilar result={result} t={t} />
          </div>

          <div className="card">
            <div className="verify-bar" style={{ justifyContent: 'space-between' }}>
              <div className="form-sec" style={{ margin: 0, border: 0 }}>{t('inst.plan.title')}</div>
              <button className="btn btn-glass btn-sm" onClick={() => setShow3d((s) => !s)}>
                {show3d ? t('inst.plan.hide3d') : t('inst.plan.view3d')}
              </button>
            </div>
            <FloorPlanEditor
              layout={form.layout}
              onChange={(l) => set({ layout: l })}
              designRooms={form.rooms}
              resultRooms={result.rooms}
              t={t}
            />
            {show3d && (
              <>
                <p className="muted" style={{ fontSize: 12, margin: '12px 0 6px' }}>{t('inst.plan.d3hint')}</p>
                <Suspense fallback={<p className="muted">{t('inst.plan.loading3d')}</p>}>
                  <FloorPlan3D layout={form.layout} />
                </Suspense>
              </>
            )}
          </div>

          <div className="card table-scroll" style={{ padding: 0 }}>
            <table className="table">
              <thead><tr>
                <th>{t('inst.col.code')}</th><th>{t('inst.col.name')}</th>
                <th className="right">{t('inst.col.section')}</th><th className="right">{t('inst.col.breaker')}</th>
                <th className="right">{t('inst.col.points')}</th><th className="right">{t('inst.col.diff')}</th>
              </tr></thead>
              <tbody>
                {result.circuits.map((c, i) => (
                  <tr key={i}>
                    <td className="num"><strong>{c.code}</strong></td><td>{c.name}</td>
                    <td className="right num">{c.section} mm²</td><td className="right num">{c.breaker} A</td>
                    <td className="right num">{c.points}</td><td className="right num">{c.differential}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="card table-scroll" style={{ padding: 0 }}>
            <table className="table">
              <thead><tr>
                <th>{t('inst.col.room')}</th><th className="right">{t('inst.area')}</th>
                <th className="right">{t('inst.col.lights')}</th><th className="right">{t('inst.col.socketsGen')}</th>
                <th className="right">{t('inst.col.socketsC5')}</th><th className="right">{t('inst.col.switches')}</th>
              </tr></thead>
              <tbody>
                {result.rooms.map((r, i) => (
                  <tr key={i}>
                    <td>{t('inst.room.' + r.type)}</td><td className="right num">{r.area}</td>
                    <td className="right num">{r.lights}</td><td className="right num">{r.socketsGeneral}</td>
                    <td className="right num">{r.socketsC5}</td><td className="right num">{r.switches}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="card table-scroll" style={{ padding: 0 }}>
            <table className="table">
              <thead><tr><th>{t('inst.materials')}</th><th className="right">{t('inst.col.qty')}</th></tr></thead>
              <tbody>
                {result.materials.map((m, i) => (
                  <tr key={i}><td>{m.item}</td><td className="right num">{m.qty}</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}

      {saved.length > 0 && (
        <div className="card table-scroll" style={{ padding: 0 }}>
          <table className="table">
            <thead><tr><th>{t('inst.savedList')}</th><th>{t('inst.grade')}</th><th></th></tr></thead>
            <tbody>
              {saved.map((s) => (
                <tr key={s.id}>
                  <td>{s.name}</td>
                  <td className="muted">{t('inst.grade.' + s.grade)}</td>
                  <td className="right">
                    <button className="link-btn" onClick={() => open(s.id)}>{t('inst.open')}</button>
                    <button className="link-btn danger-link" onClick={() => remove(s.id)}>{t('inst.delete')}</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
