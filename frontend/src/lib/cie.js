// Pure logic of the CIE: the blank form, the guided steps and their fields, and how a bill read by Claude is
// merged in. Kept out of the components so it can be tested with Vitest.
// ES: Lógica pura del CIE: el formulario vacío, los pasos guiados y sus campos, y cómo se incorpora una factura
// leída por Claude. Fuera de los componentes para poder probarla con Vitest.

export const BLANK_CIE = {
  id: null, installationType: 'nueva', useType: 'vivienda', issuedAt: '',
  address: '', postalCode: '', locality: '', province: '',
  titularName: '', titularNif: '', titularAddress: '', cups: '',
  companyName: '', companyRegNumber: '', companyNif: '', installerName: '', installerLicense: '',
  maxPower: '', installedPower: '', voltage: '', supplyType: '', earthingScheme: 'TT', circuits: '',
  derivationSection: '', igaCurrent: '', differentialSensitivity: '', earthResistance: '', earthConductorSection: '',
  observations: '',
}

/** The guided steps, in order, with the fields each one fills. */
export const STEPS = [
  { id: 'start', fields: [] },
  { id: 'client', fields: ['titularName', 'titularNif', 'address', 'postalCode', 'locality', 'province', 'cups', 'titularAddress'] },
  { id: 'installation', fields: ['installationType', 'useType', 'supplyType', 'voltage', 'maxPower', 'installedPower'] },
  { id: 'protections', fields: ['earthingScheme', 'circuits', 'derivationSection', 'igaCurrent', 'differentialSensitivity', 'earthResistance', 'earthConductorSection'] },
  { id: 'company', fields: ['companyName', 'companyRegNumber', 'companyNif', 'installerName', 'installerLicense'] },
  { id: 'review', fields: ['observations'] },
]

/** Fields required before the PDF. Mirrors CieAssistantController::review(). */
export const REQUIRED = ['address', 'titularName', 'companyName', 'companyRegNumber', 'maxPower', 'supplyType', 'igaCurrent', 'differentialSensitivity']

const filled = (v) => String(v ?? '').trim() !== ''

export const missingRequired = (form) => REQUIRED.filter((k) => !filled(form[k]))

/** Which required fields of one step are still empty. */
export const missingInStep = (form, stepId) =>
  (STEPS.find((s) => s.id === stepId)?.fields ?? []).filter((k) => REQUIRED.includes(k) && !filled(form[k]))

/** A self-employed electrician is their own installation company: defaults from "Mis datos". */
export const profileDefaults = (profile) => ({
  companyName: profile?.businessName ?? '',
  companyNif: profile?.taxId ?? '',
  companyRegNumber: profile?.installerRegNumber ?? '',
  installerName: profile?.businessName ?? '',
})

/** Fill only what is empty: what the electrician already typed always wins over what Claude read. */
export function mergeEmpty(form, values) {
  const next = { ...form }
  const changed = []
  for (const [k, v] of Object.entries(values ?? {})) {
    if (k in BLANK_CIE && filled(v) && !filled(form[k])) {
      next[k] = String(v)
      changed.push(k)
    }
  }
  return { form: next, changed }
}

/**
 * Merge a bill read by Claude ({fields, checks, contractedPowerKw}).
 * Returns the new form, the fields Claude filled (to mark ✨ until reviewed), those that failed a rule check,
 * and the contracted power — offered, never written into "potencia máxima" (they are not the same thing).
 */
export function applyBill(form, result) {
  const { form: next, changed } = mergeEmpty(form, result?.fields)
  return {
    form: next,
    aiFields: changed,
    checks: (result?.checks ?? []).filter((k) => changed.includes(k)),
    contractedPowerKw: result?.contractedPowerKw ?? null,
  }
}
