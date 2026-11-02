import { describe, it, expect } from 'vitest'
import {
  BLANK_CIE, STEPS, NOT_IN_STEPS, MULTI, requiredFor, missingRequired, missingInStep, canSave, composeAddress,
  profileDefaults, mergeEmpty, applyBill, formFromCertificate, payload, filled, decimalForForm,
} from './cie'

const complete = {
  ...BLANK_CIE,
  titularName: 'María', titularNif: '12345678Z', distributor: 'i-DE', cups: 'ES0021000012345678LB',
  streetType: 'Calle', streetName: 'del Mar', portal: '3', postalCode: '12001', locality: 'Castelló', province: 'Castellón',
  installedPower: '5.75', voltage: '230', specificRisks: ['ninguno'], useType: 'vivienda',
  companyName: 'José', companyNif: '11111111H', companyRegNumber: 'CV-1', installerName: 'José', installerNif: '11111111H',
  designDate: '2026-10-30', earthResistance: '18', insulationResistance: '250',
}

describe('steps follow the CERTINS E', () => {
  it('every field of the form is asked in exactly one step (except the internal ones)', () => {
    const inSteps = STEPS.flatMap((s) => s.fields)
    const formFields = Object.keys(BLANK_CIE).filter((k) => !NOT_IN_STEPS.includes(k))
    expect([...inSteps].sort()).toEqual([...formFields].sort())
    expect(new Set(inSteps).size).toBe(inSteps.length)
  })

  it('every required field is asked in some step', () => {
    for (const k of requiredFor({ supplySource: 'red' })) expect(STEPS.some((s) => s.fields.includes(k))).toBe(true)
  })

  it('the steps go through the sections in the model order', () => {
    expect(STEPS.filter((s) => s.section).map((s) => s.section)).toEqual(['A · B', 'C', 'C · E · F', 'D', 'G', 'MTD'])
  })

  it('multi-choice fields start as empty lists', () => {
    for (const k of MULTI) expect(BLANK_CIE[k]).toEqual([])
  })
})

describe('what is required', () => {
  it('a complete certificate misses nothing', () => {
    expect(missingRequired(complete)).toEqual([])
  })

  it('CUPS and distributor are required only with the distribution network', () => {
    const noGrid = { ...complete, supplySource: 'generacion_aislada', cups: '', distributor: '' }
    expect(missingRequired(noGrid)).toEqual([])
    expect(missingRequired({ ...noGrid, supplySource: 'red' })).toEqual(['distributor', 'cups'])
  })

  it('an empty list counts as missing', () => {
    expect(filled([])).toBe(false)
    expect(missingInStep({ ...complete, specificRisks: [] }, 'installation')).toEqual(['specificRisks'])
  })

  it('a draft can be saved with titular, company and some address', () => {
    expect(canSave({ ...BLANK_CIE, titularName: 'M', companyName: 'J', streetName: 'Mar' })).toBe(true)
    expect(canSave({ ...BLANK_CIE, titularName: 'M', companyName: 'J' })).toBe(false)
  })
})

describe('address, defaults and merging', () => {
  it('composes the one-line address like the backend', () => {
    expect(composeAddress(complete)).toBe('Calle del Mar 3')
    expect(composeAddress({ streetType: 'Avenida', streetName: 'Sol', portal: '9', stair: 'A', floor: '1º', door: 'B' })).toBe('Avenida Sol 9, esc. A, 1º B')
    expect(payload({ ...complete, id: 7 }).address).toBe('Calle del Mar 3')
    expect(payload({ ...complete, id: 7 }).id).toBeUndefined()
  })

  it('the installation company and installer come from "Mis datos"', () => {
    expect(profileDefaults({ businessName: 'José Pérez', taxId: '11111111H', installerRegNumber: 'CV-1' }))
      .toEqual({ companyName: 'José Pérez', companyNif: '11111111H', companyRegNumber: 'CV-1', installerName: 'José Pérez', installerNif: '11111111H' })
  })

  it('what the electrician typed always wins over what Claude read', () => {
    const { form, changed } = mergeEmpty({ ...BLANK_CIE, titularName: 'Tecleado' }, { titularName: 'Leído', locality: 'Castelló', unknown: 'x', province: ' ' })
    expect(form.titularName).toBe('Tecleado')
    expect(form.locality).toBe('Castelló')
    expect(form.unknown).toBeUndefined()
    expect(changed).toEqual(['locality'])
  })

  it('a bill fills the empty fields, marks them and never writes a power', () => {
    const r = applyBill({ ...BLANK_CIE }, {
      fields: { titularName: 'María', cups: 'ES0021000012345679LB', streetName: 'del Mar', distributor: 'i-DE' },
      checks: ['cups', 'titularNif'], contractedPowerKw: '5.750',
    })
    expect(r.aiFields).toEqual(['titularName', 'cups', 'streetName', 'distributor'])
    expect(r.checks).toEqual(['cups'])
    expect(r.contractedPowerKw).toBe('5.750')
    expect(r.form.installedPower).toBe('')
    expect(r.form.maxPower).toBe('')
  })

  it('loads an existing certificate: nulls become empty, lists stay lists', () => {
    const f = formFromCertificate({ id: 3, titularName: 'María', voltage: 230, cups: null, specificRisks: ['humedo'], secondaryUses: null })
    expect(f.id).toBe(3)
    expect(f.voltage).toBe('230')
    expect(f.cups).toBe('')
    expect(f.specificRisks).toEqual(['humedo'])
    expect(f.secondaryUses).toEqual([])
  })

  it('a company\'s CIF is never offered as the installer\'s NIF', () => {
    expect(profileDefaults({ businessName: 'Instalaciones Mar SL', taxId: 'B12345674' }))
      .toMatchObject({ companyNif: 'B12345674', installerName: '', installerNif: '' })
    expect(profileDefaults({ businessName: 'Ana', taxId: 'x1234567l' }).installerNif).toBe('x1234567l')
  })

  it('decimals come back with a comma, as they are written in Spain', () => {
    expect(decimalForForm('5.750')).toBe('5,75')
    expect(decimalForForm('1500.500')).toBe('1500,5')
    expect(decimalForForm('6.000')).toBe('6')
    expect(formFromCertificate({ installedPower: '5.750', maxPower: null }).installedPower).toBe('5,75')
  })

  it('an old certificate with only the one-line address is not missing its street', () => {
    const f = formFromCertificate({ id: 1, address: 'C/ Mayor 3' })
    expect(f.streetName).toBe('C/ Mayor 3')
    expect(payload(f).address).toBe('C/ Mayor 3')
    expect(missingRequired({ ...BLANK_CIE, address: 'C/ Mayor 3' })).not.toContain('streetName')
  })

  it('the required fields come from the backend model when it is loaded', () => {
    const model = { required: ['titularName'], gridRequired: ['cups'] }
    expect(requiredFor({ supplySource: 'red' }, model)).toEqual(['titularName', 'cups'])
    expect(requiredFor({ supplySource: 'generacion_aislada' }, model)).toEqual(['titularName'])
  })

  it('an isolated installation sends no distributor nor CUPS', () => {
    const body = payload({ ...BLANK_CIE, supplySource: 'generacion_aislada', distributor: 'i-DE', cups: 'ES0021000012345678LB' })
    expect(body.distributor).toBe('')
    expect(body.cups).toBe('')
  })
})
