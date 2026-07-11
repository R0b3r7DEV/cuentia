# 38 — Dual billing mode: standard invoices vs Verifactu demo · Modo dual de facturación

Goal / Objetivo: let a real freelancer issue **ordinary, legally-valid invoices** by default (no QR/XML),
while keeping the Verifactu artefacts available as a clearly-labelled demo — and keep the tamper-evident
hash chain running underneath in both cases.

*Objetivo: que un autónomo real emita **facturas ordinarias y válidas** por defecto (sin QR/XML), con los
artefactos Verifactu disponibles como demo claramente etiquetada — y mantener la cadena de hash viva en
ambos casos.*

See [ADR 0004](../decisions/0004-dual-billing-mode.md) for the why. This guide is the how.

---

## The two modes / Los dos modos

| | Standard (default) | Verifactu (demo) |
|---|---|---|
| Invoice PDF | RD 1619/2012 fields only | + QR + "DEMOSTRACIÓN" legend |
| `/api/invoices/{id}/qr` · `/xml` | **403** | 200 |
| Chain / cadena (`/verify`) | runs, hidden | runs, shown |
| Legal today / válida hoy | ✅ | ❌ (demo) |

The mode is a per-user field, `User.billingMode`, defaulting to `'standard'`.

## Why the chain runs in both modes / Por qué la cadena vive en ambos

`InvoiceService::create()` is unchanged — it **always** writes an `InvoiceRecord`, so numbering stays gapless
and any tampering is detectable, for free. Standard mode simply doesn't *surface* the QR/XML/fingerprint.

The one invariant that makes this safe: **`VerifactuHasher::fingerprint()` does not include `billingMode`.**
A fingerprint depends only on `issuerNif, fullNumber, issueDate, vatTotal, total, generatedAt, previousHash`.
So flipping the mode never rewrites a hash, and a chain built before the switch still verifies after it.

*La única invariante que lo hace seguro: la huella no incluye el modo. Cambiar de modo nunca reescribe un
hash, así que una cadena creada antes del cambio sigue verificándose después.* Test:
`testChangingBillingModeDoesNotBreakTheChainOverPreexistingInvoices`.

## Issuer fiscal profile / Perfil fiscal del emisor

A standard invoice must name the issuer (RD 1619/2012 art. 6). `User` gained `businessName` +
`fiscalAddress` (NIF already existed); the invoice PDF prints all three. Set them in **Cuenta →
Facturación**.

## What the pilot sees / Qué ve el piloto

- **Cuenta → Facturación**: a plain-language selector (*Estándar (recomendado)* / *Verifactu (DEMO)*) and the
  issuer profile form.
- **Facturación → Facturas**: in standard mode there is no chain widget and no QR/XML — just the invoice and
  a **Descargar PDF**. In demo mode a warning banner appears and the fingerprint/QR/XML come back.

## The obligation date / La fecha de obligación

Verifactu is **optional during 2026**. Autónomos are obliged from **1 July 2027** (RD 1007/2023, delayed by
RD 254/2025 and **RD-ley 15/2025**, BOE 2025-12-03); sociedades from 1 January 2027. When that nears, moving
the default or adding certified submission (Phase D of ADR 0003) is a small change.
