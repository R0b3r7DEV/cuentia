# 26 — Verifactu QR & XML · QR y XML Verifactu (Fase C)

Goal / Objetivo: give each issued invoice its two Verifactu artifacts — the **QR** that links to the AEAT
validation service and the **RegistroAlta XML** — and surface both in the invoice detail.

*Objetivo: dar a cada factura emitida sus dos artefactos Verifactu — el **QR** que enlaza al servicio de
validación de la AEAT y el **XML de RegistroAlta** — y mostrarlos en el detalle de la factura.*

---

## The QR / El QR

`VerifactuQr` builds a URL to the AEAT `ValidarQR` service carrying the invoice's identity
(`nif`, `numserie`, `fecha`, `importe`) and renders it as an **SVG** with `endroid/qr-code`.

- **EN:** SVG (not PNG) on purpose: it is scalable and needs **no `gd`/`imagick` PHP extension**, which
  keeps the Docker image and CI lean. `GET /api/invoices/{id}/qr` returns `image/svg+xml`.
- **ES:** SVG (no PNG) a propósito: es escalable y **no necesita la extensión `gd`/`imagick`**, lo que
  mantiene ligera la imagen Docker y el CI. `GET /api/invoices/{id}/qr` devuelve `image/svg+xml`.

## The XML / El XML

`VerifactuXml` serializes the record as a `RegistroAlta` document with `DOMDocument` (guaranteed
well-formed): `IDFactura` (emisor, número, fecha), `TipoFactura`, `CuotaTotal`, `ImporteTotal`, the
`Encadenamiento` block (`PrimerRegistro` for the first, `RegistroAnterior/Huella` for the rest),
`TipoHuella` (01 = SHA-256), `Huella` and `FechaHoraHusoGenRegistro`. `GET /api/invoices/{id}/xml` returns
it as an `application/xml` download.

## Honest scope / Alcance honesto

- **EN:** The QR points at the AEAT **pre-production (test)** host and the XML mirrors the record's fields
  rather than wrapping them in the full SOAP `SuministroLR` envelope. Cuentia is not a registered issuer, so
  this is a **faithful demonstration of the format**, not a live fiscal submission — that real submission is
  Phase D, deliberately out of scope (see [ADR 0003](../decisions/0003-verifactu-invoicing.md)).
- **ES:** El QR apunta al host de **pruebas** de la AEAT y el XML refleja los campos del registro en lugar de
  envolverlos en el sobre SOAP `SuministroLR` completo. Cuentia no es un emisor registrado, así que esto es
  una **demostración fiel del formato**, no un envío fiscal real — ese envío es la Fase D, fuera de alcance.

## In the UI / En la interfaz

Expanding an invoice on the **Invoices** page now shows, next to the fingerprint, the scannable QR and a
**Download XML** link, with a one-line note stating it is a demonstration.

## Verify / Verificar

```powershell
php bin/phpunit --filter VerifactuDocuments
#  QR url carries nif/numserie/importe · QR renders as <svg> · XML well-formed + carries the Huella
php bin/phpunit
#  OK (28 tests, 106 assertions)
```

---

**Next / Siguiente:** open banking (GoCardless Bank Account Data) — real bank imports. /
banca abierta (GoCardless) — importación real de movimientos bancarios.
