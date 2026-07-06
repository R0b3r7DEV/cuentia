# 27 — Open banking · Banca abierta (GoCardless)

Goal / Objetivo: let a user connect a real Spanish bank and import their movements automatically, via the
**GoCardless Bank Account Data** API (open banking / PSD2) — replacing manual CSV uploads.

*Objetivo: permitir a un usuario conectar un banco español real e importar sus movimientos
automáticamente, vía la API de **GoCardless Bank Account Data** (banca abierta / PSD2) — sustituyendo la
subida manual de CSV.*

---

## Honest status / Estado honesto

- **EN:** This integration is **built and unit-tested against the documented API shape, but not run
  against the live GoCardless service** — doing so requires app credentials, and creating them asks for
  more personal data than the owner wanted to share for a portfolio. So the feature ships **behind a flag**:
  it is **disabled unless `GOCARDLESS_SECRET_ID` and `GOCARDLESS_SECRET_KEY` are set**, and the UI shows an
  honest "not configured" note. Any deployer who adds credentials activates a working, tested flow.
- **ES:** Esta integración está **construida y testeada contra la forma documentada de la API, pero no
  ejecutada contra el servicio real de GoCardless** — hacerlo requiere credenciales de aplicación, y crearlas
  pide más datos personales de los que el dueño quería compartir para un portfolio. Así que la función va
  **tras un flag**: está **deshabilitada salvo que estén `GOCARDLESS_SECRET_ID` y `GOCARDLESS_SECRET_KEY`**, y
  la UI muestra un aviso honesto de "no configurada".

## The flow / El flujo

1. **Status** — `GET /api/bank/status` → `{ enabled }`. The frontend hides or disables the feature
   accordingly (no dead buttons).
2. **Institutions** — `GET /api/bank/institutions` lists Spanish banks (id, name, logo).
3. **Connect** — `POST /api/bank/connect { institutionId, redirect }` creates a GoCardless *requisition*
   and returns a hosted `link` (the user authorizes their bank there) plus a `requisitionId`.
4. **Import** — `POST /api/bank/import { requisitionId }` pulls the booked movements of every linked
   account and maps them to `Transaction` rows.

`GoCardlessClient` wraps the HTTP calls (token → institutions/requisitions/accounts);
`OpenBankingService` orchestrates the flow and does the mapping.

## Mapping & de-duplication / Mapeo y deduplicación

- **EN:** Each bank movement becomes a `Transaction` (`bookedAt`, `description`, signed `amount`,
  `currency`, `importedFrom = 'openbanking'`). We store the bank's transaction id as `externalId`
  (prefixed with the account id), and `TransactionRepository::existingExternalIds()` lets a re-import
  **skip anything already imported** — so syncing twice never duplicates. `toTransaction()` is a pure
  method, unit-tested directly (amount kept as an exact decimal string, description falling back to the
  creditor/debtor name, unmappable rows returning null).
- **ES:** Cada movimiento se convierte en un `Transaction`. Guardamos el id de transacción del banco como
  `externalId` (con el id de cuenta como prefijo), y `existingExternalIds()` permite que una reimportación
  **salte lo ya importado** — sincronizar dos veces nunca duplica. `toTransaction()` es un método puro,
  testeado directamente.

## Verify / Verificar

```powershell
php bin/phpunit --filter 'GoCardless|OpenBanking'
#  client parses token→institutions/requisition/transactions (MockHttpClient)
#  mapping (amount/date/desc/externalId), creditor-name fallback, dedup counts
php bin/phpunit
#  OK (36 tests, 128 assertions)
```

An integration test also asserts `/api/bank/status` returns `enabled:false` (and enabled-only endpoints
return 503) when no credentials are configured — the default, tested path.

---

**Next / Siguiente:** agentic AI + OCR (both need an Anthropic API key). / IA agéntica + OCR (ambas
necesitan una API key de Anthropic).
