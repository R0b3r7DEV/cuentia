# 36 — Bring-your-own-key integrations · Integraciones con tus propias claves

Goal / Objetivo: let each user enable the AI and open-banking features from the **Account** page by pasting
their **own** API keys — no environment variables needed by the end user — stored **encrypted**.

*Objetivo: que cada usuario active la IA y la banca abierta desde **Cuenta** pegando sus **propias**
claves — sin tocar variables de entorno — guardadas **cifradas**.*

---

## Why BYOK / Por qué BYOK

- **EN:** Previously the Anthropic and GoCardless credentials were app-level environment variables — an end
  user couldn't set them. Now it's **bring-your-own-key**: each user adds their keys in Account → the
  features light up **for that user only**; each pays for their own Claude and connects their own banks.
  The env vars remain as an app-level fallback/default.
- **ES:** Antes las credenciales eran variables de entorno de la app — un usuario final no podía ponerlas.
  Ahora es **trae tu propia clave**: cada usuario añade las suyas en Cuenta → las funciones se activan
  **solo para él**. Las variables de entorno siguen como fallback.

## Security / Seguridad

- **EN:** Keys are secrets, so they are **encrypted at rest** with `SecretCipher` (AES-256-GCM, key derived
  from the app secret). The plaintext key is **never returned to the browser** — the status endpoint only
  reports `configured: true/false` and a masked hint (last 4 chars). Everything is per-user and scoped to
  `#[CurrentUser]`.
- **ES:** Las claves son secretos, así que van **cifradas en reposo** con `SecretCipher` (AES-256-GCM,
  clave derivada del secreto de la app). La clave en claro **nunca se devuelve al navegador** — el estado
  solo indica `configured` y una pista enmascarada (últimos 4). Todo por usuario.

## How it's wired / Cómo está cableado

`CredentialStore` is the single resolver: `anthropicKey(user)` / `gocardless(user)` return the user's own
(decrypted) credentials, else the env fallback. The AI services (`ChatService`, `CategorizerService`) and
open banking (`GoCardlessClient::configure()` via `OpenBankingService` + `BankController`) all read through
it, so a user's key takes effect immediately.

```
GET    /api/account/integrations              → { anthropic:{configured,hint}, gocardless:{configured,hint} }
PUT    /api/account/integrations/anthropic    → { key }
DELETE /api/account/integrations/anthropic
PUT    /api/account/integrations/gocardless   → { secretId, secretKey }
DELETE /api/account/integrations/gocardless
```

## Frontend

An **Integrations** section on the Account page: for each of AI (Claude) and Open banking (GoCardless), a
configured/masked badge, a password input to paste the key(s), Save / Remove, a link to get the key, and a
privacy note. The assistant's "AI not configured" note now points here.

## Verify / Verificar

```powershell
php bin/phpunit --filter 'SecretCipher|PerUserApiCredentials'
#  cipher round-trip + tamper rejection · BYOK: set/clear keys, GoCardless enables /bank/status, isolation
php bin/phpunit
#  OK (58 tests, 250 assertions)
```

---

**Note / Nota:** once a user saves a real Claude key, AI categorization and the assistant switch from the
rule-based fallback to real AI for that user. / En cuanto un usuario guarda una clave de Claude real, la
categorización y el asistente pasan del fallback por reglas a IA real para ese usuario.
