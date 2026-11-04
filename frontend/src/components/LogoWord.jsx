import { useCallback, useEffect, useRef, useState } from 'react'

// Where each spark flies from the lime dot (px). / Hacia dónde salta cada chispa desde el punto lima.
const SPARKS = [[-22, -26], [16, -32], [30, -10], [-28, -4], [10, 20], [-10, 22], [26, 16], [-16, -38], [36, -24], [-34, 14]]
const SHORT_MS = 900

/**
 * The word mark "Cuentia." with a short-circuit effect: the letters flicker like a faulty tube, split for an
 * instant into cobalt and lime like an arc, and sparks jump from the lime dot. On hover, and — with `auto` —
 * now and then on its own (every 7–14 s). Nothing moves under "reduce motion".
 * ES: El logotipo «Cuentia.» con efecto cortocircuito: las letras parpadean como un fluorescente con mal
 * contacto, se desdoblan un instante en cobalto y lima como un arco, y saltan chispas del punto lima. Al pasar
 * el ratón y, con `auto`, de vez en cuando (cada 7–14 s). Con «reducir movimiento», nada se mueve.
 */
export default function LogoWord({ auto = false }) {
  const [shorting, setShorting] = useState(false)
  const busy = useRef(false)
  const reduce = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

  const fire = useCallback(() => {
    if (reduce || busy.current) return
    busy.current = true
    setShorting(true)
    setTimeout(() => { setShorting(false); busy.current = false }, SHORT_MS)
  }, [reduce])

  useEffect(() => {
    if (!auto || reduce) return undefined
    let timer
    const next = (delay) => { timer = setTimeout(() => { fire(); next(7000 + Math.random() * 7000) }, delay) }
    next(1800) // a first one soon after the page opens / uno al poco de abrir
    return () => clearTimeout(timer)
  }, [auto, reduce, fire])

  return (
    <b className={`logo-word${shorting ? ' shorting' : ''}`} onMouseEnter={fire}>
      Cuentia<i className="logo-dot">.<span className="sparks" aria-hidden="true">
        {SPARKS.map(([dx, dy], i) => <span key={i} style={{ '--dx': `${dx}px`, '--dy': `${dy}px`, '--i': i }} />)}
      </span></i>
    </b>
  )
}
