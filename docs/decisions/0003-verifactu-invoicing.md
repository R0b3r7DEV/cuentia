# ADR 0003 — Verifactu-ready invoicing

- **Status:** Accepted · **Date:** 2026-07-06

**Languages:** [English](#english) · [Español](#español)

---

## English

### Context
Spain's **Verifactu** regulation (RD 1007/2023 + Orden HAC/1177/2024) requires invoicing software to
produce, for every invoice, a **billing record** whose integrity is guaranteed by a **chained SHA-256
hash** (each record includes the previous record's hash → a tamper-evident chain), a **QR code** pointing
to the AEAT, and — in *Verifactu* mode — real-time submission of the records to the AEAT web service.

Adding this to Cuentia turns it from a "visibility" tool into something that touches a real, regulated
workflow, and showcases non-trivial engineering (tamper-evident hashing, a defined spec, fiscal domain).

> Honest note on monetization: the AEAT ships a **free** Verifactu invoicing app, so this is built as a
> **portfolio / learning showcase**, not a revenue play. See the README's "honest product take".

### Decision
Build a **Verifactu-ready invoicing engine** in phases:

- **Phase A — Invoicing:** `Customer`, `Invoice`, `InvoiceLine` entities (owned by a `User`), correlative
  numbering per series, VAT breakdown and totals.
- **Phase B — Verifactu record:** an `InvoiceRecord` (alta/anulación) with the fields from art. 13 and a
  **chained SHA-256 hash** (including the previous record's hash), plus tests that detect tampering.
- **Phase C — QR + XML:** the AEAT QR URL (`nif`, `numserie`, `fecha`, `importe`) and the record XML in the
  official shape, ready to submit.
- **Phase D — (production, not in this project):** actual submission to the AEAT web service with a digital
  certificate + *declaración responsable*. Documented as the remaining production step; **we do not fake it**.

### Consequences
- We implement the **integrity engine and formats** (the hard, demonstrable 90%), and are explicit that
  certified production submission is out of scope.
- Money stays exact (integer cents / decimal strings). All new queries are scoped to the current `User`.

---

## Español

### Contexto
La normativa **Verifactu** en España (RD 1007/2023 + Orden HAC/1177/2024) exige que el software de
facturación genere, por cada factura, un **registro de facturación** cuya integridad se garantiza con una
**huella SHA-256 encadenada** (cada registro incluye la huella del anterior → cadena antimanipulación), un
**código QR** que apunta a la AEAT y —en modo *Verifactu*— el envío en tiempo real de los registros al
servicio web de la AEAT.

Añadir esto a Cuentia la convierte de herramienta de "visibilidad" en algo que toca un flujo real y
regulado, y luce ingeniería no trivial (hashing antimanipulación, una spec definida, dominio fiscal).

> Nota honesta sobre monetización: la AEAT ofrece una app de facturación Verifactu **gratis**, así que esto
> se construye como **muestra de portfolio / aprendizaje**, no para facturar. Ver la "visión honesta de
> producto" del README.

### Decisión
Construir un **motor de facturación Verifactu-ready** por fases:

- **Fase A — Facturación:** entidades `Customer`, `Invoice`, `InvoiceLine` (propiedad de un `User`),
  numeración correlativa por serie, desglose de IVA y totales.
- **Fase B — Registro Verifactu:** un `InvoiceRecord` (alta/anulación) con los campos del art. 13 y una
  **huella SHA-256 encadenada** (incluyendo la huella del registro anterior), con tests que detectan
  manipulación.
- **Fase C — QR + XML:** la URL del QR de la AEAT (`nif`, `numserie`, `fecha`, `importe`) y el XML del
  registro en el formato oficial, listo para enviar.
- **Fase D — (producción, fuera de este proyecto):** envío real al servicio web de la AEAT con certificado
  digital + *declaración responsable*. Documentado como el paso de producción pendiente; **no lo fingimos**.

### Consecuencias
- Implementamos el **motor de integridad y los formatos** (el 90% difícil y demostrable), y dejamos
  explícito que el envío certificado en producción queda fuera de alcance.
- El dinero sigue siendo exacto (céntimos enteros / strings decimales). Toda consulta nueva se acota al
  `User` actual.
