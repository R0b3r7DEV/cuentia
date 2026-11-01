import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from '../i18n/LanguageContext'
import { STATUSES, countByStatus, filterByStatus, formatCents } from '../lib/workOrders'

const FILTERS = ['all', ...STATUSES]

/** "2026-11-01 10:30" → "1 nov, 10:30" (or just the date when there is no time). */
function when(value) {
  if (!value) return ''
  const d = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('es-ES', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

/**
 * Work orders list: a big "new" button, a filter per status with its count, and one card per order with its
 * status colour. Built for the phone first.
 * ES: Listado de partes: botón grande de "nuevo", un filtro por estado con su contador y una tarjeta por
 * parte con el color de su estado. Pensado primero para el móvil.
 */
export default function WorkOrdersPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [orders, setOrders] = useState(null)
  const [filter, setFilter] = useState('all')
  const [error, setError] = useState(false)

  useEffect(() => {
    fetch('/api/work-orders')
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then(setOrders)
      .catch(() => setError(true))
  }, [])

  const counts = countByStatus(orders ?? [])
  const shown = filterByStatus(orders ?? [], filter)

  return (
    <>
      <h1 className="page-title">{t('wo.title')}</h1>
      <p className="page-subtitle">{t('wo.subtitle')}</p>

      <button className="btn btn-primary wo-new" onClick={() => navigate('/partes/nuevo')}>
        {t('wo.new')}
      </button>

      <div className="subtabs wo-filters" role="tablist" aria-label={t('wo.filterLabel')}>
        {FILTERS.map((f) => (
          <button
            key={f}
            role="tab"
            aria-selected={filter === f}
            className={`subtab${filter === f ? ' active' : ''}`}
            onClick={() => setFilter(f)}
          >
            {t(f === 'all' ? 'wo.filterAll' : `wo.status.${f}`)} <span className="wo-count">{counts[f]}</span>
          </button>
        ))}
      </div>

      {error && <p className="msg error">{t('wo.loadError')}</p>}
      {orders === null && !error && <p className="muted">{t('wo.loading')}</p>}

      {orders !== null && shown.length === 0 && (
        <div className="card empty">
          {filter === 'all' ? t('wo.emptyAll') : t('wo.emptyFilter', { status: t(`wo.status.${filter}`).toLowerCase() })}
        </div>
      )}

      <ul className="wo-list">
        {shown.map((o) => (
          <li key={o.id}>
            <Link to={`/partes/${o.id}`} className={`card wo-card wo-${o.status}`}>
              <div className="wo-card-top">
                <span className={`wo-badge wo-badge-${o.status}`}>{t(`wo.status.${o.status}`)}</span>
                <span className="muted">{when(o.scheduledAt || o.createdAt)}</span>
              </div>
              <strong className="wo-card-title">{o.title}</strong>
              <div className="wo-card-bottom">
                <span className="muted">{o.customer}</span>
                <span className="num">{formatCents(o.totalCents)}</span>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </>
  )
}
