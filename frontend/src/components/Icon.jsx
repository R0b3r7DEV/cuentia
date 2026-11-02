/**
 * Line icons drawn in the app's own colour (currentColor), the same on every phone — emoji change from one
 * device to another and look like a toy. Paths in the style of Lucide (ISC licence), kept to the few we use.
 * ES: Iconos de línea en el color del texto, iguales en todos los móviles (los emoji cambian de un aparato a
 * otro y parecen de juguete). Trazos al estilo de Lucide, solo los que usamos.
 */
const PATHS = {
  chart: ['M3 3v18h18', 'M7 15v2', 'M11 11v6', 'M15 7v10', 'M19 12v5'],
  wrench: ['M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z'],
  certificate: ['M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z', 'M14 3v6h6', 'M12.5 11 10 15h4l-2.5 4'],
  zap: ['M13 2 4 14h7l-1 8 9-12h-7z'],
  chat: ['M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z'],
  building: ['M3 21h18', 'M5 21V7l7-4 7 4v14', 'M9 21v-5h6v5', 'M9 10h.01M15 10h.01M9 13h.01M15 13h.01'],
  user: ['M20 21a8 8 0 0 0-16 0', 'M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
  logout: ['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'M16 17l5-5-5-5', 'M21 12H9'],
  sun: ['M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4'],
  moon: ['M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z'],
  chevronDown: ['m6 9 6 6 6-6'],
}

export default function Icon({ name, size = 20, className = 'icon', ...rest }) {
  const d = PATHS[name]
  if (!d) return null
  return (
    <svg className={className} width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false" {...rest}>
      {d.map((p) => <path key={p} d={p} />)}
    </svg>
  )
}
