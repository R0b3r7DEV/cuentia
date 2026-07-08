# 30 — Services catalog · Catálogo de servicios

Goal / Objetivo: a reusable catalog of services/products (name, unit price, VAT) so a line can be added to
an invoice with one click instead of retyping it every time.

*Objetivo: un catálogo reutilizable de servicios/productos (nombre, precio, IVA) para añadir una línea a
una factura con un clic en vez de reescribirla cada vez.*

---

## Backend

`Service` entity (user-scoped: `name`, `unitPrice`, `vatRate`) + `ServiceController` CRUD:

```
GET    /api/services        → list (own only)
POST   /api/services        → create  { name, unitPrice, vatRate }
PUT    /api/services/{id}   → update
DELETE /api/services/{id}   → delete
```

- **EN:** `name` is required; prices are stored as exact decimal strings. **Deleting a catalog item is
  always safe** — invoice/quote lines keep their *own copy* of the data at creation time, so past documents
  never change when the catalog does. Migration `Version20260708075806`.
- **ES:** `name` es obligatorio; los precios se guardan como strings decimales exactos. **Borrar un
  elemento del catálogo es siempre seguro** — las líneas guardan su *propia copia* de los datos al crearse.

## Frontend

A new **Servicios** tab manages the catalog (create/edit/delete). In the new-invoice form, an **"Add from
catalog…"** dropdown appends a line prefilled with the service's description, price and VAT (the first pick
replaces the empty starter row). The user can still edit the line afterwards — the catalog is a shortcut,
not a constraint.

*Una nueva pestaña **Servicios** gestiona el catálogo. En el formulario de factura, un desplegable
**«Añadir del catálogo…»** añade una línea prellenada con el concepto, precio e IVA del servicio.*

## Verify / Verificar

```powershell
php bin/phpunit --filter Services
#  create · validation (400) · update · list · delete · per-user isolation (404 on others')
php bin/phpunit
#  OK (40 tests, 157 assertions)
```

---

**Next / Siguiente:** quotes (presupuestos) — non-fiscal documents that convert into a real Verifactu
invoice. / presupuestos — documentos no fiscales que se convierten en factura Verifactu real.
