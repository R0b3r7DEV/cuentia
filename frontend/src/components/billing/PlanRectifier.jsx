import { useRef, useState } from 'react'

const MAX_SOURCE = 2400   // cap the pixels we read, a phone photo can be 12 Mpx
const MAX_OUTPUT = 1800   // cap the rectified image we store

/** Solve a dense linear system by Gaussian elimination with partial pivoting. Returns null if singular. */
function solveLinear(A, b) {
  const n = b.length
  const M = A.map((row, i) => [...row, b[i]])
  for (let col = 0; col < n; col++) {
    let piv = col
    for (let r = col + 1; r < n; r++) if (Math.abs(M[r][col]) > Math.abs(M[piv][col])) piv = r
    if (Math.abs(M[piv][col]) < 1e-12) return null
    ;[M[col], M[piv]] = [M[piv], M[col]]
    const d = M[col][col]
    for (let c = col; c <= n; c++) M[col][c] /= d
    for (let r = 0; r < n; r++) {
      if (r === col) continue
      const f = M[r][col]
      if (f === 0) continue
      for (let c = col; c <= n; c++) M[r][c] -= f * M[col][c]
    }
  }
  return M.map((row) => row[n])
}

/**
 * The projective transform (homography) taking the destination rectangle's corners onto the source quad.
 * We solve it in that direction on purpose: to fill each output pixel we need to know where to *read* from
 * in the source (inverse mapping), which leaves no holes.
 *
 * ES: La homografía que lleva las esquinas del rectángulo destino al cuadrilátero de origen. Se resuelve en
 * ese sentido a propósito: para rellenar cada píxel de salida hay que saber de dónde *leer* en el origen
 * (mapeo inverso), y así no quedan huecos.
 */
function homography(dst, src) {
  const A = [], b = []
  for (let i = 0; i < 4; i++) {
    const { x, y } = dst[i]
    const { x: u, y: v } = src[i]
    A.push([x, y, 1, 0, 0, 0, -x * u, -y * u]); b.push(u)
    A.push([0, 0, 0, x, y, 1, -x * v, -y * v]); b.push(v)
  }
  return solveLinear(A, b) // [a, b, c, d, e, f, g, h]
}

const dist = (p, q) => Math.hypot(p.x - q.x, p.y - q.y)

/**
 * A self-intersecting or near-flat quad still yields a solvable 8x8 system, but the warp it describes
 * collapses the image. Convexity + a minimum area is the cheap guard.
 * ES: Un cuadrilátero cruzado o casi plano todavía da un sistema resoluble, pero el warp colapsa la imagen.
 */
export function isConvexQuad(p) {
  let pos = 0, neg = 0, area2 = 0
  for (let i = 0; i < 4; i++) {
    const a = p[i], b = p[(i + 1) % 4], c = p[(i + 2) % 4]
    const cross = (b.x - a.x) * (c.y - b.y) - (b.y - a.y) * (c.x - b.x)
    // a flat corner (three vertices in a line) is neither positive nor negative, so it would slip
    // through the sign test — reject it outright, a real sheet has no flat corner
    if (Math.abs(cross) < 1e-3) return false
    if (cross > 0) pos++; else neg++
    area2 += a.x * b.y - b.x * a.y
  }
  // coords are fractions of the image, so the area is a fraction too: demand at least 2 % of the sheet
  return (pos === 0 || neg === 0) && Math.abs(area2 / 2) > 0.02
}

/** Warp the quad (fractions of the image, TL·TR·BR·BL) into a straight rectangle. */
function rectify(img, quadFrac) {
  // work on a bounded copy of the source
  const ks = Math.min(1, MAX_SOURCE / Math.max(img.naturalWidth, img.naturalHeight))
  const sw = Math.round(img.naturalWidth * ks), sh = Math.round(img.naturalHeight * ks)
  const srcCanvas = document.createElement('canvas')
  srcCanvas.width = sw; srcCanvas.height = sh
  const sctx = srcCanvas.getContext('2d', { willReadFrequently: true })
  sctx.drawImage(img, 0, 0, sw, sh)
  const sdata = sctx.getImageData(0, 0, sw, sh).data

  const quad = quadFrac.map((p) => ({ x: p.x * sw, y: p.y * sh }))

  // output size = average of opposite edges, so the sheet keeps its real proportions
  let W = Math.round((dist(quad[0], quad[1]) + dist(quad[3], quad[2])) / 2)
  let H = Math.round((dist(quad[0], quad[3]) + dist(quad[1], quad[2])) / 2)
  const ko = Math.min(1, MAX_OUTPUT / Math.max(W, H))
  W = Math.max(2, Math.round(W * ko)); H = Math.max(2, Math.round(H * ko))

  const h = homography([{ x: 0, y: 0 }, { x: W, y: 0 }, { x: W, y: H }, { x: 0, y: H }], quad)
  if (!h) return null
  const [a, b, c, d, e, f, g, i8] = h

  const out = document.createElement('canvas')
  out.width = W; out.height = H
  const octx = out.getContext('2d')
  const odata = octx.createImageData(W, H)
  const op = odata.data

  for (let y = 0; y < H; y++) {
    for (let x = 0; x < W; x++) {
      const den = g * x + i8 * y + 1
      const u = (a * x + b * y + c) / den
      const v = (d * x + e * y + f) / den
      const o = (y * W + x) * 4
      if (u < 0 || v < 0 || u > sw - 1 || v > sh - 1) { op[o] = op[o + 1] = op[o + 2] = 255; op[o + 3] = 255; continue }
      // bilinear sampling — nearest-neighbour would alias the thin wall lines
      const x0 = Math.floor(u), y0 = Math.floor(v)
      const x1 = Math.min(x0 + 1, sw - 1), y1 = Math.min(y0 + 1, sh - 1)
      const fx = u - x0, fy = v - y0
      for (let ch = 0; ch < 3; ch++) {
        const p00 = sdata[(y0 * sw + x0) * 4 + ch], p10 = sdata[(y0 * sw + x1) * 4 + ch]
        const p01 = sdata[(y1 * sw + x0) * 4 + ch], p11 = sdata[(y1 * sw + x1) * 4 + ch]
        op[o + ch] = (p00 * (1 - fx) + p10 * fx) * (1 - fy) + (p01 * (1 - fx) + p11 * fx) * fy
      }
      op[o + 3] = 255
    }
  }
  octx.putImageData(odata, 0, 0)
  return { src: out.toDataURL('image/jpeg', 0.82), w: W, h: H }
}

