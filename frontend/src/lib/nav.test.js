import { describe, expect, it } from 'vitest'
import { ACCOUNT, GROUPS, MAIN, MORE_GROUPS, isCurrent } from './nav'

const all = (groups) => groups.flatMap((g) => g.items.map(([to]) => to))

describe('navigation', () => {
  it('every section is reachable exactly once from the side menu', () => {
    const paths = [...all(GROUPS), ...ACCOUNT.map(([to]) => to)]
    expect(new Set(paths).size).toBe(paths.length)
  })

  it('on a phone, the bottom bar plus "Más" reach the same sections as the side menu', () => {
    const phone = [...MAIN.map(([to]) => to), ...all(MORE_GROUPS)].sort()
    const desk = [...all(GROUPS), ...ACCOUNT.map(([to]) => to)].sort()
    expect(phone).toEqual(desk)
  })

  it('"/" is current only for itself; a section also for what is under it', () => {
    expect(isCurrent('/', '/partes')).toBe(false)
    expect(isCurrent('/partes', '/partes/7')).toBe(true)
    expect(isCurrent('/partes', '/partesx')).toBe(false)
  })
})
