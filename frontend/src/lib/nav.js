// The app's sections, grouped the way an electrician works: on site, billing, money. The side menu (computer)
// lists them all; the phone shows the daily four in the bottom bar and the rest, with the same groups, in "Más".
// ES: Las secciones de la app agrupadas como trabaja un electricista: obra, facturación y dinero. El menú
// lateral (ordenador) las lista todas; el móvil muestra las cuatro diarias abajo y el resto, con los mismos
// grupos, en «Más».
export const GROUPS = [
  { key: null, items: [['/', 'nav.today', 'home']] },
  { key: 'nav.group.work', items: [
    ['/partes', 'nav.workOrders', 'wrench'],
    ['/certificates', 'bill.certificates', 'certificate'],
    ['/installation', 'bill.installation', 'zap'],
  ] },
  { key: 'nav.group.billing', items: [
    ['/invoices', 'nav.invoicesShort', 'receipt'],
    ['/presupuestos', 'bill.quotes', 'quote'],
    ['/clientes', 'bill.customers', 'users'],
    ['/servicios', 'bill.services', 'tag'],
  ] },
  { key: 'nav.group.money', items: [
    ['/dashboard', 'nav.overview', 'chart'],
    ['/movements', 'nav.movements', 'list'],
    ['/taxes', 'nav.taxes', 'percent'],
  ] },
  { key: null, items: [['/chat', 'nav.assistant', 'chat']] },
]

/** Your own data and settings, apart from the daily work: the foot of the side menu. */
export const ACCOUNT = [
  ['/mis-datos', 'nav.myData', 'building'],
  ['/account', 'nav.account', 'user'],
]

// Phone: the bottom bar.
export const MAIN = [
  ['/', 'nav.today', 'home'],
  ['/partes', 'nav.workOrders', 'wrench'],
  ['/invoices', 'nav.invoicesShort', 'receipt'],
  ['/certificates', 'bill.certificates', 'certificate'],
]

/** Phone: what "Más" holds — every group without what the bottom bar already shows, plus the account. */
export const MORE_GROUPS = [
  ...GROUPS.map((g) => ({ ...g, items: g.items.filter(([to]) => !MAIN.some(([m]) => m === to)) })).filter((g) => g.items.length > 0),
  { key: 'nav.group.account', items: ACCOUNT },
]

/** A route is "current" for its own path and anything under it ("/" only for itself). */
export const isCurrent = (to, pathname) => (to === '/' ? pathname === '/' : pathname === to || pathname.startsWith(to + '/'))
