import { describe, it, expect } from 'vitest'
import {
  snap, dist, isFinitePoint, asPolygon, toPolygonPoints,
  polyArea, centroid, pointInPoly, projectOnSegment,
  solveLinear, homography, isConvexQuad,
} from './geometry'

describe('polygon helpers', () => {
  const square = [{ x: 0, y: 0 }, { x: 4, y: 0 }, { x: 4, y: 3 }, { x: 0, y: 3 }]

  it('snaps to the 25 cm grid', () => {
    expect(snap(1.1)).toBe(1)
    expect(snap(1.13)).toBe(1.25)
    expect(snap(-0.4)).toBe(-0.5)
  })

  it('measures polygon area by the shoelace formula, order-independent', () => {
    expect(polyArea(square)).toBe(12)
    expect(polyArea([...square].reverse())).toBe(12) // reversed winding, same area
  })

  it('gets the real area of an L-shaped polygon, not its bounding box', () => {
    // 6×6 square minus a 3×3 corner = 27
    const l = [
      { x: 0, y: 0 }, { x: 6, y: 0 }, { x: 6, y: 3 },
      { x: 3, y: 3 }, { x: 3, y: 6 }, { x: 0, y: 6 },
    ]
    expect(polyArea(l)).toBe(27)
  })

  it('expands a legacy rectangle into a polygon, and leaves a polygon untouched', () => {
    expect(asPolygon({ type: 'bano', x: 1, y: 2, w: 2, h: 3 }).points).toEqual([
      { x: 1, y: 2 }, { x: 3, y: 2 }, { x: 3, y: 5 }, { x: 1, y: 5 },
    ])
    const poly = { type: 'salon', points: square }
    expect(asPolygon(poly)).toBe(poly)
  })

  it('finds the centroid as the mean of the vertices', () => {
    expect(centroid(square)).toEqual({ x: 2, y: 1.5 })
  })

  it('tells inside from outside by ray casting', () => {
    expect(pointInPoly({ x: 2, y: 1.5 }, square)).toBe(true)
    expect(pointInPoly({ x: 5, y: 1.5 }, square)).toBe(false)
    expect(pointInPoly({ x: -0.1, y: 1.5 }, square)).toBe(false)
  })

  it('excludes an L-shape notch that a bounding box would include', () => {
    const l = [
      { x: 0, y: 0 }, { x: 6, y: 0 }, { x: 6, y: 3 },
      { x: 3, y: 3 }, { x: 3, y: 6 }, { x: 0, y: 6 },
    ]
    expect(pointInPoly({ x: 1, y: 1 }, l)).toBe(true)  // in the body
    expect(pointInPoly({ x: 5, y: 5 }, l)).toBe(false) // in the cut-out corner
  })

  it('projects a point onto a segment and clamps to its ends', () => {
    const a = { x: 0, y: 0 }, b = { x: 4, y: 0 }
    expect(projectOnSegment({ x: 2, y: 1 }, a, b).q).toEqual({ x: 2, y: 0 })
    expect(projectOnSegment({ x: -3, y: 1 }, a, b).q).toEqual({ x: 0, y: 0 }) // clamped to A
    expect(projectOnSegment({ x: 9, y: 1 }, a, b).q).toEqual({ x: 4, y: 0 })  // clamped to B
    expect(projectOnSegment({ x: 2, y: 1 }, a, b).d2).toBe(1)
  })

  it('measures euclidean distance', () => {
    expect(dist({ x: 0, y: 0 }, { x: 3, y: 4 })).toBe(5)
  })
})

