# 28 — Invoice PDF · PDF de factura

Goal / Objetivo: give each invoice a **downloadable, professional PDF** with the totals, the Verifactu
fingerprint and the QR embedded — the tangible document a freelancer actually sends to a client.

*Objetivo: dar a cada factura un **PDF descargable y profesional** con los totales, la huella Verifactu y
el QR incrustados — el documento tangible que un autónomo envía de verdad a su cliente.*

---

## How / Cómo

- **EN:** `InvoicePdf` renders an HTML template to PDF with **Dompdf** (pure PHP — no `gd`/`imagick`). The
  QR is embedded as an SVG `data:` URI (`isRemoteEnabled` lets Dompdf read it), and the bundled
  *DejaVu Sans* font renders the `€` sign and Spanish accents. `GET /api/invoices/{id}/pdf` returns
  `application/pdf` as an attachment. When a Verifactu record exists, the huella and QR are printed on the
  document; otherwise the PDF renders without them.
- **ES:** `InvoicePdf` renderiza una plantilla HTML a PDF con **Dompdf** (PHP puro — sin `gd`/`imagick`).
  El QR se incrusta como `data:` URI en SVG (`isRemoteEnabled` permite a Dompdf leerlo) y la fuente
  *DejaVu Sans* incluida renderiza el `€` y los acentos. `GET /api/invoices/{id}/pdf` devuelve
  `application/pdf` como adjunto.

## In the UI / En la interfaz

Expanding an invoice on the **Invoices** page now offers **Download PDF** next to **Download XML**, below
the QR.

## Verify / Verificar

```powershell
php bin/phpunit --filter InvoicePdf
#  builds a %PDF with the Verifactu record · and without one
php bin/phpunit
#  OK (38 tests, 134 assertions)
```

---

**Note / Nota:** the PDF is a genuine invoice document; the QR is the AEAT-format code (test host), matching
the honest scope of [ADR 0003](../decisions/0003-verifactu-invoicing.md). / El PDF es un documento de
factura real; el QR es el código con formato AEAT (host de pruebas), acorde al alcance del ADR 0003.
