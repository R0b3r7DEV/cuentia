import { useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { useTranslation } from '../i18n/LanguageContext'

export default function AccountPage() {
  const { reload } = useOutletContext()
  const { user, deleteAccount } = useAuth()
  const { t } = useTranslation()
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState(null)

  const clearData = async () => {
    if (!window.confirm(t('account.clearConfirm'))) return
    setBusy(true); setMessage(null)
    try {
      const res = await fetch('/api/account/clear', { method: 'POST' })
      const data = await res.json()
      setMessage(t('account.cleared', { n: data.cleared }))
      reload()
    } catch {
      setMessage(t('common.errorMsg', { msg: 'error' }))
    } finally {
      setBusy(false)
    }
  }

  const removeAccount = async () => {
    if (!window.confirm(t('account.deleteConfirm'))) return
    setBusy(true)
    try { await deleteAccount() } finally { setBusy(false) }
  }

  return (
    <>
      <h1 className="page-title">{t('account.title')}</h1>
      <p className="page-subtitle">{user?.email}</p>

      <div className="card">
        <h2>{t('account.dataTitle')}</h2>
        <p className="msg" style={{ marginTop: 0 }}>{t('account.dataDesc')}</p>
        <button className="btn btn-glass btn-sm" style={{ marginTop: '0.75rem' }} onClick={clearData} disabled={busy}>{t('account.clear')}</button>
        {message && <p className="msg">{message}</p>}
      </div>

      <div className="card">
        <h2>{t('account.dangerTitle')}</h2>
        <p className="msg" style={{ marginTop: 0 }}>{t('account.deleteDesc')}</p>
        <button className="btn btn-danger-glass btn-sm" style={{ marginTop: '0.75rem' }} onClick={removeAccount} disabled={busy}>{t('account.delete')}</button>
      </div>

      <div className="card">
        <h2>{t('account.privacyTitle')}</h2>
        <p className="msg" style={{ marginTop: 0 }}>{t('account.privacyBody')}</p>
      </div>
    </>
  )
}
