# 19 — Demo data & user CLI (polish) · Datos de ejemplo y CLI de usuarios

Goal / Objetivo: a fresh account is never empty, and accounts (incl. an admin) can be created from the CLI.

> Polish for going public: the #1 problem with a live demo is an **empty screen** on a new account. This
> fixes it. / Pulido para publicar: el problema nº1 de una demo en vivo es una **pantalla vacía** en una
> cuenta nueva. Esto lo resuelve.

---

## 1. "Load sample data"

- **Backend:** `POST /api/demo/load` — if the current user has no transactions, it **reuses the tested
  import + categorization pipeline** to load two months of realistic Spanish freelancer movements, then
  returns how many were loaded. No duplication (it's a no-op if data already exists).
- **Frontend:** the Movements empty state shows a **"Load sample data"** button that calls it and refreshes.

```php
if ($repo->findForUser($user) !== []) return $this->json(['loaded' => 0, 'message' => 'Account already has data']);
$import->import($this->sampleCsv(), $user);        // same code path as a real import
$categorizer->categorizeUncategorized($user);      // so VAT/IRPF/charts populate
```

- **EN:** Reusing the real pipeline (not a separate seeding path) means the demo data goes through the same
  parsing, categorization and tax logic as real data — so what a recruiter sees is genuinely the app working.
- **ES:** Reutilizar el pipeline real (no un sembrado aparte) hace que los datos de ejemplo pasen por el
  mismo parseo, categorización y lógica fiscal que los reales — así lo que ve un reclutador es la app
  funcionando de verdad.

## 2. Create users / admins from the CLI

`app:create-user` creates an account (optionally an admin) — handy for seeding a deployment and for the
admin account.

```powershell
php bin/console app:create-user admin@cuentia.local "a-strong-password" --admin
php bin/console app:create-user demo@cuentia.local "demo-password"
```

- **EN:** It validates the email/password, refuses duplicates, hashes the password, and grants `ROLE_ADMIN`
  with `--admin`. This is the safe way to create the first accounts on a server (no public admin signup).
- **ES:** Valida email/contraseña, rechaza duplicados, cifra la contraseña y otorga `ROLE_ADMIN` con
  `--admin`. Es la forma segura de crear las primeras cuentas en un servidor (sin registro admin público).

## Verify

```powershell
php bin/console app:create-user admin@cuentia.local "adminpass123" --admin   # → Created admin
# as a fresh logged-in user:
POST /api/demo/load   # → { "loaded": 15 }
```

Register a new account in the UI → the Movements page is empty → click **Load sample data** → the whole
app (table, dashboard, VAT/IRPF, forecast) fills with realistic data.

---

**Next / Siguiente:** account & GDPR (delete account / clear data), UX & responsive polish, API
integration tests. / cuenta y GDPR (borrar cuenta / limpiar datos), pulido UX y responsive, tests de
integración de la API.
