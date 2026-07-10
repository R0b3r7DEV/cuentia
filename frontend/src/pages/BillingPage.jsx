import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import InvoicesTab from '../components/billing/InvoicesTab'
import QuotesTab from '../components/billing/QuotesTab'
import CustomersTab from '../components/billing/CustomersTab'
import ServicesTab from '../components/billing/ServicesTab'

/**
 * The billing section: invoices, quotes, and the catalogs they draw from (customers, services).
 * Certificates and the installation designer used to live here as sub-tabs; they are top-level routes now.
 * ES: El apartado de facturación: facturas, presupuestos y los catálogos de los que tiran (clientes,
 * servicios). Certificados e Instalación vivían aquí como sub-pestañas; ahora son rutas propias.
 */
export default function BillingPage() {
  const { t } = useTranslation()
  const { state } = useLocation()
  // Arriving from the installation designer opens the quotes tab with its materials already loaded.
  const [tab, setTab] = useState(state?.tab ?? 'invoices')
  const [prefill, setPrefill] = useState(state?.tab ? { tab: state.tab, data: state.prefill } : null)

  const selectTab = (key) => { setPrefill(null); setTab(key) }
  const pf = (key) => (prefill && prefill.tab === key ? prefill.data : null)

  const tabs = [
    ['invoices', t('bill.invoices')],
    ['quotes', t('bill.quotes')],
    ['customers', t('bill.customers')],
    ['services', t('bill.services')],
  ]

  return (
    <>
      <h1 className="page-title">{t('bill.title')}</h1>

      <div className="subtabs">
        {tabs.map(([key, label]) => (
          <button
            key={key}
            className={`subtab${tab === key ? ' active' : ''}`}
            onClick={() => selectTab(key)}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'invoices' && <InvoicesTab />}
      {tab === 'quotes' && <QuotesTab prefill={pf('quotes')} />}
      {tab === 'customers' && <CustomersTab />}
      {tab === 'services' && <ServicesTab />}
    </>
  )
}
