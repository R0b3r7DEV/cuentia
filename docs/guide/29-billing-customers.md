# 29 — Billing tabs & customers · Pestañas de facturación y clientes

Goal / Objetivo: turn the single Invoices screen into a **Billing** section with sub-tabs, and add full
**customer management** (CRUD) that invoices can reuse.

*Objetivo: convertir la pantalla única de Facturas en un apartado de **Facturación** con sub-pestañas, y
añadir **gestión de clientes** completa (CRUD) que las facturas reutilizan.*

---

## Billing tabs / Pestañas de facturación

The `/invoices` route now renders `BillingPage`, a shell with sub-tabs (`Facturas`, `Clientes` — and, in
later phases, `Presupuestos`, `Servicios`). Each tab is its own component under
`components/billing/`, so the section grows without cluttering the navbar.

*La ruta `/invoices` renderiza ahora `BillingPage`, con sub-pestañas; cada una es su propio componente en
`components/billing/`, así el apartado crece sin recargar el navbar.*

## Customers CRUD / CRUD de clientes

`CustomerController` exposes the full lifecycle, every action scoped to `#[CurrentUser]`:

```
GET    /api/customers          → list (own only)
POST   /api/customers          → create  { name, taxId, address?, email? }
PUT    /api/customers/{id}     → update
DELETE /api/customers/{id}     → delete  (409 if the customer has invoices)
```

- **EN:** `name` and `taxId` are required. A customer with invoices **can't be deleted** — an invoice must
  keep its issuer/customer data intact — so the endpoint returns **409** and the UI shows why. The
  `Clientes` tab lists customers and offers create / edit / delete inline.
- **ES:** `name` y `taxId` son obligatorios. Un cliente con facturas **no se puede borrar** — una factura
  debe conservar sus datos — así que el endpoint devuelve **409** y la UI explica el motivo.

## Reusing a customer on an invoice / Reutilizar un cliente en una factura

Issuing an invoice now accepts an existing **`customerId`** (the new-invoice form has a customer
dropdown), falling back to the inline get-or-create by NIF when you enter a new one. `InvoiceService`
resolves `customerId` → `customer.id` → get-or-create, always checking ownership.

*Emitir una factura acepta ahora un `customerId` existente (la factura tiene un desplegable de clientes), y
si no, busca-o-crea por NIF con los datos en línea.*

## Verify / Verificar

```powershell
php bin/phpunit --filter Customers
#  create · list · update · validation (400) · delete guard (409) · per-user isolation
php bin/phpunit
#  OK (39 tests, 146 assertions)
```

---

**Next / Siguiente:** a reusable **services catalog** to prefill invoice/quote lines, then **quotes**
(presupuestos). / un **catálogo de servicios** reutilizable, y luego los **presupuestos**.
