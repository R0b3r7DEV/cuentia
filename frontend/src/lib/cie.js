// Pure logic of the CIE, laid out as the GVA's CERTINS E model (sections A–G) plus the data that belongs to
// the Technical Design Report (MTD). Kept out of the components so it can be tested with Vitest.
// ES: Lógica pura del CIE, ordenada como el modelo CERTINS E de la GVA (secciones A–G) más los datos de la MTD.

export const BLANK_CIE = {
  id: null, issuedAt: '', installationType: 'nueva',
  // A · Titular
  titularName: '', titularNif: '', titularAddress: '',
  // B · Suministro
  supplySource: 'red', distributor: '', cups: '',
  // C · Emplazamiento (desglosado) and the one-line address composed from it
  streetType: '', streetName: '', portal: '', bis: '', stair: '', floor: '', door: '',
  postalCode: '', locality: '', population: '', province: '', address: '',
  // C · Características
  installedPower: '', voltage: '', specificRisks: [],
  // E/F · Uso
  useType: 'vivienda', secondaryUses: [], useSpecifications: '',
  // D · Empresa instaladora
  companyName: '', companyNif: '', companyRegNumber: '', installerName: '', installerNif: '', installerLicense: '',
  // G · Certificación
  designBasis: 'mtd', designDate: '', earthResistance: '', insulationResistance: '',
  // Datos para la MTD (no van en el certificado)
  maxPower: '', supplyType: '', earthingScheme: 'TT', circuits: '', derivationSection: '', igaCurrent: '',
  differentialSensitivity: '', earthConductorSection: '',
  observations: '',
}

/** The guided steps, in the order of the CERTINS E, with the fields each one fills. */
export const STEPS = [
  { id: 'start', fields: [] },
  { id: 'client', section: 'A · B', fields: ['titularName', 'titularNif', 'supplySource', 'distributor', 'cups', 'titularAddress'] },
  { id: 'location', section: 'C', fields: ['streetType', 'streetName', 'portal', 'bis', 'stair', 'floor', 'door', 'postalCode', 'locality', 'population', 'province'] },
  { id: 'installation', section: 'C · E · F', fields: ['installationType', 'installedPower', 'voltage', 'specificRisks', 'useType', 'secondaryUses', 'useSpecifications'] },
  { id: 'company', section: 'D', fields: ['companyName', 'companyNif', 'companyRegNumber', 'installerName', 'installerNif', 'installerLicense'] },
  { id: 'certification', section: 'G', fields: ['designBasis', 'designDate', 'earthResistance', 'insulationResistance'] },
  { id: 'mtd', section: 'MTD', fields: ['maxPower', 'supplyType', 'earthingScheme', 'circuits', 'derivationSection', 'igaCurrent', 'differentialSensitivity', 'earthConductorSection'] },
  { id: 'review', fields: ['observations'] },
]

/** Fields that live in the form but are not asked in any step (internal or composed). */
export const NOT_IN_STEPS = ['id', 'issuedAt', 'address']

/** Fields entered as a list of choices. */
export const MULTI = ['specificRisks', 'secondaryUses']

const BASE_REQUIRED = [
  'titularName', 'titularNif', 'streetName', 'locality', 'province', 'postalCode', 'installedPower', 'voltage', 'specificRisks',
  'useType', 'companyName', 'companyNif', 'companyRegNumber', 'installerName', 'installerNif',
  'designDate', 'earthResistance', 'insulationResistance',
]

/**
 * Required fields of the CERTINS E. With the distribution network as the source, distributor and CUPS too (GVA
 * low-voltage FAQ 26). Mirrors CertinsSheet and CieAssistantController on the backend.
 */
export const requiredFor = (form) => (form?.supplySource === 'generacion_aislada' ? BASE_REQUIRED : [...BASE_REQUIRED, 'distributor', 'cups'])

/** Fields the backend refuses to save without (a draft needs only these). */
export const SAVE_REQUIRED = ['titularName', 'companyName']

export const filled = (v) => (Array.isArray(v) ? v.length > 0 : String(v ?? '').trim() !== '')

export const missingRequired = (form) => requiredFor(form).filter((k) => !filled(form[k]))

/** Which required fields of one step are still empty. */
export const missingInStep = (form, stepId) => {
  const req = requiredFor(form)
  return (STEPS.find((s) => s.id === stepId)?.fields ?? []).filter((k) => req.includes(k) && !filled(form[k]))
}

/** Can be saved as a draft: titular, company and some address. */
export const canSave = (form) => SAVE_REQUIRED.every((k) => filled(form[k])) && (filled(form.streetName) || filled(form.address))

/** "Calle del Mar 3, esc. A, 2º B" — the same composition as Certificate::composedAddress(). */
export function composeAddress(form) {
  const street = [form.streetType, form.streetName, form.portal, form.bis].map((v) => String(v ?? '').trim()).filter(Boolean).join(' ')
  const floorDoor = [form.floor, form.door].map((v) => String(v ?? '').trim()).filter(Boolean).join(' ')
  return [street, form.stair ? `esc. ${String(form.stair).trim()}` : '', floorDoor].filter(Boolean).join(', ')
}

/** A self-employed electrician is their own installation company and installer: defaults from "Mis datos". */
export const profileDefaults = (profile) => ({
  companyName: profile?.businessName ?? '',
  companyNif: profile?.taxId ?? '',
  companyRegNumber: profile?.installerRegNumber ?? '',
  installerName: profile?.businessName ?? '',
  installerNif: profile?.taxId ?? '',
})

/** Fill only what is empty: what the electrician already typed always wins over what Claude read. */
export function mergeEmpty(form, values) {
  const next = { ...form }
  const changed = []
  for (const [k, v] of Object.entries(values ?? {})) {
    if (k in BLANK_CIE && filled(v) && !filled(form[k])) {
      next[k] = Array.isArray(v) ? [...v] : String(v)
      changed.push(k)
    }
  }
  return { form: next, changed }
}

/**
 * Merge a bill read by Claude ({fields, checks, contractedPowerKw}).
 * Returns the new form, the fields Claude filled (to mark ✨ until reviewed), those that failed a rule check,
 * and the contracted power — offered, never written by itself (it is neither the installed nor the maximum power).
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

/** Turn an existing certificate from the API into the form (nulls → '', lists kept). */
export function formFromCertificate(c) {
  const form = { ...BLANK_CIE }
  for (const k of Object.keys(BLANK_CIE)) {
    const v = c?.[k]
    if (v === null || v === undefined) continue
    form[k] = Array.isArray(BLANK_CIE[k]) ? (Array.isArray(v) ? [...v] : []) : String(v)
  }
  form.id = c?.id ?? null
  return form
}

/** The body to POST/PUT: the one-line address composed from the parts when there are parts. */
export function payload(form) {
  const { id: _id, ...body } = form
  const composed = composeAddress(form)
  return { ...body, address: composed || form.address }
}
