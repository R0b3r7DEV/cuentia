import { describe, it, expect } from 'vitest'
import {
  STATUSES, TRANSITIONS, canEdit, canTransition, canConvert, nextActions,
  toCents, lineBaseCents, vatCents, laborBaseCents, totals, formatCents, stepHours,
  countByStatus, filterByStatus, fitWithin,
} from './workOrders'

describe('toCents', () => {
  it('reads euros with comma or dot as integer cents', () => {
    expect(toCents('6,50')).toBe(650)
    expect(toCents('6.5')).toBe(650)
    expect(toCents('12')).toBe(1200)
    expect(toCents(0.1)).toBe(10)
    expect(toCents('')).toBe(0)
  })

  it('does not drift like floats do', () => {
    // 1.15 * 100 === 114.99999999999999 in JavaScript
    expect(toCents('1.15')).toBe(115)
    expect(toCents('33.33')).toBe(3333)
  })

  it('rejects what is not a non-negative amount with at most 2 decimals', () => {
    expect(toCents('-3')).toBeNull()
    expect(toCents('1.234')).toBeNull()
    expect(toCents('abc')).toBeNull()
    expect(toCents(null)).toBeNull()
  })
})

describe('money in integer cents', () => {
  it('computes a line base with a whole quantity of at least 1', () => {
    expect(lineBaseCents({ unitPrice: '12.00', quantity: 2 })).toBe(2400)
    expect(lineBaseCents({ unitPrice: '12.00', quantity: 0 })).toBe(1200)
    expect(lineBaseCents({ unitPrice: 'nope', quantity: 3 })).toBe(0)
  })

  it('rounds VAT half up per line, like the invoice', () => {
    expect(vatCents(7000, '21.00')).toBe(1470)
    expect(vatCents(3833, '21')).toBe(805) // 8.0493 → 8.05
    expect(vatCents(5, '10')).toBe(1) // 0.005 → 0.01
  })

  it('computes labour like the backend, including the rounding edges', () => {
    expect(laborBaseCents('2', '35')).toBe(7000)
    expect(laborBaseCents('1.15', '33.33')).toBe(3833)
    expect(laborBaseCents('0.50', '0.01')).toBe(1)
    expect(laborBaseCents('', '35')).toBe(0)
  })

  it('totals materials and labour — the same figures the backend tests assert', () => {
    expect(totals({
      laborHours: '2', laborRate: '35', laborVatRate: '21',
      lines: [{ description: 'Magnetotérmico', quantity: 2, unitPrice: '12.00', vatRate: '21.00' }],
    })).toEqual({ base: 9400, vat: 1974, total: 11374 })

    expect(totals({
      laborHours: '1.5', laborRate: '40', laborVatRate: '21',
      lines: [{ quantity: 1, unitPrice: '6,50', vatRate: '21' }],
    })).toEqual({ base: 6650, vat: 1397, total: 8047 })
  })

  it('an empty order totals zero', () => {
    expect(totals({})).toEqual({ base: 0, vat: 0, total: 0 })
  })

  it('formats cents as Spanish euros', () => {
    expect(formatCents(8470).replace(/\s/g, ' ')).toBe('84,70 €')
  })

  it('steps hours by quarters and never goes negative', () => {
    expect(stepHours('1.50', 0.25)).toBe('1.75')
    expect(stepHours('0.00', -0.25)).toBe('0.00')
    expect(stepHours('', 0.25)).toBe('0.25')
  })
})

describe('status lifecycle', () => {
  it('never lets anyone reach "facturado" by hand', () => {
    for (const s of STATUSES) expect(canTransition(s, 'facturado')).toBe(false)
  })

  it('follows pendiente → en_curso → terminado, with the way back', () => {
    expect(canTransition('pendiente', 'en_curso')).toBe(true)
    expect(canTransition('pendiente', 'terminado')).toBe(false)
    expect(canTransition('en_curso', 'terminado')).toBe(true)
    expect(canTransition('en_curso', 'pendiente')).toBe(true)
    expect(canTransition('terminado', 'en_curso')).toBe(true)
    expect(TRANSITIONS.facturado).toEqual([])
  })

  it('only pending or in-progress orders can be edited', () => {
    expect(STATUSES.filter(canEdit)).toEqual(['pendiente', 'en_curso'])
  })

  it('only a finished, not yet invoiced order can be converted', () => {
    expect(canConvert({ status: 'terminado', convertedInvoice: null })).toBe(true)
    expect(canConvert({ status: 'en_curso', convertedInvoice: null })).toBe(false)
    expect(canConvert({ status: 'facturado', convertedInvoice: { id: 1 } })).toBe(false)
  })

  it('offers one clear main action per status', () => {
    const main = (s) => nextActions(s).find((a) => a.primary)?.id
    expect(main('pendiente')).toBe('start')
    expect(main('en_curso')).toBe('finish')
    expect(main('terminado')).toBe('convert')
    expect(main('facturado')).toBe('pdf')
  })

  it('every action that moves the status is a move the backend allows', () => {
    for (const s of STATUSES) {
      for (const a of nextActions(s)) if (a.to) expect(canTransition(s, a.to)).toBe(true)
    }
  })

  it('asks before the actions that cannot be undone easily', () => {
    const confirmOf = (s, id) => nextActions(s).find((a) => a.id === id)?.confirm
    expect(confirmOf('terminado', 'convert')).toBeTruthy()
    expect(confirmOf('terminado', 'reopen')).toBeTruthy()
    expect(confirmOf('pendiente', 'delete')).toBeTruthy()
  })
})

describe('list helpers', () => {
  const orders = [{ status: 'pendiente' }, { status: 'en_curso' }, { status: 'en_curso' }, { status: 'facturado' }]

  it('counts per status', () => {
    expect(countByStatus(orders)).toEqual({ all: 4, pendiente: 1, en_curso: 2, terminado: 0, facturado: 1 })
  })

  it('filters by status, or shows all', () => {
    expect(filterByStatus(orders, 'en_curso')).toHaveLength(2)
    expect(filterByStatus(orders, 'all')).toHaveLength(4)
  })

  it('fits a photo inside the maximum side without enlarging it', () => {
    expect(fitWithin(4000, 3000, 1600)).toEqual({ width: 1600, height: 1200 })
    expect(fitWithin(800, 600, 1600)).toEqual({ width: 800, height: 600 })
  })
})
