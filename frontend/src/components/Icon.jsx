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
  home: ['M3 10.5 12 3l9 7.5', 'M5 9.5V21h14V9.5', 'M10 21v-6h4v6'],
  receipt: ['M5 3h14v18l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21z', 'M9 8h6M9 12h6M9 16h3'],
  more: ['M5 12h.01M12 12h.01M19 12h.01'],
  plus: ['M12 5v14M5 12h14'],
  close: ['M18 6 6 18M6 6l12 12'],
  list: ['M8 6h13M8 12h13M8 18h13', 'M3 6h.01M3 12h.01M3 18h.01'],
  percent: ['M19 5 5 19', 'M6.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z', 'M17.5 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
  check: ['m4 12 5 5L20 6'],
  copy: ['M9 9h11v11H9z', 'M5 15H4V4h11v1'],
  alert: ['M12 3 2 20h20z', 'M12 10v4', 'M12 17h.01'],
  info: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z', 'M12 16v-4', 'M12 8h.01'],
  sparkle: ['M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4z', 'M19 17l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z'],
  lock: ['M6 11h12v10H6z', 'M8 11V7a4 4 0 0 1 8 0v4'],
  compass: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z', 'm16 8-2.5 5.5L8 16l2.5-5.5z'],
  pen: ['M4 20h4L19 9l-4-4L4 16z', 'M14 6l4 4'],
  quote: ['M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z', 'M14 3v6h6', 'M8 13h8M8 17h5'],
  users: ['M16 21a6 6 0 0 0-12 0', 'M10 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M22 21a6 6 0 0 0-4-5.6', 'M16 4.1a4 4 0 0 1 0 7.8'],
  tag: ['M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z', 'M7.5 7.5h.01'],
  globe: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z', 'M2 12h20', 'M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z'],
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
