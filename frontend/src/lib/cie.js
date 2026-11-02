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

// Only until GET /api/certificates/model answers: the list itself lives in the backend (CertinsModel::REQUIRED).
const FALLBACK_REQUIRED = [
  'titularName', 'titularNif', 'supplySource', 'streetName', 'province', 'locality', 'postalCode', 'installedPower',
  'voltage', 'installationType', 'specificRisks', 'companyName', 'companyNif', 'companyRegNumber', 'installerName',
  'installerNif', 'useType', 'designBasis', 'designDate', 'earthResistance', 'insulationResistance',
]
const FALLBACK_GRID = ['distributor', 'cups']

/**
 * Required fields of the CERTINS E, as the backend's model sends them. With the distribution network as the
 * source, distributor and CUPS too (GVA low-voltage FAQ 26).
 */
export const requiredFor = (form, model) => {
  const base = model?.required ?? FALLBACK_REQUIRED
  return form?.supplySource === 'generacion_aislada' ? base : [...base, ...(model?.gridRequired ?? FALLBACK_GRID)]
}

/** Fields the backend refuses to save without (a draft needs only these). */
export const SAVE_REQUIRED = ['titularName', 'companyName']

export const filled = (v) => (Array.isArray(v) ? v.length > 0 : String(v ?? '').trim() !== '')

// A certificate from before the CERTINS fields only has the one-line address: it counts as the street name.
const isEmpty = (form, k) => !filled(form[k]) && !(k === 'streetName' && filled(form.address))

export const missingRequired = (form, model) => requiredFor(form, model).filter((k) => isEmpty(form, k))

/** Which required fields of one step are still empty. */
export const missingInStep = (form, stepId, model) => {
  const req = requiredFor(form, model)
  return (STEPS.find((s) => s.id === stepId)?.fields ?? []).filter((k) => req.includes(k) && isEmpty(form, k))
}

/** Can be saved as a draft: titular, company and some address. */
export const canSave = (form) => SAVE_REQUIRED.every((k) => filled(form[k])) && (filled(form.streetName) || filled(form.address))

/** "Calle del Mar 3, esc. A, 2º B" — the same composition as Certificate::composedAddress(). */
export function composeAddress(form) {
  const street = [form.streetType, form.streetName, form.portal, form.bis].map((v) => String(v ?? '').trim()).filter(Boolean).join(' ')
  const floorDoor = [form.floor, form.door].map((v) => String(v ?? '').trim()).filter(Boolean).join(' ')
  return [street, form.stair ? `esc. ${String(form.stair).trim()}` : '', floorDoor].filter(Boolean).join(', ')
}

/** A person's NIF (DNI or NIE), not a company's CIF — format only, as Nif::isPerson() on the backend. */
export const isPersonNif = (nif) => /^[XYZ]?\d{7,8}[A-Z]$/.test(String(nif ?? '').toUpperCase().replace(/[^A-Z0-9]/g, ''))

/**
 * A self-employed electrician is their own installation company and installer: defaults from "Mis datos".
 * The installer (holder of the CCI) is a person: a company's CIF is never offered as their NIF.
 */
export const profileDefaults = (profile) => {
  const person = isPersonNif(profile?.taxId)
  return {
    companyName: profile?.businessName ?? '',
    companyNif: profile?.taxId ?? '',
    companyRegNumber: profile?.installerRegNumber ?? '',
    installerName: person ? profile?.businessName ?? '' : '',
    installerNif: person ? profile?.taxId ?? '' : '',
  }
}

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

const DECIMALS = ['installedPower', 'maxPower']
const ADDRESS_PARTS = ['streetType', 'streetName', 'portal', 'bis', 'stair', 'floor', 'door']

/** "5.750" (as the API stores it) → "5,75" (as it is written in Spain, and sent back). */
export const decimalForForm = (v) => {
  const s = String(v ?? '')
  return /^-?\d+\.\d+$/.test(s) ? s.replace(/0+$/, '').replace(/\.$/, '').replace('.', ',') : s
}

/**
 * Turn an existing certificate from the API into the form (nulls → '', lists kept, decimals with a comma).
 * A certificate from before the CERTINS fields gets its one-line address as the street name, to split by hand.
 */
export function formFromCertificate(c) {
  const form = { ...BLANK_CIE }
  for (const k of Object.keys(BLANK_CIE)) {
    const v = c?.[k]
    if (v === null || v === undefined) continue
    form[k] = Array.isArray(BLANK_CIE[k]) ? (Array.isArray(v) ? [...v] : []) : DECIMALS.includes(k) ? decimalForForm(v) : String(v)
  }
  if (ADDRESS_PARTS.every((k) => !filled(form[k])) && filled(form.address)) form.streetName = form.address
  form.id = c?.id ?? null
  return form
}

/** The body to POST/PUT: the one-line address composed from the parts when there are parts. */
export function payload(form) {
  const { id: _id, ...body } = form
  const composed = composeAddress(form)
  // An isolated generating installation has no distributor nor CUPS (the backend drops them too).
  const grid = form.supplySource === 'generacion_aislada' ? { distributor: '', cups: '' } : {}
  return { ...body, ...grid, address: composed || form.address }
}
