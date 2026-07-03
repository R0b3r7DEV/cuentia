# 22 — API integration tests · Tests de integración de la API

Goal / Objetivo: boot the real app and test the HTTP endpoints end to end (auth + per-user isolation).

---

## What they cover

`tests/Api/ApiIntegrationTest.php` (extends `WebTestCase`) exercises the real kernel, firewall and database:

- **Auth flow**: register → login → `/api/me` → logout (and `/api/me` → 401 afterwards).
- **Access control**: an unauthenticated `GET /api/transactions` returns **401**.
- **Duplicate email**: a second register with the same email returns **409**.
- **Per-user isolation**: user A imports two movements and sees 2; user B sees **0** (the security core).
- **Account**: clear data (→ 0) and delete account.

- **EN:** Unlike the unit tests (pure logic), these make **real HTTP requests** through the whole stack, so
  they prove the wiring — routes, security, serialization, database — actually works together.
- **ES:** A diferencia de los tests unitarios (lógica pura), estos hacen **peticiones HTTP reales** por
  toda la pila, así que demuestran que el cableado — rutas, seguridad, serialización, base de datos —
  funciona de verdad en conjunto.

## How it runs (SQLite in-memory, no DB service)

```
# .env.test
DATABASE_URL="sqlite:///:memory:"
```

- Each test creates the schema from the entity metadata (`SchemaTool`) and calls `disableReboot()` so the
  same kernel — and its in-memory database — is reused across the requests within a test.
- **No database service needed in CI**, so the tests are fast and portable.

## Two real bugs this surfaced

- **EN:** (1) The `transaction` table is a **reserved word** in SQLite; it worked on PostgreSQL but failed
  in tests. Fixed by quoting the table name (`#[ORM\Table(name: '\`transaction\`')]`) so Doctrine quotes it
  per platform. (2) SQLite **file** locking on Windows across kernel reboots — solved by the in-memory DB +
  `disableReboot()`. Integration tests earn their keep by catching exactly this kind of environment bug.
- **ES:** (1) La tabla `transaction` es **palabra reservada** en SQLite; funcionaba en PostgreSQL pero
  fallaba en tests. Resuelto entrecomillando el nombre de la tabla para que Doctrine lo cite por
  plataforma. (2) Bloqueo de **fichero** SQLite en Windows entre reinicios del kernel — resuelto con la BD
  en memoria + `disableReboot()`. Los tests de integración se ganan el sueldo cazando justo este tipo de
  errores de entorno.

## Verify

```powershell
php bin/phpunit
# OK (16 tests, 52 assertions)
```

CI now installs `pdo_sqlite` and runs the whole suite (unit + integration) on every push.

---

**Next / Siguiente:** the live deployment. / el despliegue en vivo.
