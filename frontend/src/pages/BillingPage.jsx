import { useState } from 'react'
import { useTranslation } from '../i18n/LanguageContext'
import InvoicesTab from '../components/billing/InvoicesTab'
import QuotesTab from '../components/billing/QuotesTab'
import CustomersTab from '../components/billing/CustomersTab'
import ServicesTab from '../components/billing/ServicesTab'
import CertificatesTab from '../components/billing/CertificatesTab'

/**
 * The billing section, grouped into sub-tabs: invoices, customers (and later quotes + services).
 * ES: El apartado de facturación, agrupado en sub-pestañas: facturas, clientes (y luego presupuestos +
 * servicios).
 */
export default function BillingPage() {
  const { t } = useTranslation()
  const [tab, setTab] = useState('invoices')

  const tabs = [
    ['invoices', t('bill.invoices')],
    ['quotes', t('bill.quotes')],
    ['customers', t('bill.customers')],
    ['services', t('bill.services')],
    ['certificates', t('bill.certificates')],
  ]

  return (
    <>
      <h1 className="page-title">{t('bill.title')}</h1>

      <div className="subtabs">
        {tabs.map(([key, label]) => (
          <button
            key={key}
            className={`subtab${tab === key ? ' active' : ''}`}
            onClick={() => setTab(key)}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'invoices' && <InvoicesTab />}
      {tab === 'quotes' && <QuotesTab />}
      {tab === 'customers' && <CustomersTab />}
      {tab === 'services' && <ServicesTab />}
      {tab === 'certificates' && <CertificatesTab />}
    </>
  )
}
