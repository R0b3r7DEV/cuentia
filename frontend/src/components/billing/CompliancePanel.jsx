/**
 * What the drawn plan still owes ITC-BT-25 tabla 2, room by room.
 *
 * It only ever reports the **number of points of use**, which is what a plan can tell. Sections, earthing
 * and the measurements a certificate also rests on are outside what any drawing can prove.
 *
 * ES: Lo que al plano dibujado le falta para cumplir la tabla 2 de la ITC-BT-25, estancia por estancia.
 * Solo informa del NÚMERO de puntos de utilización, que es lo que un plano puede decir. Las secciones, las
 * tomas de tierra y las mediciones en las que también se apoya un certificado quedan fuera de un dibujo.
 */
export default function CompliancePanel({ validation, t }) {
  if (!validation?.checked) return null

  const failing = validation.rooms.filter((r) => r.missing.length > 0)

  return (
    <div className="card">
      <div className="verify-bar" style={{ justifyContent: 'space-between' }}>
        <div className="form-sec" style={{ margin: 0, border: 0 }}>{t('inst.check.title')}</div>
        <span className={`chain-badge ${validation.compliant ? 'chain-ok' : 'chain-broken'}`}>
          {validation.compliant
            ? `✓ ${t('inst.check.ok')}`
            : `${validation.missingTotal} ${t('inst.check.missingCount')}`}
        </span>
      </div>

      {validation.compliant ? (
        <p className="msg" style={{ marginBottom: 0 }}>{t('inst.check.okDetail')}</p>
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>{t('inst.check.room')}</th>
              <th>{t('inst.check.missing')}</th>
            </tr>
          </thead>
          <tbody>
            {failing.map((room, i) => (
              <tr key={i}>
                <td>
                  <strong>{t('inst.room.' + room.type)}</strong>
                  <span className="muted num"> · {room.area} m²</span>
                </td>
                <td>
                  {room.missing.map((m, j) => (
                    <div key={j} className="check-miss">
                      <span className="chain-badge chain-broken">{m.circuit}</span>{' '}
                      {t('inst.check.short', { n: m.short, item: m.item })}
                      <span className="muted num"> ({m.have}/{m.need})</span>
                    </div>
                  ))}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      <p className="muted" style={{ fontSize: 12, marginTop: 10, marginBottom: 0 }}>{t('inst.check.scope')}</p>
    </div>
  )
}
