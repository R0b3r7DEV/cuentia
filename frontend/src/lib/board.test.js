import { describe, expect, it } from 'vitest'
import { INITIAL_BOARD, act, isLive, statusKey } from './board'

describe('the landing board behaves like a real one', () => {
  it('a circuit is live only with the IGA and the ID up', () => {
    expect(isLive(INITIAL_BOARD, 'inv')).toBe(true)
    expect(isLive({ ...INITIAL_BOARD, iga: false }, 'inv')).toBe(false)
    expect(isLive({ ...INITIAL_BOARD, id: false }, 'inv')).toBe(false)
    expect(isLive({ ...INITIAL_BOARD, inv: false }, 'inv')).toBe(false)
    expect(isLive({ ...INITIAL_BOARD, iga: false }, 'id')).toBe(false)
  })

  it('the T button trips the ID, and only once', () => {
    const r = act(INITIAL_BOARD, 'test')
    expect(r.state.id).toBe(false)
    expect(r.tripped).toBe(true)
    expect(r.message).toBe('board.tripped')
    expect(act(r.state, 'test')).toMatchObject({ state: r.state, message: 'board.alreadyTripped', tripped: false })
    expect(statusKey(r.state)).toBe('board.statusTripped')
  })

  it('flipping a device toggles it; rearming the ID and dropping the IGA say so', () => {
    expect(act(INITIAL_BOARD, 'wo').state.wo).toBe(false)
    expect(act({ ...INITIAL_BOARD, id: false }, 'id').message).toBe('board.rearmed')
    const off = act(INITIAL_BOARD, 'iga')
    expect(off.message).toBe('board.igaOff')
    expect(statusKey(off.state)).toBe('board.statusIga')
    expect(act(INITIAL_BOARD, 'nope').state).toBe(INITIAL_BOARD)
  })
})