describe('NaN safety (the blank-3D-canvas bug)', () => {
  it('recognises finite points', () => {
    expect(isFinitePoint({ x: 1, y: 2 })).toBe(true)
    expect(isFinitePoint({ x: NaN, y: 2 })).toBe(false)
    expect(isFinitePoint(null)).toBe(false)
    expect(isFinitePoint({ x: 1 })).toBe(false)
  })

  it('turns a polygon into finite points and drops the junk', () => {
    expect(toPolygonPoints({ points: [{ x: 0, y: 0 }, { x: 1, y: 0 }, { x: NaN, y: 1 }] }))
      .toEqual([{ x: 0, y: 0 }, { x: 1, y: 0 }])
  })

  it('expands a legacy rectangle, and returns [] for a room with no geometry', () => {
    expect(toPolygonPoints({ x: 0, y: 0, w: 2, h: 2 })).toHaveLength(4)
    expect(toPolygonPoints({ type: 'salon' })).toEqual([]) // undefined x/w — the case that fed NaN to the camera
  })
})

describe('linear solve', () => {
  it('solves a small system', () => {
    // 2x + y = 5 ; x - y = 1  → x = 2, y = 1
    const sol = solveLinear([[2, 1], [1, -1]], [5, 1])
    expect(sol[0]).toBeCloseTo(2, 12)
    expect(sol[1]).toBeCloseTo(1, 12)
  })

  it('returns null for a singular system', () => {
    expect(solveLinear([[1, 1], [2, 2]], [1, 2])).toBeNull()
  })
})

describe('homography — the de-skew that makes measured cable trustworthy', () => {
  const W = 1000, H = 700
  const dst = [{ x: 0, y: 0 }, { x: W, y: 0 }, { x: W, y: H }, { x: 0, y: H }]
  // a deliberately skewed quad, as a phone photo of a plan would be: rotated + keystoned
  const quad = [{ x: 120, y: 80 }, { x: 960, y: 210 }, { x: 880, y: 840 }, { x: 60, y: 640 }]

  const apply = (h, x, y) => {
    const [a, b, c, d, e, f, g, i8] = h
    const den = g * x + i8 * y + 1
    return { x: (a * x + b * y + c) / den, y: (d * x + e * y + f) / den }
  }

  it('maps each destination corner exactly onto the source quad', () => {
    const h = homography(dst, quad)
    dst.forEach((p, i) => {
      const m = apply(h, p.x, p.y)
      expect(m.x).toBeCloseTo(quad[i].x, 9)
      expect(m.y).toBeCloseTo(quad[i].y, 9)
    })
  })

  it('keeps straight lines straight (an edge midpoint stays on the edge)', () => {
    const h = homography(dst, quad)
    const mid = apply(h, W / 2, 0)
    // cross product of (quad1-quad0) and (mid-quad0) must be ~0 → collinear
    const cross = (quad[1].x - quad[0].x) * (mid.y - quad[0].y) - (quad[1].y - quad[0].y) * (mid.x - quad[0].x)
    expect(Math.abs(cross)).toBeLessThan(1e-6)
  })
})

describe('convexity guard — rejects quads a solvable system would silently collapse', () => {
  const asPts = (arr) => arr.map(([x, y]) => ({ x, y }))

  it('accepts a proper rectangle and a skewed-but-convex photo quad', () => {
    expect(isConvexQuad(asPts([[0.05, 0.05], [0.95, 0.05], [0.95, 0.95], [0.05, 0.95]]))).toBe(true)
    expect(isConvexQuad(asPts([[0.12, 0.08], [0.96, 0.21], [0.88, 0.84], [0.06, 0.64]]))).toBe(true)
  })

  it('rejects a bow-tie (self-intersecting) quad', () => {
    expect(isConvexQuad(asPts([[0.05, 0.05], [0.95, 0.05], [0.05, 0.95], [0.95, 0.95]]))).toBe(false)
  })

  it('rejects three collinear points — the cross-product-exactly-zero case that first slipped through', () => {
    expect(isConvexQuad(asPts([[0, 0], [0.1, 0.1], [0.2, 0.2], [0, 1]]))).toBe(false)
  })

  it('rejects a quad enclosing too little of the sheet', () => {
    expect(isConvexQuad(asPts([[0.5, 0.5], [0.6, 0.5], [0.6, 0.55], [0.5, 0.55]]))).toBe(false)
  })
})
