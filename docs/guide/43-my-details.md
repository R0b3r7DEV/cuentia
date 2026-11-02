# 43 — "Mis datos": the issuer's own data · los datos propios

Goal / Objetivo: one place for the electrician's own data, filled in once and printed on every invoice, quote,
work order and CIE — kept to what a self-employed electrician (autónomo) actually needs.

*Objetivo: un único sitio para los datos propios, que se rellenan una vez y salen en facturas, presupuestos,
partes y CIE — solo lo que necesita un autónomo.*

---

## Fields / Campos

| Field | Required for | Why |
|---|---|---|
| Nombre y apellidos (`businessName`) | invoice, CIE | RD 1619/2012 art. 6; the installation company of an autónomo is the person |
| NIF (`taxId`) | invoice, CIE | ídem |
| Domicilio fiscal (`fiscalAddress`) | invoice | RD 1619/2012 art. 6 |
| Nº de registro de empresa instaladora (`installerRegNumber`) | CIE | register of installation companies (GVA) |
| Teléfono (`phone`) | — optional | contact on documents |
| IBAN (`iban`) | — optional | "Forma de pago: transferencia" on invoices; validated with mod-97 (ISO 13616) on both sides |
| Logo | — optional | header of invoices, quotes and work orders (not the CIE: it is an official model) |

`GET /api/account/settings` returns `missing.invoice` and `missing.certificate`: the required fields still
empty. The invoices and certificates screens warn with a link to "Mis datos" while something is missing.

A new CIE takes company name, NIF, register number and installer name from "Mis datos" — in the form (so it
can be checked) and in the backend for anything left blank.

## Logo

- `POST /api/account/logo` (multipart `logo`), `GET`, `DELETE`. No id in the URL: a user only ever reaches
  their own.
- Through `ImageSanitizer::logo()`: type sniffed from the bytes, size and pixel caps before decoding,
  re-encoded with GD as PNG keeping transparency, fitted into 600 × 240 px.
- Stored in its own table `user_logo`, **not on `User`**: the user is serialised into the session on every
  request; an image there would bloat it, and a blob stream cannot be serialised.
- PDFs embed it as a `data:` URI via `IssuerProfile`, so Dompdf never fetches anything.

## Also fixed / También se arregla

- The quote PDF printed the **login e-mail** as the issuer; it now prints the fiscal name, NIF, address and
  phone, like the invoice.

## Hash chain / Cadena de hash

Only the PDF rendering changes. The fingerprinted fields (`issuerNif` is snapshotted at issue time) are
untouched; the integration test issues an invoice with the full profile and logo and `verify` stays `ok`.
