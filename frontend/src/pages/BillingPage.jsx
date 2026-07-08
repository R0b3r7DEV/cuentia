import { useState } from 'react'
import { useTranslation } from '../i18n/LanguageContext'
import InvoicesTab from '../components/billing/InvoicesTab'
import QuotesTab from '../components/billing/QuotesTab'
import CustomersTab from '../components/billing/CustomersTab'
import ServicesTab from '../components/billing/ServicesTab'
import CertificatesTab from '../components/billing/CertificatesTab'
import InstallationTab from '../components/billing/InstallationTab'

/**
 * The billing section, grouped into sub-tabs: invoices, customers (and later quotes + services).
 * ES: El apartado de facturación, agrupado en sub-pestañas: facturas, clientes (y luego presupuestos +
 * servicios).
 */
export default function BillingPage() {
  const { t } = useTranslation()
  const [tab, setTab] = useState('invoices')
  // Lets one tab hand data to another (e.g. a design → a prefilled CIE or quote).
  // ES: Permite que una pestaña pase datos a otra (p.ej. un diseño → un CIE o presupuesto prellenado).
  const [prefill, setPrefill] = useState(null)

  const selectTab = (key) => { setPrefill(null); setTab(key) }
  const navigate = (key, data) => { setPrefill({ tab: key, data }); setTab(key) }
  const pf = (key) => (prefill && prefill.tab === key ? prefill.data : null)

  const tabs = [
    ['invoices', t('bill.invoices')],
    ['quotes', t('bill.quotes')],
    ['customers', t('bill.customers')],
    ['services', t('bill.services')],
    ['certificates', t('bill.certificates')],
    ['installation', t('bill.installation')],
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
      {tab === 'certificates' && <CertificatesTab prefill={pf('certificates')} />}
      {tab === 'installation' && <InstallationTab onNavigate={navigate} />}
    </>
  )
}
