# 24 — Verifactu invoicing · Facturación Verifactu (Fase A)

Goal / Objetivo: build the invoicing domain model — customers, invoices with lines, exact totals in
cents and correlative numbering per series — the foundation for the Verifactu hash chain (next phase).

*Objetivo: construir el modelo de dominio de facturación — clientes, facturas con líneas, totales
exactos en céntimos y numeración correlativa por serie — la base para la cadena de hash Verifactu
(siguiente fase).*

---

## Why Verifactu / Por qué Verifactu

- **EN:** *Verifactu* is Spain's upcoming anti-fraud invoicing regulation (Reglamento VeriFactu, Orden
  HAC/1177/2024). Software must issue invoices as **chained, tamper-evident records** (each carries a
  SHA-256 hash of the previous one) and expose a QR to the AEAT. Getting ahead of the 2026 obligation
  makes Cuentia a credible showcase. This phase (**A**) builds the domain model; the hash chain, QR and
  XML come in phases B–C. See [ADR 0003](../decisions/0003-verifactu-invoicing.md) for the full scope
  and the honest "this is a portfolio, not revenue" note.
- **ES:** *Verifactu* es la futura normativa antifraude de facturación en España (Reglamento VeriFactu,
  Orden HAC/1177/2024). El software debe emitir facturas como **registros encadenados e inalterables**
  (cada uno lleva un hash SHA-256 del anterior) y exponer un QR a la AEAT. Adelantarse a la obligación de
  2026 hace de Cuentia un escaparate creíble. Esta fase (**A**) construye el modelo de dominio; la cadena
  de hash, el QR y el XML llegan en las fases B–C. Ver [ADR 0003](../decisions/0003-verifactu-invoicing.md).

## The domain model / El modelo de dominio

Three entities, all scoped to a `User` (multi-tenant isolation, same rule as `Transaction`):

| Entity | Fields | Notes |
|---|---|---|
| `Customer` | name, taxId, address?, email? | resolved **get-or-create** by `taxId` per user |
| `Invoice` | series, number, issuedAt, baseTotal, vatTotal, total, status | `getFullNumber()` = `series/number` |
| `InvoiceLine` | description, quantity, unitPrice, vatRate | `baseCents()` / `vatCents()` |

- **EN:** Money is **never a float**. Line totals are computed in **integer cents**
  (`baseCents = round(unitPrice×100) × quantity`, `vatCents = round(baseCents × vatRate / 100)`) and only
  formatted back to a `decimal(12,2)` string at the end. Rounding per line, then summing, matches how the
  AEAT expects VAT to be totalled.
- **ES:** El dinero **nunca es un float**. Los totales de línea se calculan en **céntimos enteros** y solo
  se formatean a `decimal(12,2)` al final. Redondear por línea y luego sumar es como la AEAT espera que se
  totalice el IVA.

## Correlative numbering / Numeración correlativa

`InvoiceRepository::nextNumber(user, series)` returns `MAX(number) + 1` for that user+series (starts at 1).
Invoice numbers must be **gapless and sequential per series** — a legal requirement, and a prerequisite for
the hash chain, where each record references the previous by number.

*Los números de factura deben ser **correlativos y sin huecos por serie** — requisito legal y previo a la
cadena de hash, donde cada registro referencia al anterior por número.*

## Endpoints

```
GET  /api/invoices        → list (newest first)
POST /api/invoices        → create & issue (201)  { series?, customer:{name,taxId}, lines:[{description,quantity,unitPrice,vatRate}] }
GET  /api/invoices/{id}   → detail (404 if not owned)
```

All require `ROLE_USER` and are scoped to `#[CurrentUser]` — a user can never read another's invoices.

## Verify / Verificar

```powershell
# unit test: totals, VAT and numbering (no DB)
php bin/phpunit --filter InvoiceServiceTest
#  base 200.00 · VAT 31.00 · total 231.00 · number TEST/5

# full suite
php bin/phpunit
#  OK (18 tests, 59 assertions)
```

Live check (register → login → create) returns `base 1100.00 · VAT 220.00 · total 1320.00 · number 2026/1`
for one line at 21 % + two at 10 %.

---

**Next / Siguiente:** Phase B — the `InvoiceRecord` with the **chained SHA-256 hash** and tamper-detection
tests. / Fase B — el `InvoiceRecord` con el **hash SHA-256 encadenado** y tests de detección de manipulación.
