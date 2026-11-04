// The landing page's distribution board, as a real one behaves: nothing gets current without the main switch
// (IGA) and the residual-current device (ID) up; the ID's T button trips it. Pure, so it can be tested.
// ES: El cuadro de la portada, como uno real: nada tiene tensión sin el IGA y el diferencial (ID) subidos; el
// botón T del diferencial lo hace saltar. Funciones puras, para poder probarlas.

/** The three circuits and what each one powers (C1 · C2 · C3). / Los tres circuitos y lo que alimentan. */
export const CIRCUITS = ['wo', 'inv', 'cie']

export const INITIAL_BOARD = { iga: true, id: true, wo: true, inv: true, cie: true }

/** Is this circuit (or device) live? The IGA feeds the ID, the ID feeds the circuits. */
export function isLive(state, key) {
  if (key === 'iga') return state.iga
  if (key === 'id') return state.iga && state.id
  return state.iga && state.id && Boolean(state[key])
}

/**
 * Act on the board: flip a device, or press the ID's test button. Returns the new state and, when the change
 * deserves a word, a message key (to translate). / Devuelve el estado nuevo y, si toca, la clave de un aviso.
 */
export function act(state, target) {
  if (target === 'test') {
    if (!state.id) return { state, message: 'board.alreadyTripped', tripped: false }
    return { state: { ...state, id: false }, message: 'board.tripped', tripped: true }
  }
  if (!(target in state)) return { state, message: null, tripped: false }
  const next = { ...state, [target]: !state[target] }
  let message = null
  if (target === 'id' && next.id) message = 'board.rearmed'
  if (target === 'iga' && !next.iga) message = 'board.igaOff'
  return { state: next, message, tripped: false }
}

/** One line for the status chip. / Una línea para la etiqueta de estado. */
export const statusKey = (state) => (!state.iga ? 'board.statusIga' : !state.id ? 'board.statusTripped' : 'board.statusOn')
