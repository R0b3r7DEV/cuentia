import { fitWithin } from './workOrders'

// Shrinks a phone photo before it is uploaded: a 3–8 MB camera JPEG becomes ~150–400 KB at ≤ 1600 px,
// so it uploads fast over a weak signal at the site. The server re-encodes it anyway (ADR 0005); this is
// about bandwidth, not security. createImageBitmap applies the EXIF rotation, so portrait photos stay
// upright once the metadata is gone.
//
// ES: Reduce la foto del móvil antes de subirla: un JPEG de 3–8 MB pasa a ~150–400 KB a ≤ 1600 px y sube
// rápido con poca cobertura. El servidor la vuelve a codificar igualmente (ADR 0005); esto es por ancho de
// banda, no por seguridad. createImageBitmap aplica la rotación del EXIF, así las fotos en vertical siguen
// derechas cuando se pierden los metadatos.

export const PHOTO_MAX_SIDE = 1600

export async function downscalePhoto(file, maxSide = PHOTO_MAX_SIDE, quality = 0.82) {
  const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
  const { width, height } = fitWithin(bitmap.width, bitmap.height, maxSide)

  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const ctx = canvas.getContext('2d')
  ctx.fillStyle = '#fff' // a transparent PNG would turn black in JPEG
  ctx.fillRect(0, 0, width, height)
  ctx.drawImage(bitmap, 0, 0, width, height)
  bitmap.close?.()

  return new Promise((resolve, reject) => {
    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('encode'))), 'image/jpeg', quality)
  })
}
