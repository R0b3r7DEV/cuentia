// Pure helpers for "Mis datos": the IBAN check (the same mod-97 rule as the backend) and what is still
// missing to issue each kind of document.
// ES: Ayudas puras para «Mis datos»: la comprobación del IBAN (la misma regla mod-97 que el backend) y lo
// que aún falta para emitir cada documento.

const IBAN_LENGTHS = { ES: 24, PT: 25, FR: 27, DE: 22, IT: 27, AD: 24, GB: 22, NL: 18, BE: 16 }

/** "es91 2100-0418…" → "ES912100…" */
export const normalizeIban = (v) => String(v ?? '').replace(/[^A-Za-z0-9]/g, '').toUpperCase()

export function isValidIban(value) {
  const iban = normalizeIban(value)
  if (!/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(iban)) return false
  const len = IBAN_LENGTHS[iban.slice(0, 2)]
  if (len && iban.length !== len) return false
  const digits = (iban.slice(4) + iban.slice(0, 4)).replace(/[A-Z]/g, (c) => String(c.charCodeAt(0) - 55))
  let rest = 0
  for (let i = 0; i < digits.length; i += 7) rest = Number(String(rest) + digits.slice(i, i + 7)) % 97
  return rest === 1
}

/** "ES9121000418450200051332" → "ES91 2100 0418 4502 0005 1332" */
export const formatIban = (v) => normalizeIban(v).replace(/(.{4})/g, '$1 ').trim()

/** The fields each document needs from "Mis datos". Mirrors AccountController::settingsPayload(). */
export const REQUIRED = {
  invoice: ['businessName', 'taxId', 'fiscalAddress'],
  certificate: ['businessName', 'taxId', 'installerRegNumber'],
}

/** Which required fields are still empty in a (possibly unsaved) profile form. */
export const missingFor = (profile, doc) => REQUIRED[doc].filter((k) => !String(profile?.[k] ?? '').trim())
