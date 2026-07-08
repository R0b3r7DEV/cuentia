# 31 — Quotes · Presupuestos

Goal / Objetivo: issue **quotes (presupuestos)** — non-fiscal offers — and, when a customer accepts,
**convert a quote into a real Verifactu invoice** with one click.

*Objetivo: emitir **presupuestos** —ofertas no fiscales— y, cuando el cliente acepta, **convertir un
presupuesto en una factura Verifactu real** con un clic.*

---

## Why a separate entity / Por qué una entidad aparte

- **EN:** A quote is *not* an invoice: it has no fiscal value, no Verifactu hash chain, and it can be
  revised, expire or be rejected. Modelling it as its own `Quote` (+ `QuoteLine`) keeps the legally-strict
  invoice chain clean. A quote carries a `status` (`draft` → `sent` → `accepted` / `rejected`) and, once
  converted, `converted` plus a link to the resulting invoice.
- **ES:** Un presupuesto *no* es una factura: no tiene valor fiscal ni cadena de hash Verifactu, y puede
  revisarse, caducar o rechazarse. Modelarlo como su propio `Quote` mantiene limpia la cadena legal de
  facturas.

## Endpoints

```
GET    /api/quotes              → list
POST   /api/quotes              → create  { customerId | customer, validUntil?, lines[] }  (status: draft)
GET    /api/quotes/{id}         → detail (lines, status, convertedInvoice)
POST   /api/quotes/{id}/status  → set status  { status: sent|accepted|rejected }
POST   /api/quotes/{id}/convert → create a Verifactu invoice from the quote (201)
GET    /api/quotes/{id}/pdf     → PDF (no QR/fingerprint — non-fiscal)
```

Totals use the same **integer-cent** maths as invoices; quote numbers are correlative per series
(default `P<year>`).

## Convert → invoice / Convertir → factura

- **EN:** `QuoteService::convert()` copies the quote's lines and customer into `InvoiceService::create()`,
  so the new invoice is a **fully sealed Verifactu invoice** (hash chain, QR, XML, PDF — everything). It is
  **idempotent**: a quote converts once; calling convert again returns the same invoice and never
  duplicates. The quote is then marked `converted` and linked to the invoice.
- **ES:** `QuoteService::convert()` copia las líneas y el cliente del presupuesto a
  `InvoiceService::create()`, así la nueva factura es una **factura Verifactu completamente sellada**. Es
  **idempotente**: un presupuesto se convierte una vez; volver a convertir devuelve la misma factura.

## Frontend

A **Presupuestos** tab: create (customer picker + catalog lines + *valid until*), list with a colored
**status** pill, and per-quote actions — change status, **Convert to invoice**, and **Download PDF**. Once
converted, the row shows a link to the invoice instead of the actions.

## Verify / Verificar

```powershell
php bin/phpunit --filter 'QuoteService|Quotes'
#  totals/numbering, default series P<year>, draft status, empty-lines guard
#  integration: create → status → convert → invoice sealed → idempotent → PDF
php bin/phpunit
#  OK (44 tests, 179 assertions)
```

---

**Next / Siguiente:** agentic AI + OCR (need an Anthropic API key). / IA agéntica + OCR (necesitan API key
de Anthropic).
