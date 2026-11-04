// "Hoy": what an electrician looks at first thing — built only from data the app really has.
// Pure functions, kept out of the page so they can be tested with Vitest.
// ES: «Hoy»: lo primero que mira un electricista, solo con datos que la app tiene de verdad.
import { formFromCertificate, missingRequired } from './cie'

const ACTIVE = ['pendiente', 'en_curso']

/** Pending and in-progress work orders, the scheduled ones first, soonest first. */
export function activeOrders(orders) {
  // [0 = scheduled, 1 = not], then the date: scheduled ones first, soonest first.
  const key = (o) => [o.scheduledAt ? 0 : 1, o.scheduledAt || o.createdAt || '']
  const cmp = (a, b) => (a < b ? -1 : a > b ? 1 : 0)
  return (orders ?? []).filter((o) => ACTIVE.includes(o.status)).sort((a, b) => {
    const [ra, da] = key(a), [rb, db] = key(b)
    return ra - rb || cmp(da, db)
  })
}

/** Finished work orders not yet turned into an invoice: the next money to make. */
export const ordersToInvoice = (orders) => (orders ?? []).filter((o) => o.status === 'terminado' && !o.invoiced)

/** Certificates with required CERTINS fields still empty, with what is missing (the CIE keys). */
export function incompleteCertificates(certificates, model) {
  return (certificates ?? [])
    .map((c) => ({ id: c.id, titularName: c.titularName, address: c.address, missing: missingRequired(formFromCertificate(c), model) }))
    .filter((c) => c.missing.length > 0)
}

/** Invoices issued in the month of `today` (YYYY-MM-DD): how many and their total, in euros. */
export function invoicedThisMonth(invoices, today) {
  const month = String(today ?? '').slice(0, 7)
  const these = (invoices ?? []).filter((i) => String(i.issuedAt ?? '').startsWith(month))
  const total = these.reduce((sum, i) => sum + Math.round(Number(i.total ?? 0) * 100), 0) / 100
  return { count: these.length, total }
}

/** Today's date in Spain as YYYY-MM-DD (the backend stores business dates in Europe/Madrid too). */
export const madridToday = (now = new Date()) =>
  new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Madrid', year: 'numeric', month: '2-digit', day: '2-digit' }).format(now)

/** Days a payment can wait before it counts as late (the usual 30 days of Spanish commercial practice). */
export const PAYMENT_DAYS = 30

/**
 * Issued invoices not yet paid, oldest first, with their age in days and whether they are late; and the total
 * owed, in euros. ES: Facturas emitidas sin cobrar, de la más antigua a la más nueva, con sus días y si van tarde.
 */
export function unpaidInvoices(invoices, today) {
  const day = (iso) => Date.UTC(+iso.slice(0, 4), +iso.slice(5, 7) - 1, +iso.slice(8, 10))
  const now = day(String(today ?? ''))
  const items = (invoices ?? [])
    .filter((i) => (i.status ?? 'issued') === 'issued' && !i.paidAt)
    .map((i) => {
      const days = Math.max(0, Math.round((now - day(String(i.issuedAt))) / 86400000))
      return { ...i, days, late: days > PAYMENT_DAYS }
    })
    .sort((a, b) => b.days - a.days)
  const total = items.reduce((sum, i) => sum + Math.round(Number(i.total ?? 0) * 100), 0) / 100
  return { total, items }
}
