import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react'

// The backend accepts signatures up to 2000 × 1000 px (ImageSanitizer).
const MAX_W = 2000
const MAX_H = 1000

/**
 * A canvas the client signs on with a finger (or mouse/pen). Pointer events cover touch, mouse and stylus
 * alike; `touch-action: none` stops the page from scrolling while signing. The canvas is drawn at the
 * screen's pixel density (capped to what the backend accepts) so the stroke is sharp in the PDF, and it is
 * exported as a transparent PNG.
 *
 * ES: Lienzo en el que el cliente firma con el dedo (o ratón/lápiz). Los pointer events sirven para dedo,
 * ratón y lápiz; `touch-action: none` evita que la página se desplace mientras firma. Se dibuja a la
 * densidad de la pantalla (con el tope que acepta el backend) para que el trazo salga nítido en el PDF, y se
 * exporta como PNG transparente.
 */
const SignaturePad = forwardRef(function SignaturePad({ label, onChange }, ref) {
  const canvasRef = useRef(null)
  const drawing = useRef(false)
  const last = useRef(null)
  const [empty, setEmpty] = useState(true)

  // Size the backing store to the element's size × pixel density.
  useEffect(() => {
    const canvas = canvasRef.current
    const fit = () => {
      const { width, height } = canvas.getBoundingClientRect()
      const ratio = Math.min(window.devicePixelRatio || 1, MAX_W / width, MAX_H / height)
      canvas.width = Math.round(width * ratio)
      canvas.height = Math.round(height * ratio)
      const ctx = canvas.getContext('2d')
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
      ctx.lineWidth = 2.4
      ctx.lineCap = 'round'
      ctx.lineJoin = 'round'
      ctx.strokeStyle = '#1a1630'
      setEmpty(true)
    }
    fit()
    window.addEventListener('resize', fit)
    return () => window.removeEventListener('resize', fit)
  }, [])

  const point = (e) => {
    const r = canvasRef.current.getBoundingClientRect()
    return { x: e.clientX - r.left, y: e.clientY - r.top }
  }

  const down = (e) => {
    e.preventDefault()
    canvasRef.current.setPointerCapture?.(e.pointerId)
    drawing.current = true
    last.current = point(e)
    // a single tap leaves a dot
    const ctx = canvasRef.current.getContext('2d')
    ctx.beginPath()
    ctx.arc(last.current.x, last.current.y, 1.2, 0, Math.PI * 2)
    ctx.fillStyle = ctx.strokeStyle
    ctx.fill()
    if (empty) { setEmpty(false); onChange?.(false) }
  }

  const move = (e) => {
    if (!drawing.current) return
    e.preventDefault()
    const p = point(e)
    const ctx = canvasRef.current.getContext('2d')
    ctx.beginPath()
    ctx.moveTo(last.current.x, last.current.y)
    ctx.lineTo(p.x, p.y)
    ctx.stroke()
    last.current = p
  }

  const up = () => { drawing.current = false }

  const clear = () => {
    const canvas = canvasRef.current
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height)
    setEmpty(true)
    onChange?.(true)
  }

  useImperativeHandle(ref, () => ({
    isEmpty: () => empty,
    toDataURL: () => canvasRef.current.toDataURL('image/png'),
    clear,
  }))

  return (
    <canvas
      ref={canvasRef}
      className="sig-pad"
      aria-label={label}
      role="img"
      onPointerDown={down}
      onPointerMove={move}
      onPointerUp={up}
      onPointerCancel={up}
      onPointerLeave={up}
    />
  )
})

export default SignaturePad
