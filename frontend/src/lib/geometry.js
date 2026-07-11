// Pure plane geometry for the floor-plan editor and the 3D view — no React, no DOM, no side effects.
// Extracted here so the tricky parts (polygon area, ray casting, and above all the homography that
// de-skews a photo of the plan) can be unit-tested on their own.
//
// ES: Geometría plana pura del editor 2D y la vista 3D — sin React, sin DOM, sin efectos. Extraída aquí
// para poder testear por separado lo delicado (área de polígonos, lanzamiento de rayos y, sobre todo, la
// homografía que endereza la foto del plano).

/** Snap a metre value to the 25 cm grid. */
export const snap = (v) => Math.round(v * 4) / 4

/** Euclidean distance between two points. */
export const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y)

/** A point with two finite coordinates. Guards the 3D view against a NaN reaching the camera. */
export const isFinitePoint = (p) => Boolean(p) && Number.isFinite(p.x) && Number.isFinite(p.y)

/** Rooms are polygons; a rectangle saved by an older version is converted to its four corners. */
export const asPolygon = (r) => (r.points ? r : {
  ...r,
  points: [{ x: r.x, y: r.y }, { x: r.x + r.w, y: r.y }, { x: r.x + r.w, y: r.y + r.h }, { x: r.x, y: r.y + r.h }],
})

/** Just the polygon points, NaN-filtered — a legacy rectangle becomes its corners, junk becomes []. */
export const toPolygonPoints = (r) => {
  if (Array.isArray(r?.points) && r.points.length >= 3) return r.points.filter(isFinitePoint)
  if (!Number.isFinite(r?.x) || !Number.isFinite(r?.w)) return []
  return [{ x: r.x, y: r.y }, { x: r.x + r.w, y: r.y }, { x: r.x + r.w, y: r.y + r.h }, { x: r.x, y: r.y + r.h }]
}

/** Shoelace formula — the area of any simple polygon, rounded to 0.1 m². Vertex order does not matter. */
export const polyArea = (pts) => {
  let s = 0
  for (let i = 0, n = pts.length; i < n; i++) {
    const a = pts[i], b = pts[(i + 1) % n]
    s += a.x * b.y - b.x * a.y
  }
  return Math.round(Math.abs(s / 2) * 10) / 10
}

/** The average of the vertices — where a room's label sits. */
export const centroid = (pts) => ({
  x: pts.reduce((s, p) => s + p.x, 0) / pts.length,
  y: pts.reduce((s, p) => s + p.y, 0) / pts.length,
})

/** Ray casting: is the point inside the polygon? Decides what a room carries when it moves. */
export const pointInPoly = (p, pts) => {
  let inside = false
  for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
    const a = pts[i], b = pts[j]
    if ((a.y > p.y) !== (b.y > p.y) && p.x < ((b.x - a.x) * (p.y - a.y)) / (b.y - a.y) + a.x) inside = !inside
  }
  return inside
}

/** Closest point on segment AB to P, and its squared distance — used to insert a vertex on an edge. */
export const projectOnSegment = (p, a, b) => {
  const vx = b.x - a.x, vy = b.y - a.y
  const len2 = vx * vx + vy * vy
  const t = len2 === 0 ? 0 : Math.max(0, Math.min(1, ((p.x - a.x) * vx + (p.y - a.y) * vy) / len2))
  const q = { x: a.x + t * vx, y: a.y + t * vy }
  return { q, d2: (p.x - q.x) ** 2 + (p.y - q.y) ** 2 }
}

/** Solve a dense linear system by Gaussian elimination with partial pivoting. Returns null if singular. */
export function solveLinear(A, b) {
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
 * Solved in that direction on purpose: to fill each output pixel we must know where to *read* from in the
 * source (inverse mapping), which leaves no holes. Returns [a,b,c,d,e,f,g,h], or null if degenerate.
 *
 * ES: La homografía que lleva las esquinas del rectángulo destino al cuadrilátero de origen. Se resuelve en
 * ese sentido a propósito: para rellenar cada píxel de salida hay que saber de dónde *leer* en el origen.
 */
export function homography(dst, src) {
  const A = [], b = []
  for (let i = 0; i < 4; i++) {
    const { x, y } = dst[i]
    const { x: u, y: v } = src[i]
    A.push([x, y, 1, 0, 0, 0, -x * u, -y * u]); b.push(u)
    A.push([0, 0, 0, x, y, 1, -x * v, -y * v]); b.push(v)
  }
  return solveLinear(A, b)
}

/**
 * A self-intersecting or near-flat quad still yields a solvable 8×8 system while collapsing the image, so
 * convexity (plus a minimum area) is the guard the linear solve cannot give. Points are fractions of the
 * image. A flat corner — three vertices in a line, cross product exactly zero — is rejected outright.
 *
 * ES: Un cuadrilátero cruzado o casi plano todavía da un sistema resoluble mientras colapsa la imagen, así
 * que la convexidad es el guardián que el sistema lineal no ofrece.
 */
export function isConvexQuad(p) {
  let pos = 0, neg = 0, area2 = 0
  for (let i = 0; i < 4; i++) {
    const a = p[i], b = p[(i + 1) % 4], c = p[(i + 2) % 4]
    const cross = (b.x - a.x) * (c.y - b.y) - (b.y - a.y) * (c.x - b.x)
    if (Math.abs(cross) < 1e-3) return false
    if (cross > 0) pos++; else neg++
    area2 += a.x * b.y - b.x * a.y
  }
  return (pos === 0 || neg === 0) && Math.abs(area2 / 2) > 0.02
}
