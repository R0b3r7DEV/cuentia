import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'
import Stat from '../components/Stat'
import { useTranslation } from '../i18n/LanguageContext'

export default function TaxesPage() {
  const { vat, irpf } = useOutletContext()
  const { t } = useTranslation()

  return (
    <>
      <h1 className="page-title">{t('tax.title')}</h1>
      <p className="page-subtitle">{t('tax.subtitle')}</p>

      {vat && (
        <div className="card">
          <h2>{t('vat.title')}</h2>
          <div className="stat-row">
            <Stat label={t('vat.output')} value={eur(vat.outputVat)} color="var(--chart-1)" />
            <Stat label={t('vat.input')} value={eur(vat.inputVat)} color="var(--chart-2)" />
            <Stat label={Number(vat.net) >= 0 ? t('vat.netPay') : t('vat.netReclaim')} value={eur(Math.abs(Number(vat.net)))} />
          </div>
          <p className="msg" style={{ marginTop: 12 }}>{t('vat.note')}</p>
        </div>
      )}

      {irpf && (
        <div className="card">
          <h2>{t('irpf.title', { year: irpf.year })}</h2>

          {irpf.nextDeadline && irpf.nextDeadline.daysLeft <= 30 && (
            <div className="alert-warn">
              {t('irpf.alert', { q: irpf.nextDeadline.quarter, date: irpf.nextDeadline.date, days: irpf.nextDeadline.daysLeft })}
            </div>
          )}

          <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>{t('col.quarter')}</th>
                <th className="right">{t('col.net')}</th>
                <th className="right">{t('col.payment')}</th>
                <th>{t('col.deadline')}</th>
              </tr>
            </thead>
            <tbody>
              {irpf.quarters.map((q) => (
                <tr key={q.quarter}>
                  <td>{t('q.label', { q: q.quarter, year: irpf.year })}</td>
                  <td className="right num">{eur(q.net)}</td>
                  <td className="right num" style={{ fontWeight: 600 }}>{eur(q.payment)}</td>
                  <td className="muted">{q.deadline}</td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>
          <p className="msg" style={{ marginTop: 12 }}>{t('irpf.note')}</p>
        </div>
      )}
    </>
  )
}
