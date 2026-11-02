import { describe, it, expect } from 'vitest'
import { BLANK_CIE, STEPS, REQUIRED, missingRequired, missingInStep, profileDefaults, mergeEmpty, applyBill } from './cie'

describe('steps', () => {
  it('every field of the form belongs to exactly one step (none forgotten, none twice)', () => {
    const inSteps = STEPS.flatMap((s) => s.fields)
    const formFields = Object.keys(BLANK_CIE).filter((k) => !['id', 'issuedAt'].includes(k))
    expect([...inSteps].sort()).toEqual([...formFields].sort())
    expect(new Set(inSteps).size).toBe(inSteps.length)
  })

  it('every required field is asked in some step', () => {
    for (const k of REQUIRED) expect(STEPS.some((s) => s.fields.includes(k))).toBe(true)
  })

  it('reports what is missing, overall and per step', () => {
    const form = { ...BLANK_CIE, address: 'C/ Mar 3', titularName: 'María', supplyType: 'monofasico' }
    expect(missingRequired(form)).toEqual(['companyName', 'companyRegNumber', 'maxPower', 'igaCurrent', 'differentialSensitivity'])
    expect(missingInStep(form, 'client')).toEqual([])
    expect(missingInStep(form, 'protections')).toEqual(['igaCurrent', 'differentialSensitivity'])
  })
})

describe('defaults and merging', () => {
  it('the installation company comes from "Mis datos"', () => {
    expect(profileDefaults({ businessName: 'José Pérez', taxId: '12345678Z', installerRegNumber: 'CV-1' }))
      .toEqual({ companyName: 'José Pérez', companyNif: '12345678Z', companyRegNumber: 'CV-1', installerName: 'José Pérez' })
  })

  it('what the electrician typed always wins over what Claude read', () => {
    const { form, changed } = mergeEmpty({ ...BLANK_CIE, titularName: 'Tecleado' }, { titularName: 'Leído', locality: 'Castelló', unknown: 'x', province: ' ' })
    expect(form.titularName).toBe('Tecleado')
    expect(form.locality).toBe('Castelló')
    expect(form.unknown).toBeUndefined()
    expect(changed).toEqual(['locality'])
  })

  it('a bill fills the empty fields, marks them and never touches the maximum power', () => {
    const r = applyBill({ ...BLANK_CIE }, {
      fields: { titularName: 'María', cups: 'ES0021000012345679LB' },
      checks: ['cups', 'titularNif'],
      contractedPowerKw: '5.750',
    })
    expect(r.form.titularName).toBe('María')
    expect(r.aiFields).toEqual(['titularName', 'cups'])
    expect(r.checks).toEqual(['cups'], 'only checks on fields that were actually filled')
    expect(r.contractedPowerKw).toBe('5.750')
    expect(r.form.maxPower).toBe('')
  })
})
