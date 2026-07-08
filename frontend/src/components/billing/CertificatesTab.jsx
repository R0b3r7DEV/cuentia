import { useEffect, useState } from 'react'
import { useTranslation } from '../../i18n/LanguageContext'

const blank = {
  id: null, installationType: 'nueva', useType: 'vivienda', issuedAt: '',
  address: '', postalCode: '', locality: '', province: '',
  titularName: '', titularNif: '', titularAddress: '',
  companyName: '', companyRegNumber: '', companyNif: '', installerName: '', installerLicense: '',
  maxPower: '', installedPower: '', voltage: '', supplyType: '', earthingScheme: 'TT', circuits: '',
  derivationSection: '', igaCurrent: '', differentialSensitivity: '', earthResistance: '', earthConductorSection: '',
  observations: '',
}

export default function CertificatesTab({ prefill }) {
  const { t } = useTranslation()
  const [certificates, setCertificates] = useState([])
  const [form, setForm] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)

  const load = async () => {
    const res = await fetch('/api/certificates')
    if (res.ok) setCertificates(await res.json())
  }
  useEffect(() => { load() }, [])

  // Open a prefilled draft when arriving from the installation designer.
  useEffect(() => {
    if (prefill) setForm({ ...blank, ...prefill })
  }, [prefill])

  const set = (patch) => setForm((f) => ({ ...f, ...patch }))

  const save = async (e) => {
    e.preventDefault()
    setSaving(true); setMessage(null)
    try {
      const res = await fetch(form.id ? `/api/certificates/${form.id}` : '/api/certificates', {
        method: form.id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setForm(null)
      setMessage(t('cie.savedMsg'))
      await load()
    } catch (err) {
      setMessage(t('common.errorMsg', { msg: err.message }))
    } finally {
      setSaving(false)
    }
  }

  const remove = async (c) => {
    if (!window.confirm(t('cie.deleteConfirm'))) return
    const res = await fetch(`/api/certificates/${c.id}`, { method: 'DELETE' })
    if (res.ok) await load()
  }

  // A labelled input bound to a form key. A plain function (not a component) so React keeps focus
  // while typing. / Una función (no un componente) para que React no pierda el foco al escribir.
  const field = (k, { type = 'text', sm = false, required = false } = {}) => (
    <label className={`field${sm ? ' field-sm' : ''}`} key={k}>
      <span>{t('cie.' + k)}</span>
      <input type={type} required={required} value={form[k] ?? ''} onChange={(e) => set({ [k]: e.target.value })} />
    </label>
  )

  return (
    <>
      <p className="page-subtitle">{t('cie.subtitle')}</p>
      <div className="card cie-note">{t('cie.note')}</div>

      <div className="card">
        <strong>{t('cie.howTitle')}</strong>
        <ol className="cie-steps">
          <li>{t('cie.step1')}</li>
          <li>{t('cie.step2')}</li>
          <li>{t('cie.step3')}</li>
        </ol>
        <div className="doc-links">
          <a className="link-btn" href="https://firmaelectronica.gob.es/Home/Descargas.html" target="_blank" rel="noreferrer">{t('cie.linkAutofirma')} ↗</a>
          <a className="link-btn" href="https://www.accv.es" target="_blank" rel="noreferrer">{t('cie.linkAccv')} ↗</a>
          <a className="link-btn" href="https://sede.gva.es/es/detall-tramit?id_proc=440" target="_blank" rel="noreferrer">{t('cie.linkSede')} ↗</a>
        </div>
      </div>

      <div className="card">
        {form === null ? (
          <button className="btn btn-glass btn-sm" onClick={() => setForm({ ...blank })}>{t('cie.new')}</button>
        ) : (
          <form className="invoice-form" onSubmit={save}>
            <div className="form-sec">{t('cie.secInstallation')}</div>
            <div className="field-row">
              <label className="field field-sm">
                <span>{t('cie.installationType')}</span>
                <select className="bank-select" value={form.installationType} onChange={(e) => set({ installationType: e.target.value })}>
                  {['nueva', 'ampliacion', 'reforma'].map((v) => <option key={v} value={v}>{t('cie.type.' + v)}</option>)}
                </select>
              </label>
              <label className="field field-sm">
                <span>{t('cie.useType')}</span>
                <select className="bank-select" value={form.useType} onChange={(e) => set({ useType: e.target.value })}>
                  {['vivienda', 'local', 'industrial', 'garaje', 'comunes', 'agricola', 'otros'].map((v) => <option key={v} value={v}>{t('cie.use.' + v)}</option>)}
                </select>
              </label>
              {field('issuedAt', { type: 'date', sm: true })}
            </div>
            <div className="field-row">
              {field('address', { required: true })}
              {field('postalCode', { sm: true })}
            </div>
            <div className="field-row">{field('locality')}{field('province')}</div>

            <div className="form-sec">{t('cie.secTitular')}</div>
            <div className="field-row">{field('titularName', { required: true })}{field('titularNif', { sm: true })}</div>
            <div className="field-row">{field('titularAddress')}</div>

            <div className="form-sec">{t('cie.secCompany')}</div>
            <div className="field-row">{field('companyName', { required: true })}{field('companyRegNumber', { sm: true })}</div>
            <div className="field-row">{field('companyNif', { sm: true })}{field('installerName')}{field('installerLicense', { sm: true })}</div>

            <div className="form-sec">{t('cie.secTech')}</div>
            <div className="field-row">
              {field('maxPower', { type: 'number', sm: true })}{field('installedPower', { type: 'number', sm: true })}
              {field('voltage', { type: 'number', sm: true })}
              <label className="field field-sm">
                <span>{t('cie.supplyType')}</span>
                <select className="bank-select" value={form.supplyType} onChange={(e) => set({ supplyType: e.target.value })}>
                  <option value="">—</option>
                  {['monofasico', 'trifasico'].map((v) => <option key={v} value={v}>{t('cie.supply.' + v)}</option>)}
                </select>
              </label>
            </div>
            <div className="field-row">
              {field('earthingScheme', { sm: true })}{field('circuits', { type: 'number', sm: true })}{field('derivationSection', { sm: true })}{field('igaCurrent', { sm: true })}
            </div>
            <div className="field-row">
              {field('differentialSensitivity', { sm: true })}{field('earthResistance', { sm: true })}{field('earthConductorSection', { sm: true })}
            </div>
            <label className="field">
              <span>{t('cie.observations')}</span>
              <textarea className="cie-textarea" value={form.observations ?? ''} onChange={(e) => set({ observations: e.target.value })} />
            </label>

            <div className="verify-bar">
              <button className="btn btn-glass btn-sm" type="submit" disabled={saving}>{saving ? t('cie.saving') : t('cie.save')}</button>
              <button type="button" className="link-btn" onClick={() => setForm(null)}>{t('cie.cancel')}</button>
            </div>
          </form>
        )}
        {message && <p className="msg">{message}</p>}
      </div>

      <div className="card table-scroll" style={{ padding: 0 }}>
        <table className="table">
          <thead>
            <tr>
              <th>{t('cie.issuedAt')}</th>
              <th>{t('cie.address')}</th>
              <th>{t('cie.titularName')}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {certificates.map((c) => (
              <tr key={c.id}>
                <td className="muted" style={{ whiteSpace: 'nowrap' }}>{c.issuedAt}</td>
                <td>{c.address}</td>
                <td>{c.titularName}</td>
                <td className="right">
                  <a className="link-btn" href={`/api/certificates/${c.id}/pdf`}>{t('cie.download')}</a>
                  <button className="link-btn" onClick={() => setForm({ ...blank, ...c, voltage: c.voltage ?? '', circuits: c.circuits ?? '' })}>{t('cie.edit')}</button>
                  <button className="link-btn danger-link" onClick={() => remove(c)}>{t('cie.delete')}</button>
                </td>
              </tr>
            ))}
            {certificates.length === 0 && (
              <tr><td className="empty" colSpan={4}>{t('cie.empty')}</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
