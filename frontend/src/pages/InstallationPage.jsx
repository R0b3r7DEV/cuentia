import { useNavigate } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import InstallationTab from '../components/billing/InstallationTab'

/**
 * The electrician's installation designer. Its two hand-offs (prefill a CIE, turn the bill of materials
 * into a quote) now cross a route boundary, so the payload travels in the router's location state.
 * ES: El diseñador de instalaciones. Sus dos traspasos (prellenar un CIE, convertir los materiales en un
 * presupuesto) ahora cruzan una ruta, así que el dato viaja en el `state` del router.
 */
export default function InstallationPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const go = (target, data) => {
    if (target === 'certificates') navigate('/certificates', { state: { prefill: data } })
    else navigate('/invoices', { state: { tab: 'quotes', prefill: data } })
  }

  return (
    <>
      <h1 className="page-title">{t('bill.installation')}</h1>
      <InstallationTab onNavigate={go} />
    </>
  )
}
