import { useLocation } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import CertificatesTab from '../components/billing/CertificatesTab'

/**
 * Electrical installation certificates (CIE). Reachable straight from the navbar, and also as the
 * landing point when the installation designer hands over a computed design (router `state.prefill`).
 * ES: Certificados de instalación eléctrica (CIE). Se llega desde la navbar y también cuando el
 * diseñador de instalaciones traspasa un diseño ya calculado (`state.prefill` del router).
 */
export default function CertificatesPage() {
  const { t } = useTranslation()
  const { state } = useLocation()

  return (
    <>
      <h1 className="page-title">{t('bill.certificates')}</h1>
      <CertificatesTab prefill={state?.prefill ?? null} />
    </>
  )
}
