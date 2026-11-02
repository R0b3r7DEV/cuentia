// What an electrician uses every day, always in sight; everything else under "Más".
// ES: Lo que un electricista usa a diario, siempre a la vista; el resto, bajo «Más».
export const MAIN = [
  ['/', 'nav.today', 'home'],
  ['/partes', 'nav.workOrders', 'wrench'],
  ['/invoices', 'nav.invoicesShort', 'receipt'],
  ['/certificates', 'bill.certificates', 'certificate'],
]
export const MORE = [
  ['/dashboard', 'nav.overview', 'chart'],
  ['/movements', 'nav.movements', 'list'],
  ['/taxes', 'nav.taxes', 'percent'],
  ['/installation', 'bill.installation', 'zap'],
  ['/chat', 'nav.assistant', 'chat'],
  ['/mis-datos', 'nav.myData', 'building'],
  ['/account', 'nav.account', 'user'],
]

/** A route is "current" for its own path and anything under it ("/" only for itself). */
export const isCurrent = (to, pathname) => (to === '/' ? pathname === '/' : pathname === to || pathname.startsWith(to + '/'))
