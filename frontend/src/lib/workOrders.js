// Pure logic for work orders (partes de trabajo): money in integer cents and the status lifecycle.
// Mirrors the backend (WorkOrder::TRANSITIONS, laborBaseCents, totalsCents) so the totals the electrician
// sees while typing are the same, to the cent, as the ones the server — and later the invoice — computes.
//
// ES: Lógica pura de los partes: dinero en céntimos enteros y el ciclo de estados. Replica el backend para
// que los totales que ve el electricista mientras escribe sean, al céntimo, los mismos que calcula el
// servidor y después la factura.

export const STATUSES = ['pendiente', 'en_curso', 'terminado', 'facturado']

/** Manual moves. 'facturado' is never here: only "Convertir en factura" reaches it. */
export const TRANSITIONS = {
  pendiente: ['en_curso'],
  en_curso: ['pendiente', 'terminado'],
  terminado: ['en_curso'],
  facturado: [],
}

export const EDITABLE = ['pendiente', 'en_curso']

export const canEdit = (status) => EDITABLE.includes(status)
export const canTransition = (from, to) => (TRANSITIONS[from] ?? []).includes(to)
export const canConvert = (order) => order?.status === 'terminado' && !order?.convertedInvoice

/**
 * The actions offered for each status, in order: the first one is the big button at the bottom.
 * `confirm` names the question asked before doing it.
 * ES: Las acciones de cada estado, en orden: la primera es el botón grande de abajo.
 */
export function nextActions(status) {
  switch (status) {
    case 'pendiente':
      return [
        { id: 'start', to: 'en_curso', primary: true },
        { id: 'delete', danger: true, confirm: 'wo.confirmDelete' },
      ]
    case 'en_curso':
      return [
        { id: 'finish', to: 'terminado', primary: true },
        { id: 'backToPending', to: 'pendiente' },
        { id: 'delete', danger: true, confirm: 'wo.confirmDelete' },
      ]
    case 'terminado':
      return [
        { id: 'convert', primary: true, confirm: 'wo.confirmConvert' },
        { id: 'pdf' },
        { id: 'reopen', to: 'en_curso', confirm: 'wo.confirmReopen' },
      ]
    case 'facturado':
      return [{ id: 'pdf', primary: true }, { id: 'viewInvoice' }]
    default:
      return []
  }
}

/**
 * "6,50" | "6.50" | 6.5 → 650. Integer cents without float rounding drift; anything that is not a
 * non-negative amount with at most 2 decimals gives null.
 * ES: Importe → céntimos enteros, sin errores de coma flotante; lo que no sea un importe válido da null.
 */
export function toCents(value) {
  if (value === null || value === undefined) return null
  const s = String(value).trim().replace(',', '.')
  if (s === '') return 0
  const m = /^(\d+)(?:\.(\d{0,2}))?$/.exec(s)
  if (!m) return null
  return Number(m[1]) * 100 + Number((m[2] ?? '').padEnd(2, '0'))
}

/** Same as toCents but never null: invalid input counts as 0, for live previews. */
const cents = (v) => toCents(v) ?? 0

/** Line base: unit price in cents × whole quantity (at least 1, like the backend). */
export function lineBaseCents(line) {
  const qty = Math.max(1, Math.trunc(Number(line?.quantity) || 1))
  return cents(line?.unitPrice) * qty
}

/** VAT of one line, rounded half up per line: the rule InvoiceLine applies. */
export function vatCents(baseCents, rate) {
  return Math.floor((baseCents * cents(rate) + 5000) / 10000)
}

/** Labour: hours × rate, both read as hundredths and multiplied as integers, rounded once. */
export function laborBaseCents(hours, rate) {
  return Math.floor((cents(hours) * cents(rate) + 50) / 100)
}

/** Totals of a work order in integer cents: materials + labour, VAT per line. */
export function totals(order) {
  let base = 0
  let vat = 0
  for (const line of order?.lines ?? []) {
    const b = lineBaseCents(line)
    base += b
    vat += vatCents(b, line.vatRate)
  }
  const labor = laborBaseCents(order?.laborHours, order?.laborRate)
  base += labor
  vat += vatCents(labor, order?.laborVatRate)
  return { base, vat, total: base + vat }
}

/** 8470 → "84,70 €". */
export const formatCents = (c) =>
  new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(c / 100)

/** Step the labour hours by quarters of an hour, never below 0. Returns a 2-decimal string. */
export function stepHours(hours, delta) {
  const next = Math.max(0, cents(hours) + Math.round(delta * 100))
  return (next / 100).toFixed(2)
}

/** How many orders per status, plus 'all'. */
export function countByStatus(orders) {
  const counts = { all: orders.length }
  for (const s of STATUSES) counts[s] = 0
  for (const o of orders) if (o.status in counts) counts[o.status] += 1
  return counts
}

export const filterByStatus = (orders, status) =>
  status === 'all' ? orders : orders.filter((o) => o.status === status)

/** Fit a w×h image inside a square of `max` px, never enlarging it. */
export function fitWithin(w, h, max) {
  const k = Math.min(1, max / Math.max(w, h))
  return { width: Math.max(1, Math.round(w * k)), height: Math.max(1, Math.round(h * k)) }
}
