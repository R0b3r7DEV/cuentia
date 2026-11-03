import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import InvoicesTab from '../components/billing/InvoicesTab'
import QuotesTab from '../components/billing/QuotesTab'
import CustomersTab from '../components/billing/CustomersTab'
import ServicesTab from '../components/billing/ServicesTab'

// Each part of billing has its own address, so the side menu can list them and a link can open one.
// ES: Cada parte de la facturación tiene su dirección: el menú lateral las lista y un enlace abre una.
const BILLING_TABS = [
  ['invoices', '/invoices', 'bill.invoices'],
  ['quotes', '/presupuestos', 'bill.quotes'],
  ['customers', '/clientes', 'bill.customers'],
  ['services', '/servicios', 'bill.services'],
]

/**
 * The billing section: invoices, quotes, and the catalogs they draw from (customers, services). The tab comes
 * from the address; on a phone the sub-tabs switch between them, on a computer the side menu does.
 * ES: El apartado de facturación. La pestaña sale de la dirección; en el móvil se cambia con las
 * sub-pestañas y en el ordenador con el menú lateral.
 */
export default function BillingPage({ tab = 'invoices' }) {
  const { t } = useTranslation()
  const { state } = useLocation()
  // Arriving from the installation designer opens the quotes with its materials already loaded.
  const prefill = tab === 'quotes' ? state?.prefill ?? null : null

  return (
    <>
      {/* the open part as the title (the side menu already says it is billing) / la parte abierta como título */}
      <h1 className="page-title">{t(BILLING_TABS.find(([key]) => key === tab)?.[2] ?? 'bill.title')}</h1>

      <nav className="subtabs billing-subtabs" aria-label={t('bill.title')}>
        {BILLING_TABS.map(([key, to, label]) => (
          <NavLink key={key} to={to} className={`subtab${tab === key ? ' active' : ''}`}>{t(label)}</NavLink>
        ))}
      </nav>

      {tab === 'invoices' && <InvoicesTab />}
      {tab === 'quotes' && <QuotesTab prefill={prefill} />}
      {tab === 'customers' && <CustomersTab />}
      {tab === 'services' && <ServicesTab />}
    </>
  )
}
