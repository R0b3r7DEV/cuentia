const MOD_W = 26          // width of one DIN module, in px
const DEV_MODULES = 2     // a 2P / 1P+N device takes two modules
const DEV_W = MOD_W * DEV_MODULES
const DEV_H = 74
const ROW_GAP = 16
const PAD = 14

/** One DIN device: a body, its rating and the lever. */
function Module({ x, y, kind, top, bottom }) {
  return (
    <g className={`din din-${kind}`}>
      <rect x={x} y={y} width={DEV_W - 4} height={DEV_H} rx="3" />
      <rect className="din-lever" x={x + DEV_W / 2 - 9} y={y + 10} width="10" height="16" rx="2" />
      <text className="din-top" x={x + (DEV_W - 4) / 2} y={y + 42}>{top}</text>
      <text className="din-bottom" x={x + (DEV_W - 4) / 2} y={y + 58}>{bottom}</text>
    </g>
  )
}

/**
 * The main panel (CGMP) drawn from the devices actually placed on the plan: the IGA, then one row per
 * differential with the breakers that hang from it. Rows wrap at 12 modules, the width of a domestic row.
 *
 * ES: El cuadro general dibujado a partir de los dispositivos realmente colocados en el plano: el IGA y
 * luego una fila por diferencial con los magnetotérmicos que cuelgan de él. Las filas rompen a 12 módulos,
 * el ancho de una fila doméstica.
 */
export default function PanelBoard({ board, t }) {
  if (!board) return null

  // Lay out: row 0 is the IGA alone; then each differential opens a row with its breakers behind it.
  const rows = [[{ kind: 'iga', top: 'IGA', bottom: `${board.iga.current} A` }]]
  board.differentials.forEach((d) => {
    const row = [{ kind: 'id', top: 'ID', bottom: `${d.current} A · ${d.sensitivity} mA` }]
    board.circuits
      .filter((c) => c.differential === d.index)
      .forEach((c) => row.push({ kind: 'pia', top: c.code, bottom: `${c.breaker} A` }))
    rows.push(row)
  })

  const widest = Math.max(...rows.map((r) => r.length))
  const W = PAD * 2 + Math.max(widest, 4) * DEV_W
  const H = PAD * 2 + rows.length * (DEV_H + ROW_GAP + 12)

  return (
    <div className="panel-board">
      <svg viewBox={`0 0 ${W} ${H}`} width="100%" style={{ maxWidth: W, height: 'auto' }} role="img"
        aria-label={t('inst.board.title')}>
        <rect className="board-case" x="1" y="1" width={W - 2} height={H - 2} rx="8" />
        {rows.map((row, r) => {
          const y = PAD + r * (DEV_H + ROW_GAP + 12)
          return (
            <g key={r}>
              <rect className="board-rail" x={PAD - 6} y={y + DEV_H / 2 - 5} width={W - (PAD - 6) * 2} height="10" rx="2" />
              {row.map((d, i) => <Module key={i} x={PAD + i * DEV_W} y={y} {...d} />)}
            </g>
          )
        })}
      </svg>

      <table className="table">
        <thead>
          <tr>
            <th>{t('inst.board.circuit')}</th>
            <th>{t('inst.board.breaker')}</th>
            <th>{t('inst.board.section')}</th>
            <th className="num">{t('inst.board.points')}</th>
            <th className="num">{t('inst.board.differential')}</th>
          </tr>
        </thead>
        <tbody>
          {board.circuits.map((c, i) => (
            <tr key={i}>
              <td><strong>{c.code}</strong> <span className="muted">{c.name}</span></td>
              <td className="num">{c.breaker} A</td>
              <td className="num">{c.section} mm²</td>
              <td className="num">{c.points}</td>
              <td className="num">#{c.differential}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <p className="msg" style={{ marginBottom: 0 }}>
        {t('inst.board.modules', { total: board.modules.total, rows: board.modules.rows, capacity: board.modules.capacity })}
      </p>
      {board.orphanSockets > 0 && (
        <p className="msg" style={{ color: 'var(--warn-text)', marginBottom: 0 }}>
          ⚠️ {t('inst.board.orphans', { n: board.orphanSockets })}
        </p>
      )}
      <p className="muted" style={{ fontSize: 12, marginTop: 8, marginBottom: 0 }}>{t('inst.board.scope')}</p>
    </div>
  )
}
