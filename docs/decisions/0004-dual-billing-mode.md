# ADR 0004 — Dual billing mode (standard vs Verifactu demo)

- **Status:** Accepted · **Date:** 2026-07-11 · **Supersedes part of:** [ADR 0003](0003-verifactu-invoicing.md)

**Languages:** [English](#english) · [Español](#español)

---

## English

### Context
[ADR 0003](0003-verifactu-invoicing.md) built a Verifactu-ready engine: every invoice gets a chained
SHA-256 record, and the PDF printed the AEAT **QR** + a "Verifactu" legend whenever a record existed. Since
`InvoiceService::create()` **always** creates a record, **every** invoice — including one a real pilot user
issues for a real client — carried a QR pointing at the AEAT **pre-production (test)** host and a
"demonstration" legend. That is fine for a portfolio demo but wrong for real use.

Two facts make a dual mode the right call:
- **The Verifactu obligation does not apply yet.** RD 1007/2023 was delayed twice: RD 254/2025 (BOE
  2025-04-02) and **RD-ley 15/2025 (BOE 2025-12-03)**. Autónomos (IRPF with economic activity) are obliged
  from **1 July 2027**; sociedades from 1 January 2027. During 2026 Verifactu is **optional**.
- **An ordinary invoice is valid today.** A standard invoice under **RD 1619/2012** (Reglamento de
  facturación) needs no QR, no XML and no "verifiable invoice" legend.

### Decision
A per-user `billingMode`:
- **`standard`** (default): ordinary RD 1619/2012 invoice — issuer fiscal profile, customer, lines, VAT
  breakdown, totals. **No QR, no XML, no Verifactu legend.** The `/qr` and `/xml` endpoints return **403**.
- **`verifactu`** (demo): the full ADR 0003 behaviour, marked in the UI and on the PDF as
  **«DEMOSTRACIÓN — sin validez fiscal»**.

**The internal hash chain runs in BOTH modes.** `InvoiceService::create()` is unchanged: it always writes an
`InvoiceRecord`, giving tamper-evidence and gapless numbering for free. The chain is simply **not surfaced**
in standard mode. Crucially, `VerifactuHasher::fingerprint()` does **not** include `billingMode`, so changing
mode never alters a fingerprint and `GET /api/invoices/verify` stays green over pre-existing invoices (a
regression test asserts exactly this).

A standard invoice also needs the issuer's fiscal identity, so `User` gains `businessName` + `fiscalAddress`
(NIF already existed).

### Consequences
- Real invoices are legally shippable **now**; the Verifactu demo is one toggle away and clearly labelled.
- The integrity engine is not wasted — it protects numbering and detects tampering in both modes.
- Out of scope (unchanged from ADR 0003): certified real-time submission to the AEAT web service (Phase D).
- When the 2027 obligation nears, flipping the default (or adding a real submission path) is a small change.

---

## Español

### Contexto
El [ADR 0003](0003-verifactu-invoicing.md) construyó un motor Verifactu-ready: cada factura genera un
registro SHA-256 encadenado, y el PDF imprimía el **QR** de la AEAT + una leyenda «Verifactu» siempre que
existía registro. Como `InvoiceService::create()` crea **siempre** un registro, **toda** factura —incluida
una que el piloto emita a un cliente real— salía con un QR que apunta al host de **pruebas** de la AEAT y una
leyenda de «demostración». Vale para una demo de portfolio, pero está mal para uso real.

Dos hechos hacen del doble modo la decisión correcta:
- **La obligación Verifactu aún no aplica.** El RD 1007/2023 se aplazó dos veces: RD 254/2025 (BOE
  2025-04-02) y **RD-ley 15/2025 (BOE 2025-12-03)**. Los autónomos (IRPF con actividad económica) quedan
  obligados desde el **1 de julio de 2027**; las sociedades desde el 1 de enero de 2027. Durante 2026 es
  **opcional**.
- **Una factura ordinaria es válida hoy.** Una factura estándar según el **RD 1619/2012** (Reglamento de
  facturación) no necesita QR, ni XML, ni leyenda de «factura verificable».

### Decisión
Un `billingMode` por usuario:
- **`standard`** (por defecto): factura ordinaria RD 1619/2012 — perfil fiscal del emisor, cliente, líneas,
  desglose de IVA, totales. **Sin QR, sin XML, sin leyenda Verifactu.** Los endpoints `/qr` y `/xml`
  devuelven **403**.
- **`verifactu`** (demo): el comportamiento completo del ADR 0003, marcado en la UI y en el PDF como
  **«DEMOSTRACIÓN — sin validez fiscal»**.

**La cadena de hash interna vive en AMBOS modos.** `InvoiceService::create()` no cambia: siempre escribe un
`InvoiceRecord`, dando antimanipulación y numeración sin huecos gratis. Simplemente **no se muestra** en modo
estándar. Clave: `VerifactuHasher::fingerprint()` **no** incluye `billingMode`, así que cambiar de modo nunca
altera una huella y `GET /api/invoices/verify` sigue en verde sobre facturas preexistentes (hay un test de
regresión que lo comprueba).

Una factura estándar necesita además la identidad fiscal del emisor, así que `User` gana `businessName` +
`fiscalAddress` (el NIF ya existía).

### Consecuencias
- Las facturas reales ya son emitibles legalmente **ahora**; la demo Verifactu está a un clic y claramente
  etiquetada.
- El motor de integridad no se desperdicia: protege la numeración y detecta manipulación en ambos modos.
- Fuera de alcance (igual que en el ADR 0003): el envío certificado en tiempo real al servicio web de la
  AEAT (Fase D).
- Cuando se acerque la obligación de 2027, cambiar el default (o añadir el envío real) es un cambio pequeño.
