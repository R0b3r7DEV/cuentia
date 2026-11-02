import { describe, it, expect } from 'vitest'
import { normalizeIban, isValidIban, formatIban, missingFor } from './profile'

describe('IBAN', () => {
  it('accepts a valid IBAN however it is typed', () => {
    expect(isValidIban('ES91 2100 0418 4502 0005 1332')).toBe(true)
    expect(isValidIban('es91-2100-0418-4502-0005-1332')).toBe(true)
    expect(isValidIban('DE89 3704 0044 0532 0130 00')).toBe(true)
  })

  it('catches a wrong digit, swapped digits and a missing digit', () => {
    expect(isValidIban('ES91 2100 0418 4502 0005 1333')).toBe(false)
    expect(isValidIban('ES91 2100 0418 4502 0005 3132')).toBe(false)
    expect(isValidIban('ES91 2100 0418 4502 0005 133')).toBe(false)
    expect(isValidIban('')).toBe(false)
  })

  it('normalizes and groups by four', () => {
    expect(normalizeIban(' es91 2100-0418 ')).toBe('ES9121000418')
    expect(formatIban('ES9121000418450200051332')).toBe('ES91 2100 0418 4502 0005 1332')
  })
})

describe('what is missing', () => {
  it('lists the empty required fields per document', () => {
    const p = { businessName: 'José Pérez', taxId: ' ', fiscalAddress: 'C/ Major 1', installerRegNumber: '' }
    expect(missingFor(p, 'invoice')).toEqual(['taxId'])
    expect(missingFor(p, 'certificate')).toEqual(['taxId', 'installerRegNumber'])
    expect(missingFor({}, 'invoice')).toEqual(['businessName', 'taxId', 'fiscalAddress'])
  })
})
