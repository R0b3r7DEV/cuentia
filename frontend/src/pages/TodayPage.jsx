import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { formatCents } from '../lib/workOrders'
import { eur } from '../lib/format'
import { activeOrders, incompleteCertificates, invoicedThisMonth, madridToday, ordersToInvoice } from '../lib/today'
import Icon from '../components/Icon'

const getJson = (url) => fetch(url).then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))

/**
 * "Hoy", the first screen after logging in: open work orders, finished ones still to invoice, certificates
 * missing data and what has been invoiced this month — each one a tap away from where it is fixed.
 * ES: «Hoy», la primera pantalla al entrar: partes abiertos, terminados sin facturar, certificados a medias y
 * lo facturado este mes, cada cosa a un toque de donde se arregla.
 */
export default function TodayPage() {
  const { t, lang } = useTranslation()
  const [data, setData] = useState(null)
  const [failed, setFailed] = useState(false)
  const today = madridToday()

  useEffect(() => {
    Promise.allSettled([getJson('/api/work-orders'), getJson('/api/certificates'), getJson('/api/invoices'), getJson('/api/certificates/model')])
      .then(([orders, certs, invoices, model]) => {
        if ([orders, certs, invoices].every((r) => r.status === 'rejected')) { setFailed(true); return }
        const v = (r) => (r.status === 'fulfilled' ? r.value : [])
        setData({
          active: activeOrders(v(orders)),
          toInvoice: ordersToInvoice(v(orders)),
          certs: incompleteCertificates(v(certs), model.status === 'fulfilled' ? model.value : null),
          month: invoicedThisMonth(v(invoices), today),
        })
      })
  }, [today])

  const dateLabel = new Intl.DateTimeFormat(lang === 'es' ? 'es-ES' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'Europe/Madrid' }).format(new Date())

  if (failed) return <p className="msg error">{t('today.loadError')}</p>
  if (!data) return <p className="muted">{t('wo.loading')}</p>

  const allClear = data.active.length === 0 && data.toInvoice.length === 0 && data.certs.length === 0

  return (
    <div className="today">
      <h1 className="page-title">{t('today.title')}</h1>
      <p className="page-subtitle today-date">{dateLabel}</p>

      <div className="today-kpis">
        <Link to="/partes" className="card today-kpi">
          <b className="num">{data.active.length}</b>
          <span>{t('today.kpiActive')}</span>
        </Link>
        <Link to="/invoices" className="card today-kpi today-kpi-hot">
          <b className="num">{eur(data.month.total)}</b>
          <span>{t('today.kpiMonth', { n: data.month.count })}</span>
        </Link>
      </div>

      {allClear && <div className="card empty">{t('today.allClear')}</div>}

      {data.toInvoice.length > 0 && (
        <section className="today-sec">
          <div className="today-sec-head"><h2>{t('today.toInvoice')}</h2></div>
          <p className="muted today-hint">{t('today.toInvoiceHint')}</p>
          <ul className="wo-list">
            {data.toInvoice.map((o) => (
              <li key={o.id}>
                <Link to={`/partes/${o.id}`} className="card wo-card wo-terminado">
                  <div className="wo-card-top">
                    <strong className="today-title">{o.title}</strong>
                    <span className="num">{formatCents(o.totalCents)}</span>
                  </div>
                  <span className="muted">{o.customer}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      {data.active.length > 0 && (
        <section className="today-sec">
          <div className="today-sec-head"><h2>{t('today.active')}</h2><Link to="/partes" className="link-btn">{t('today.seeAll')}</Link></div>
          <ul className="wo-list">
            {data.active.slice(0, 5).map((o) => (
              <li key={o.id}>
                <Link to={`/partes/${o.id}`} className={`card wo-card wo-${o.status}`}>
                  <div className="wo-card-top">
                    <strong className="today-title">{o.title}</strong>
                    <span className={`wo-badge wo-badge-${o.status}`}>{t(`wo.status.${o.status}`)}</span>
                  </div>
                  <span className="muted">{[o.customer, o.scheduledAt].filter(Boolean).join(' · ')}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      {data.certs.length > 0 && (
        <section className="today-sec">
          <div className="today-sec-head"><h2>{t('today.certs')}</h2><Link to="/certificates" className="link-btn">{t('today.seeAll')}</Link></div>
          <ul className="wo-list">
            {data.certs.slice(0, 5).map((c) => (
              <li key={c.id}>
                <Link to={`/certificates/${c.id}/gva`} className="card wo-card today-cert">
                  <div className="wo-card-top">
                    <strong className="today-title">{c.titularName}</strong>
                    <span className="today-missing-count">{t('today.missingCount', { n: c.missing.length })}</span>
                  </div>
                  <span className="muted">{c.address}</span>
                  <span className="today-missing mono">
                    {c.missing.slice(0, 2).map((k) => t('cie.' + k)).join(' · ')}
                    {c.missing.length > 2 ? ` · +${c.missing.length - 2}` : ''}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      <div className="today-fab">
        <Link to="/partes/nuevo" className="btn btn-primary today-new"><Icon name="plus" size={20} />{t('today.newOrder')}</Link>
      </div>
    </div>
  )
}
