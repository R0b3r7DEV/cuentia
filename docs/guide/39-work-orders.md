# 39 — Work orders (partes de trabajo): the everyday job · el día a día

Goal / Objetivo: model the 80 % of an electrician's real work — call-outs and repairs — as a **work order**
that is filled in at the site and, when finished, **converts into a real invoice** without ever
duplicating.

*Objetivo: modelar el 80 % del trabajo real de un electricista —avisos y reparaciones— como un **parte de
trabajo** que se rellena a pie de obra y, al terminar, **se convierte en factura real** sin duplicar nunca.*

> This guide covers the **backend**: entities, lifecycle, signature, photos, PDF and API. The mobile UI lands
> in the next PR. / Esta guía cubre el **backend**; la UI móvil llega en el siguiente PR.

---

## The model / El modelo

- **`WorkOrder`**: `user`, `customer`, `title`, `description`, `status`, `scheduledAt`, labour (`laborHours`
  × `laborRate`, plus `laborVatRate`), a `convertedInvoice` link, material `lines`, `photos` and the client
  signature (`signaturePng`, `signedBy`, `signedAt`, or `signatureWaived`).
- **`WorkOrderLine`**: description, quantity, unit price, VAT rate — exactly like `InvoiceLine`/`QuoteLine`.
- **`WorkOrderPhoto`**: a re-encoded JPEG stored as `bytea` ([ADR 0005](../decisions/0005-work-order-photo-storage.md)).
- **`User.defaultLaborRate`**: euros/hour pre-filled in every new order (Account → Billing).

**Money in integer cents.** `laborBaseCents()` reads hours and rate as hundredths and multiplies integers,
rounding once; VAT is rounded per line like `InvoiceLine`. `totalsCents()` adds materials and labour with the
same rule the invoice uses, so **the order and the invoice it becomes always agree to the cent**.

*Dinero en céntimos enteros: la mano de obra se calcula con enteros (sin `float`) y el IVA se redondea por
línea, igual que en la factura, así que el parte y su factura coinciden al céntimo.*

## Lifecycle / Ciclo de vida

```
pendiente ──► en_curso ──► terminado ──convert()──► facturado
    ◄────────────┘   ◄──── reabrir ───┘
```

| From | Allowed manual moves | Editable? |
|---|---|---|
| `pendiente` | `en_curso` | yes (and deletable) |
| `en_curso` | `pendiente`, `terminado` | yes (and deletable) |
| `terminado` | `en_curso` (reopen) | no |
| `facturado` | — | no, immutable |

- **`facturado` is never a manual move**: only `convert()` reaches it, so an order can never look invoiced
  without a real invoice behind it. *Solo `convert()` llega a facturado.*
- Status changes go through `POST /status`; a `PUT` edits content and **ignores** `status`.
- **Finishing needs the client's signature**, or `withoutSignature: true` when the client was not there
  (recorded as `signatureWaived` and printed on the PDF).
- **Reopening** a finished order drops its signature: it covered work that is about to change.
- A new order starts `pendiente`, or `en_curso` when it is created at the site.

## Convert → invoice, idempotently / Conversión idempotente

`WorkOrderService::convert()` reuses the **exact** pattern of `QuoteService::convert()`: a `convertedInvoice`
link means *convert twice ⇒ same invoice* (checked first, whatever the state). Otherwise the order must be
`terminado` (409 `not_finished`). Materials become invoice lines; the labour hours become one **"Mano de obra
(N h)"** line whose price is built from integer cents. The order is then marked `facturado` and linked.

The invoice is issued through the one door, `InvoiceService::create()`, so it gets the **gapless number** and
the **hash-chain record** for free — and, per [ADR 0004](../decisions/0004-dual-billing-mode.md), it prints
as a standard or Verifactu-demo invoice according to the user's billing mode. Nothing here touches the
fingerprint, and `GET /api/invoices/verify` stays green (asserted at the end of the lifecycle test).

## Signature and photos / Firma y fotos

Both are **untrusted uploads**, handled by `ImageSanitizer`:

- the real type is sniffed from the bytes (the MIME type and file name are ignored);
- the pixel count is capped **from the header, before decoding** (decompression bombs);
- the image is decoded with GD and **re-encoded**, which drops all metadata — **EXIF GPS included**;
- photos: JPEG/PNG in, JPEG ≤ 1600 px out, max 6 per order; signature: PNG in, PNG out (transparency kept).

A photo is only ever looked up **inside the user's own order**, so knowing a photo id is not enough (IDOR).
Images are served with `X-Content-Type-Options: nosniff`.

*Las dos son subidas no fiables: se mira el tipo real, se limita el tamaño antes de decodificar y se
re-codifican con GD, lo que borra los metadatos, incluido el GPS. Una foto solo se busca dentro del parte del
propio usuario.*

## PDF

`WorkOrderPdf` follows `QuotePdf`: issuer (fiscal name, NIF, address), client, job, materials, labour,
totals, photo thumbnails and the signature ("Firmado por … el …", or "sin firma: cliente ausente"). Remote
loading is **off**: images are embedded as `data:` URIs, so user text can never make Dompdf fetch a URL
(SSRF). Not a fiscal document — the invoice is issued separately.

## API

| Method | Route | What |
|---|---|---|
| GET | `/api/work-orders` | list (newest first, own only), with `totalCents` |
| POST | `/api/work-orders` | create |
| GET | `/api/work-orders/{id}` | detail: totals in cents, `editable`, `transitions`, photos, signature |
| PUT | `/api/work-orders/{id}` | edit content (409 if not editable) |
| DELETE | `/api/work-orders/{id}` | delete (409 if not editable) |
| POST | `/api/work-orders/{id}/status` | `{status, withoutSignature?}` |
| PUT · DELETE | `/api/work-orders/{id}/signature` | `{png, signedBy}` · remove |
| GET | `/api/work-orders/{id}/signature` | the signature PNG |
| POST | `/api/work-orders/{id}/photos` | multipart, field `photo` |
| GET · DELETE | `/api/work-orders/{id}/photos/{photoId}` | the JPEG · remove |
| GET | `/api/work-orders/{id}/pdf` | the work-order PDF |
| POST | `/api/work-orders/{id}/convert` | convert to invoice (idempotent) |

Errors come back as **fixed codes**, never as raw exception messages: 400 for bad input (`title_required`,
`invalid_amount`, `invalid_image`, `image_too_large`, `invalid_signature`, `signer_required`, …) and 409 for
something not allowed in the current state (`invalid_transition`, `signature_required`, `not_editable`,
`not_finished`, `photo_limit`, `invoiced_immutable`).

## Tests

- `WorkOrderTest`: labour and VAT in integer cents (including the rounding edges), totals, the transition
  table (`facturado` unreachable by hand), reopening drops the signature.
- `WorkOrderServiceTest`: creation rules, default rate, negative amounts, transitions and their codes,
  signature required/waived, edits locked when finished, convert only when finished, the labour line sent as
  exact cents, the photo cap.
- `ImageSanitizerTest`: EXIF GPS stripped, PNG → JPEG, downscaling, a fake image, a decompression bomb, the
  signature PNG keeps transparency, wrong/oversized signatures.
- `WorkOrderPdfTest`: content and totals, embedded photo and signature, the waiver, escaped user text, and a
  real render with images while remote loading is off.
- Integration: the full site lifecycle (create → start → photo → sign → finish → PDF → convert twice → one
  invoice with the same total → hash chain verifies), reopening, per-user isolation of photos, signature and
  PDF, and the default labour rate.
