# 39 — Work orders (partes de trabajo): the everyday job · el día a día

Goal / Objetivo: model the 80 % of an electrician's real work — call-outs and repairs — as a **work order**
that is filled in at the site and, when finished, **converts into a real invoice** without ever
duplicating.

*Objetivo: modelar el 80 % del trabajo real de un electricista —avisos y reparaciones— como un **parte de
trabajo** que se rellena a pie de obra y, al terminar, **se convierte en factura real** sin duplicar nunca.*

> This guide covers the **backend** (entities, service, API). The mobile UI, photos and the client
> signature land in the next step (PR3). / Esta guía cubre el **backend**; la UI móvil, las fotos y la firma
> del cliente llegan en el siguiente paso.

---

## The model / El modelo

- **`WorkOrder`**: `user`, `customer`, `title`, `description`, `status`, `scheduledAt`, labour (`laborHours`
  × `laborRate`, plus `laborVatRate`), a `convertedInvoice` link, and material `lines`.
- **`WorkOrderLine`**: description, quantity, unit price, VAT rate — exact **integer cents**, exactly like
  `InvoiceLine`/`QuoteLine`.
- **Lifecycle**: `pendiente → en_curso → terminado → facturado`.

## Convert → invoice, idempotently / Conversión idempotente

`WorkOrderService::convert()` reuses the **exact** pattern of `QuoteService::convert()`: a `convertedInvoice`
link means *convert twice ⇒ same invoice*. Materials become invoice lines; the labour hours become one
**"Mano de obra (N h)"** line priced at `hours × rate`. The order is then marked `facturado` and linked.

The invoice is issued through the one door, `InvoiceService::create()`, so it gets the **gapless number** and
the **hash-chain record** for free — and, per [ADR 0004](../decisions/0004-dual-billing-mode.md), it prints
as a standard or Verifactu-demo invoice according to the user's billing mode. An invoiced order is
**immutable** (update/delete → 409).

*`convert()` reutiliza el patrón de `QuoteService::convert()`: el enlace `convertedInvoice` garantiza que
convertir dos veces no duplica. Los materiales pasan a líneas; la mano de obra a una línea "Mano de obra
(N h)". La factura se emite por la única puerta, `InvoiceService::create()`, así que hereda numeración sin
huecos y registro de la cadena. Un parte ya facturado es inmutable (409).*

## API

| Method | Route | What |
|---|---|---|
| GET | `/api/work-orders` | list (newest first, own only) |
| POST | `/api/work-orders` | create |
| GET | `/api/work-orders/{id}` | detail |
| PUT | `/api/work-orders/{id}` | update (409 if invoiced) |
| DELETE | `/api/work-orders/{id}` | delete (409 if invoiced) |
| POST | `/api/work-orders/{id}/convert` | convert to invoice (idempotent) |

Everything is scoped to the current user (`findOwned`).

## Tests

`WorkOrderServiceTest` (create, labour cents, no-title, idempotent shortcut) + an integration test that
creates an order, **converts it twice and asserts a single invoice** with the right total (materials +
labour), plus per-user isolation and invoiced-immutability (409). 91 tests overall.
