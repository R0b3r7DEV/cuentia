# 25 — Verifactu hash chain · Cadena de hash Verifactu (Fase B)

Goal / Objetivo: make issued invoices **tamper-evident**. Each invoice generates a *registro de alta*
that carries a SHA-256 fingerprint (huella) chained to the previous one — altering any past invoice
breaks every hash after it, and `/api/invoices/verify` detects exactly where.

*Objetivo: hacer las facturas emitidas **inalterables**. Cada factura genera un registro de alta con una
huella SHA-256 encadenada a la anterior — alterar cualquier factura pasada rompe todos los hashes
posteriores, y `/api/invoices/verify` detecta exactamente dónde.*

---

## The fingerprint / La huella

`VerifactuHasher` builds a fixed, ordered canonical string (the AEAT layout, Orden HAC/1177/2024) and
hashes it with SHA-256, hex uppercase:

```
IDEmisorFactura=<NIF>&NumSerieFactura=<serie/num>&FechaExpedicionFactura=<dd-mm-yyyy>
&TipoFactura=F1&CuotaTotal=<iva>&ImporteTotal=<total>&Huella=<hash anterior>&FechaHoraHusoGenRegistro=<ISO-8601>
```

- **EN:** The **previous record's hash** is one of the inputs, so each fingerprint depends on the entire
  history before it — that is what makes the records a *chain* rather than independent stamps.
- **ES:** El **hash del registro anterior** es una de las entradas, así que cada huella depende de toda la
  historia previa — eso es lo que convierte los registros en una *cadena* y no en sellos independientes.

## The record / El registro

`InvoiceRecord` stores a **snapshot** of every fingerprint input at issue time (issuer NIF, full number,
date, type, VAT, total, timestamp) plus `previousHash` and `hash`. Snapshotting matters: verification
recomputes the hash from the record's *own stored fields*, so if someone edits the invoice later, the
recomputed hash no longer matches the sealed one.

*El registro guarda una **copia** de cada entrada de la huella en el momento de emisión, más `previousHash`
y `hash`. La copia es clave: la verificación recalcula el hash desde los campos guardados del propio
registro, así que si alguien edita la factura después, el hash recalculado ya no coincide con el sellado.*

## Verification / Verificación

`VerifactuChain::verify(records)` walks the chain oldest-first and, per record, checks:

1. **`previousHash` matches the actual previous hash** → catches a deleted or reordered record
   (`previous_hash_mismatch`).
2. **the sealed `hash` still equals the recomputed fingerprint** → catches a tampered field
   (`record_tampered`).

`GET /api/invoices/verify` returns `{ ok, count }` or `{ ok:false, brokenAt, reason }`.

## A subtle bug the tests caught / Un fallo sutil que cazaron los tests

- **EN:** The first integration run failed only on SQLite. A `NUMERIC` amount saved as `1210.00` was read
  back as `1210`, so the fingerprint recomputed after a DB round-trip didn't match the one sealed at
  creation. PostgreSQL (production) returns `1210.00`, so the live check had passed and hidden it. **Lesson:
  a cryptographic hash must never depend on how the database formats a value.** Fixed by normalizing every
  amount to two decimals inside the canonical string.
- **ES:** La primera ejecución de integración falló solo en SQLite. Un importe `NUMERIC` guardado como
  `1210.00` se releía como `1210`, así que la huella recalculada tras el viaje a la BD no coincidía con la
  sellada al crear. PostgreSQL (producción) devuelve `1210.00`, por eso la prueba en vivo había pasado y lo
  ocultaba. **Lección: un hash criptográfico nunca debe depender de cómo formatee un valor la base de
  datos.** Resuelto normalizando cada importe a dos decimales dentro de la cadena canónica.

## Verify / Verificar

```powershell
php bin/phpunit --filter 'VerifactuChain|Invoicing'
#  fingerprint determinism · chaining · tamper detection (field + reseal) · end-to-end verify
php bin/phpunit
#  OK (24 tests, 86 assertions)
```

---

**Next / Siguiente:** Phase C — the invoice **QR** (to the AEAT) and the **XML** export. /
Fase C — el **QR** de la factura (a la AEAT) y la exportación **XML**.