/** Straight downscale, for when the photo is already square-on. */
function plain(img) {
  const k = Math.min(1, MAX_OUTPUT / Math.max(img.naturalWidth, img.naturalHeight))
  const c = document.createElement('canvas')
  c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k)
  c.getContext('2d').drawImage(img, 0, 0, c.width, c.height)
  return { src: c.toDataURL('image/jpeg', 0.8), w: c.width, h: c.height }
}

/**
 * Ask the user for the four corners of the sheet, then de-skew the photo into a straight "scan".
 * A two-point calibration can only recover scale; rotation and perspective have to be undone first,
 * otherwise a metre is not the same length across the page.
 * ES: Pide las cuatro esquinas de la hoja y endereza la foto. Una calibración de dos puntos solo recupera
 * la escala; el giro y la perspectiva hay que deshacerlos antes, o el metro no mide igual en toda la hoja.
 */
export default function PlanRectifier({ img, t, onDone, onCancel }) {
  const [pts, setPts] = useState([{ x: 0.06, y: 0.06 }, { x: 0.94, y: 0.06 }, { x: 0.94, y: 0.94 }, { x: 0.06, y: 0.94 }])
  const [busy, setBusy] = useState(false)
  const boxRef = useRef(null)
  const dragIndex = useRef(null)

  const dispW = Math.min(760, img.naturalWidth)
  const dispH = Math.round(dispW * img.naturalHeight / img.naturalWidth)

  const onMove = (e) => {
    if (dragIndex.current === null) return
    const r = boxRef.current.getBoundingClientRect()
    const x = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width))
    const y = Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))
    setPts((p) => p.map((q, i) => (i === dragIndex.current ? { x, y } : q)))
  }

  const apply = async (rectified) => {
    setBusy(true)
    // let the browser paint the "working…" state before the synchronous pixel loop
    await new Promise((r) => setTimeout(r, 30))
    const result = rectified ? rectify(img, pts) : plain(img)
    setBusy(false)
    if (result) onDone(result)
  }

  const labels = ['1', '2', '3', '4']
  const valid = isConvexQuad(pts)

  return (
    <div className="rectify-overlay" onPointerMove={onMove} onPointerUp={() => { dragIndex.current = null }}>
      <div className="card rectify-card">
        <h2 style={{ marginTop: 0 }}>{t('inst.rect.title')}</h2>
        <p className="msg" style={{ marginTop: 0 }}>{t('inst.rect.hint')}</p>

        {/* aspect-ratio + viewBox so the handles keep sitting on the photo when the card is narrower than dispW */}
        <div className="rectify-box" ref={boxRef} style={{ width: dispW, aspectRatio: `${dispW} / ${dispH}` }}>
          <img src={img.src} alt="" draggable={false} />
          <svg viewBox={`0 0 ${dispW} ${dispH}`} className="rectify-svg">
            <polygon className={valid ? '' : 'invalid'} points={pts.map((p) => `${p.x * dispW},${p.y * dispH}`).join(' ')} />
            {pts.map((p, i) => (
              <g key={i}>
                <circle cx={p.x * dispW} cy={p.y * dispH} r="9"
                  onPointerDown={(e) => { e.preventDefault(); dragIndex.current = i }} />
                <text x={p.x * dispW} y={p.y * dispH - 14} textAnchor="middle">{labels[i]}</text>
              </g>
            ))}
          </svg>
        </div>

        {!valid && <p className="alert-warn" style={{ marginTop: 12, marginBottom: 0 }}>{t('inst.rect.invalid')}</p>}

        <div className="verify-bar" style={{ marginTop: 14 }}>
          <button className="btn btn-primary btn-sm" onClick={() => apply(true)} disabled={busy || !valid}>
            {busy ? t('inst.rect.working') : t('inst.rect.apply')}
          </button>
          <button className="btn btn-glass btn-sm" onClick={() => apply(false)} disabled={busy}>{t('inst.rect.skip')}</button>
          <button className="link-btn" onClick={onCancel} disabled={busy}>{t('inst.rect.cancel')}</button>
        </div>
      </div>
    </div>
  )
}
