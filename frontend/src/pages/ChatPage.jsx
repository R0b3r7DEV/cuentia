import { useState } from 'react'
import { useTranslation } from '../i18n/LanguageContext'

export default function ChatPage() {
  const { t } = useTranslation()
  const [messages, setMessages] = useState([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(false)

  const send = async (e) => {
    e.preventDefault()
    const question = input.trim()
    if (!question || loading) return
    setInput('')
    setMessages((m) => [...m, { role: 'user', text: question }])
    setLoading(true)
    try {
      const res = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
      setMessages((m) => [...m, { role: 'assistant', text: data.answer, source: data.source }])
    } catch (err) {
      setMessages((m) => [...m, { role: 'assistant', text: `Error: ${err.message}`, source: 'error' }])
    } finally {
      setLoading(false)
    }
  }

  return (
    <>
      <h1 className="page-title">{t('chat.title')}</h1>
      <p className="page-subtitle">{t('chat.subtitle')}</p>

      <div className="card">
        <div className="chat-log">
          {messages.length === 0 && <p className="muted">{t('chat.empty')}</p>}
          {messages.map((m, i) => (
            <div key={i} className={`chat-msg chat-${m.role}`}>
              <div className="chat-role">{m.role === 'user' ? t('chat.you') : t('chat.assistant')}</div>
              <div className="chat-text">{m.text}</div>
              {m.source === 'fallback' && <div className="chat-note">{t('chat.fallbackNote')}</div>}
            </div>
          ))}
          {loading && <p className="muted">{t('chat.sending')}</p>}
        </div>

        <form onSubmit={send} className="chat-form">
          <input
            className="chat-input"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder={t('chat.placeholder')}
            disabled={loading}
          />
          <button className="btn btn-glass btn-sm" type="submit" disabled={loading || !input.trim()}>
            {t('chat.send')}
          </button>
        </form>
      </div>
    </>
  )
}
