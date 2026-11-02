import { describe, expect, it } from 'vitest'
import { activeOrders, incompleteCertificates, invoicedThisMonth, madridToday, ordersToInvoice } from './today'

const orders = [
  { id: 1, status: 'pendiente', createdAt: '2026-11-01 09:00', scheduledAt: null },
  { id: 2, status: 'en_curso', createdAt: '2026-10-30 08:00', scheduledAt: '2026-11-02 10:00' },
  { id: 3, status: 'terminado', invoiced: false },
  { id: 4, status: 'terminado', invoiced: true },
  { id: 5, status: 'facturado', invoiced: true },
]

describe('Hoy', () => {
  it('lists pending and in-progress orders, scheduled ones first', () => {
    expect(activeOrders(orders).map((o) => o.id)).toEqual([2, 1])
    expect(activeOrders(null)).toEqual([])
  })

  it('a finished order is ready to invoice until it has its invoice', () => {
    expect(ordersToInvoice(orders).map((o) => o.id)).toEqual([3])
  })

  it('a certificate with required fields empty says which', () => {
    const model = { required: ['titularName', 'insulationResistance'], gridRequired: [] }
    const certs = [
      { id: 7, titularName: 'María', insulationResistance: null, supplySource: 'red' },
      { id: 8, titularName: 'Pepe', insulationResistance: '250', supplySource: 'red' },
    ]
    expect(incompleteCertificates(certs, model)).toEqual([{ id: 7, titularName: 'María', address: undefined, missing: ['insulationResistance'] }])
  })

  it('adds up this month\'s invoices without float drift', () => {
    const invoices = [
      { issuedAt: '2026-11-02', total: '0.10' }, { issuedAt: '2026-11-01', total: '0.20' }, { issuedAt: '2026-10-31', total: '999.00' },
    ]
    expect(invoicedThisMonth(invoices, '2026-11-02')).toEqual({ count: 2, total: 0.3 })
  })

  it('today is the date in Spain, not in UTC', () => {
    expect(madridToday(new Date('2026-11-02T23:30:00Z'))).toBe('2026-11-03')
  })
})
