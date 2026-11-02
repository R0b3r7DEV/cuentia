import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from '../../i18n/LanguageContext'
import { BLANK_CIE, formFromCertificate, mergeEmpty, profileDefaults } from '../../lib/cie'
import CieWizard from './CieWizard'

/**
 * The certificates: how to file them at the GVA, the step-by-step assistant (to create AND to edit — the old
 * flat form did not know the CERTINS E fields and would have wiped them on save), and the list, each with its
 * "Para la GVA" sheet and its PDF data sheet.
 * ES: Los certificados: cómo presentarlos en la GVA, el asistente paso a paso (para crear y para editar) y la
 * lista, cada uno con su hoja «Para la GVA» y su hoja de datos en PDF.
 */
export default function CertificatesTab({ prefill, editId }) {
  const { t } = useTranslation()
  const [certificates, setCertificates] = useState([])
  const [profile, setProfile] = useState(null) // "Mis datos", to prefill the installation company
  const [wizard, setWizard] = useState(null) // {form, fromInstallation} while the assistant is open

  const load = async () => {
    const res = await fetch('/api/certificates')
    if (!res.ok) return []
    const list = await res.json()
    setCertificates(list)
    return list
  }

  useEffect(() => {
    load().then((list) => {
      // Arriving from "Para la GVA" → "Editar": open that certificate in the assistant.
      const c = editId ? list.find((x) => x.id === editId) : null
      if (c) setWizard({ form: formFromCertificate(c), fromInstallation: false })
    })
    fetch('/api/account/settings').then((r) => (r.ok ? r.json() : null)).then(setProfile).catch(() => {})
  }, [editId])

  // A self-employed electrician is their own installation company: a new CIE starts with "Mis datos".
  const openNew = () => setWizard({ form: { ...BLANK_CIE, ...profileDefaults(profile) }, fromInstallation: false })
  const openEdit = (c) => setWizard({ form: formFromCertificate(c), fromInstallation: false })

  // "Mis datos" may arrive after the assistant opened: fill the company only where still empty.
  useEffect(() => {
    if (profile) setWizard((w) => (w && !w.form.id ? { ...w, form: mergeEmpty(w.form, profileDefaults(profile)).form } : w))
  }, [profile])

  // A design handed over from the installation designer opens the assistant with the technical part filled.
  useEffect(() => {
    if (prefill) setWizard({ form: { ...BLANK_CIE, ...prefill }, fromInstallation: true })
  }, [prefill])

  const remove = async (c) => {
    if (!window.confirm(t('cie.deleteConfirm'))) return
    const res = await fetch(`/api/certificates/${c.id}`, { method: 'DELETE' })
    if (res.ok) await load()
  }

  const missingCie = profile?.missing?.certificate ?? []

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
          <a className="link-btn" href="https://sede.gva.es/es/detall-tramit?id_proc=440" target="_blank" rel="noreferrer">{t('cie.linkSede')} ↗</a>
          <a className="link-btn" href="https://firmaelectronica.gob.es/Home/Descargas.html" target="_blank" rel="noreferrer">{t('cie.linkAutofirma')} ↗</a>
        </div>
      </div>

      <div className="card">
        {wizard ? (
          <CieWizard
            key={wizard.form.id ?? (wizard.fromInstallation ? 'inst' : 'new')}
            initial={wizard}
            onSaved={() => load()}
            onCancel={() => setWizard(null)}
          />
        ) : (
          <>
            {missingCie.length > 0 && (
              <p className="alert-warn" style={{ marginTop: 0 }}>
                {t('my.warnCie', { fields: missingCie.map((k) => t(`my.f.${k}`)).join(', ') })}{' '}
                <Link to="/mis-datos" className="link-btn">{t('my.goFill')}</Link>
              </p>
            )}
            <button className="btn btn-primary" onClick={openNew}>🧭 {t('wiz.open')}</button>
          </>
        )}
      </div>

      <div className="card table-scroll" style={{ padding: 0 }}>
        <table className="table table-cards">
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
                <td className="muted tc-meta" style={{ whiteSpace: 'nowrap' }}>{c.issuedAt}</td>
                <td className="tc-sub">{c.address}</td>
                <td className="tc-title">{c.titularName}</td>
                <td className="right cie-actions tc-actions">
                  <Link className="link-btn" to={`/certificates/${c.id}/gva`}>{t('gva.short')}</Link>
                  <a className="link-btn" href={`/api/certificates/${c.id}/pdf`}>{t('cie.download')}</a>
                  <button className="link-btn" onClick={() => openEdit(c)}>{t('cie.edit')}</button>
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
