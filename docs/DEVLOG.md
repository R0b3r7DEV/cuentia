# Dev log · Diario de desarrollo

**Languages:** [English](#english) · [Español](#español)

A running, dated journal of every meaningful step: what we did, why, and what's next.
Newest entries at the top.

*Diario fechado de cada paso importante: qué hicimos, por qué y qué viene después. Las entradas más
recientes van arriba.*

---

## English

### 2026-07-08 — Entry 040: Close the loop — design → CIE & design → quote
**Done**
- Wired the installation designer into the rest of the billing module (frontend-only). From a computed
  design, two buttons: **"Create certificate (CIE)"** prefills the Certificados form with the technical
  data (power→kW, voltage, supply, earthing TT, circuits, IGA 25/40 A, differential 30 mA); **"Materials →
  quote"** opens the Presupuestos form with a line per material. Added cross-tab prefill plumbing in
  `BillingPage` (a tab hands data to another; manual tab clicks clear it). No placeholders: the user
  completes the customer/identity where it belongs.

**Why**
- This is the differentiator I kept pointing at: **design → CIE → quote → invoice** in one tool, each step
  feeding the next. The data the electrician already entered once now flows through the whole chain.

**Next**
- Designer Phase 2 (2D plan) / Phase 3 (3D). Agentic AI + OCR still need an Anthropic key.

### 2026-07-08 — Entry 039: Installation designer — Phase 1 (ITC-BT-25 calculator + single-line diagram)
**Done**
- New **Instalación** tab: a REBT **ITC-BT-25** calculator. `InstallationCalculator` (pure, unit-tested)
  turns rooms + expected loads into circuits (C1–C12 with section/PIA, splitting into C6/C7 when point
  limits are exceeded), minimum points per room, grade & power to contract, differentials, a bill of
  materials and an estimated cable length. `InstallationController` exposes a stateless `/compute` plus CRUD
  to save designs (`Installation` entity, JSON columns; result recomputed from stored input). The frontend
  recomputes live and renders a **single-line diagram (SVG)** + circuits/points/materials tables. Migration
  added. Suite now **52 tests, 233 assertions** (6 calculator unit + integration).
- Researched and grounded in ITC-BT-25 (5 mandatory circuits básico, 9+ elevado; one differential per 5
  circuits; per-room point minima). Honest framing: a pre-dimensioning aid; cable is an estimate; not a
  signed project.

**Why**
- The electrician's design → CIE → quote → invoice chain starts here. This is Phase 1 of the designer we
  scoped (enfoque B); Phase 2 = 2D floor-plan editor, Phase 3 = extruded 3D view.

**Next**
- Phase 2 (2D plan), autofill the CIE from a design, materials → quote. Agentic AI + OCR still need a key.

### 2026-07-08 — Entry 038: CIE — make it really submittable (sign-ready + AutoFirma)
**Done**
- Took the CIE from "draft" to **sign-ready**. Researched the GVA telematic procedure: documents must be
  **PDF digitally signed** (DNIe / FNMT / ACCV / Cl@ve-firma) and filed by the authorised installer at the
  sede — there is **no third-party submission API**. Chose the secure path (with the user): Cuentia never
  handles the installer's certificate.
- Upgraded `CiePdf` to a complete CERTINS E-style document: full compliance declaration (RD 842/2002 +
  ITC-BT, favourable measurements), *lugar y fecha*, and **reserved digital-signature areas** for the
  company and the installer ("firma electrónica AutoFirma/ACCV"). Added an in-app **"How to sign & file it"**
  panel (download → sign with AutoFirma/ACCV → file at the GVA sede) with official links.

**Why**
- "Presentable de verdad" = a faithful document + a real signature + filing. The signature is done locally
  by the installer (private key never leaves their machine, as GVA recommends); filing has no public API, so
  it stays the installer's manual upload — the same honest boundary as Verifactu's real AEAT submission.

**Next**
- Agentic AI + OCR — both need an Anthropic API key.

### 2026-07-08 — Entry 037: Electrical Installation Certificate (CIE / CERTINS E)
**Done**
- Researched the Comunitat Valenciana CIE and confirmed an official model — **CERTINS E (12/2012)**, filed
  telematically with a digital signature via the GVA sede electrónica. Implemented a **Certificate** entity
  + `CertificateController` CRUD + `CiePdf` (a CERTINS E-style PDF via Dompdf) + a **Certificados** tab in
  Billing. Fields follow the REBT (RD 842/2002): installation, titular, installer company/installer, and
  technical characteristics (power, voltage, supply, earthing, IGA, differential, etc.). Migration added.
- Honest framing throughout (form note + PDF footer): it's a **fill-in draft aid**, not the official
  telematic+signed submission. Tests: CRUD, validation, PDF, per-user isolation. Suite **45 tests, 192
  assertions**. Guide 32. (Fixed two small bugs the tests caught: an undefined-key read on an unset
  `installationType`, and using null as an array offset in the PDF.)

**Why**
- A real plus for an electrician using the app: the CIE is the document they issue on every job, and
  reusing the same data they already keep for invoicing removes duplicate typing.

**Next**
- Agentic AI + OCR — both need an Anthropic API key.

### 2026-07-08 — Entry 036: Quotes (presupuestos) + convert-to-invoice
**Done**
- Added **quotes**: `Quote`/`QuoteLine` entities (non-fiscal, no hash chain), `QuoteService`,
  `QuoteController` (`list/create/get/status/convert/pdf`), `QuotePdf`, migration, and a **Presupuestos**
  tab (create with customer + catalog lines + *valid until*, status pill, per-row actions). The headline:
  **convert** copies a quote into `InvoiceService::create()` to produce a fully sealed Verifactu invoice —
  **idempotent** (converts once, never duplicates), then marks the quote `converted` and links it.
- Tests: `QuoteServiceTest` (totals/numbering/default series/draft/empty guard) + an integration test of
  create → status → convert → invoice sealed → idempotent → PDF. Suite now **44 tests, 179 assertions**.
  Guide 31. Fixed a create bug where an unset `status` re-read an undefined key and blanked the status.

**Why**
- Freelancers quote before they invoice; converting the accepted quote in one click (into a compliant
  invoice) is the natural workflow and keeps the fiscal chain separate from non-fiscal offers.

**Next**
- Agentic AI + OCR — both need an Anthropic API key.

### 2026-07-08 — Entry 035: Services catalog (reusable line items)
**Done**
- Added a reusable **services/products catalog**: `Service` entity (user-scoped name/unitPrice/vatRate),
  `ServiceController` CRUD (`/api/services`), migration `Version20260708075806`, and a **Servicios** tab.
  The new-invoice form gets an **"Add from catalog…"** dropdown that appends a line prefilled from the
  chosen service (still editable). Suite now **40 tests, 157 assertions** (CRUD + validation + isolation).

**Why**
- Freelancers bill the same handful of services repeatedly; a catalog removes the retyping. Lines copy the
  service's values at creation, so deleting a catalog item never rewrites past invoices.

**Next**
- Quotes (presupuestos) — non-fiscal documents that convert into a real Verifactu invoice.

### 2026-07-08 — Entry 034: Billing tabs + customer management (CRUD)
**Done**
- Turned the Invoices screen into a **Billing** section with sub-tabs (`BillingPage` → `Facturas`,
  `Clientes`; `Presupuestos`/`Servicios` coming). Added **customer CRUD**: `CustomerController`
  (`GET/POST/PUT/DELETE /api/customers`, all user-scoped) with a **delete guard** (409 if the customer has
  invoices) and required `name`/`taxId`. New `Clientes` tab (list + create/edit/delete). Issuing an invoice
  can now reuse an existing customer by **`customerId`** (dropdown in the form); `InvoiceService` resolves
  id → get-or-create.
- Tests: an integration test covering create/list/update, validation (400), the delete guard (409) and
  per-user isolation. Suite now **39 tests, 146 assertions**. Guide 29.

**Why**
- This is the step from "issue a one-off invoice" toward an actual billing tool: customers you reuse, a
  section that can hold quotes and a services catalog next.

**Next**
- A services catalog to prefill lines, then quotes (presupuestos) with convert-to-invoice.

### 2026-07-08 — Entry 033: Invoice PDF (with embedded QR)
**Done**
- Each invoice can now be downloaded as a **professional PDF** (`InvoicePdf` service via **Dompdf**, pure
  PHP): issuer/customer, line table, totals, the Verifactu **huella** and the **QR** embedded as an SVG
  `data:` URI. `GET /api/invoices/{id}/pdf`; the Invoices page shows a **Download PDF** link next to XML.
  Guide 28. Suite now **38 tests, 134 assertions** (unit render + integration endpoint).
- Also refreshed the **README** to showcase Verifactu invoicing and open banking, with an engineering
  highlight on the hash chain and an honestly rewritten scope section.

**Why**
- The QR/XML were developer artifacts; a PDF is the document a freelancer actually sends. Dompdf keeps it
  gd/imagick-free, and DejaVu Sans renders the € sign and accents.

**Next**
- Agentic AI + OCR — both need an Anthropic API key.

### 2026-07-06 — Entry 032: Open banking (GoCardless) — behind a feature flag
**Done**
- Built a real open-banking import via **GoCardless Bank Account Data** (PSD2): `GoCardlessClient` (token →
  institutions → requisition → account transactions), `OpenBankingService` (connect + import with mapping),
  and `BankController` (`/api/bank/status|institutions|connect|import`). Movements now carry an `externalId`
  (migration `Version20260706103931`) so re-imports **skip duplicates**. Frontend `BankConnect` component on
  the Movements page: pick a bank → authorize (hosted GoCardless link) → import.
- **Feature flag:** disabled unless `GOCARDLESS_SECRET_ID`/`_KEY` are set; the UI then shows an honest "not
  configured" note and enabled-only endpoints return 503.
- Tests: `GoCardlessClientTest` (MockHttpClient), `OpenBankingServiceTest` (mapping, creditor-name fallback,
  dedup counts), integration test of the disabled path. Suite now **36 tests, 128 assertions**.

**Why**
- Creating GoCardless app credentials asks for more personal data than makes sense for a portfolio, so the
  integration is built and **tested against the documented API shape but not run live** — shipping it behind
  a flag is the honest way to showcase the capability without pretending it's been exercised end-to-end.

**Next**
- Agentic AI + OCR — both need an Anthropic API key.

### 2026-07-06 — Entry 031: Verifactu invoicing — Phase C (QR + XML)
**Done**
- Each invoice now has its two Verifactu artifacts. `VerifactuQr` builds the AEAT `ValidarQR` URL (nif,
  numserie, fecha, importe) and renders it as an **SVG** via `endroid/qr-code` (SVG needs no gd/imagick);
  `VerifactuXml` serializes a `RegistroAlta` XML with `DOMDocument`. Endpoints `GET /api/invoices/{id}/qr`
  (image/svg+xml) and `/xml` (download). The Invoices page shows the scannable QR + a Download XML link in
  the expanded detail, with a note that it's a faithful demonstration (test host), not a live submission.
- Tests: `VerifactuDocumentsTest` (QR url fields, SVG render, XML well-formed + carries the huella, first-vs
  -chained record) + integration coverage of both endpoints. Suite now **28 tests, 106 assertions**.

**Why**
- SVG over PNG keeps the Docker image and CI free of the `gd`/`imagick` extension. The QR and XML follow the
  AEAT format faithfully but point at the pre-production host — Cuentia isn't a registered issuer, so real
  SOAP submission stays Phase D (out of scope), as the ADR says.

**Next**
- Open banking (GoCardless Bank Account Data) — real bank movement imports.

### 2026-07-06 — Entry 030: Invoices page — surfacing Verifactu in the UI
**Done**
- New **Invoices** page (React): issue an invoice (customer + dynamic lines with a live client-side total
  preview), see the list, and expand any row to reveal its **Verifactu fingerprint** (hash, the record it
  chains to, sealed timestamp). A **"Verify chain"** button calls `/api/invoices/verify` and shows a green
  "🔒 Chain intact · N records verified" badge (or an amber "broken at …" one). Added the route, the navbar
  link and full ES/EN strings. Frontend build green (604 modules).

**Why**
- The Verifactu engine (phases A–B) was API-only and therefore invisible to anyone opening the live app.
  This page makes the tamper-evident chain something a visitor can *see and operate* — the point of the
  feature for a portfolio.

**Next**
- Phase C: the invoice QR (to the AEAT) and XML export, embedded in this same page.

### 2026-07-06 — Entry 029: Verifactu invoicing — Phase B (tamper-evident hash chain)
**Done**
- Every issued invoice now generates an `InvoiceRecord` (Verifactu *registro de alta*) carrying a
  **SHA-256 fingerprint chained to the previous record** — the core anti-fraud mechanism. Added
  `VerifactuHasher` (canonical string + hash), `VerifactuChain` (integrity verifier), the record entity +
  repository, wired generation into `InvoiceService`, and a `GET /api/invoices/verify` endpoint; the
  invoice detail now returns its `verifactu` block (hash, previousHash, generatedAt). Added a `taxId` (NIF)
  to `User` as the fingerprint's issuer id. Migration `Version20260706083619`.
- Tests: `VerifactuChainTest` (determinism, chaining, tamper detection — both a mutated field and a
  resealed-but-unlinked record) + an end-to-end integration test (two invoices → `/verify` ok, count 2,
  chained, per-user isolation). Suite now **24 tests, 86 assertions**.

**Why**
- A field-mutation test failed *only on SQLite*: a `NUMERIC` `1210.00` read back as `1210`, so the
  fingerprint recomputed after a DB round-trip didn't match the sealed one (PostgreSQL returns `1210.00`,
  hiding it in the live check). Lesson banked: a cryptographic hash must never depend on how the database
  formats a value — amounts are now normalized to two decimals inside the canonical string.

**Next**
- Phase C: the invoice QR (to the AEAT) and the XML export.

### 2026-07-06 — Entry 028: Verifactu invoicing — Phase A (domain model)
**Done**
- Added the invoicing domain: `Customer`, `Invoice` and `InvoiceLine` entities (all scoped to a `User`),
  their repositories, an `InvoiceService` and an `InvoiceController` (`GET`/`POST /api/invoices`,
  `GET /api/invoices/{id}`). Migration `Version20260706075837` creates the three tables.
- Totals are computed in **integer cents** (never floats) and invoice numbers are **correlative per
  series** (`nextNumber = MAX(number)+1`) — both prerequisites for the Verifactu hash chain.
- Wrote [ADR 0003](decisions/0003-verifactu-invoicing.md) (scope A→D + honest monetization note) and
  [guide 24](guide/24-verifactu-invoicing.md). Added `InvoiceServiceTest` (totals/VAT/numbering + empty-lines
  guard): suite now **18 tests, 59 assertions**. Live check: one line @21 % + two @10 % → base 1100.00,
  VAT 220.00, total 1320.00, number 2026/1.

**Why**
- Verifactu (Orden HAC/1177/2024) becomes obligatory in 2026; getting ahead of it makes Cuentia a credible
  showcase. The domain model has to be right — cents-exact totals and gapless numbering — before the
  tamper-evident hash chain can sit on top of it.

**Next**
- Phase B: the `InvoiceRecord` with the chained SHA-256 hash and tamper-detection tests.

### 2026-07-03 — Entry 027: Free hosting — Render + Neon (Railway trial expired)
**Done**
- Switched the deploy target to free tiers: **Render** (Docker web service) for the backend and **Neon**
  (PostgreSQL that doesn't pause) for the database; Vercel unchanged. Added `render.yaml` (blueprint).
- **Fixed a Dockerfile bug**: the Apache `$PORT` was substituted at build time (would be empty); now it's
  set at container start (`${PORT:-8080}`), so it works on Render/Koyeb/Fly/Railway.
- Updated the deploy guide (Render + Neon, cold-start note, Koyeb alternative).

**Why**
- The Railway trial expired and paying isn't an option; the Dockerfile being host-agnostic means only the
  hosting instructions changed, not the app.

**Next**
- Deploy on Render + Neon, then add the URL + screenshots to the README and pin the repo.

### 2026-07-03 — Entry 026: Deploy configuration (Docker + Vercel)
**Done**
- Added `backend/Dockerfile` (Apache + PHP 8.4, runs migrations on boot), `public/.htaccess` (apache-pack),
  a `when@prod` framework config (trusted proxies + secure cookies), and `frontend/vercel.json` (SPA routing
  + `/api` proxy to the backend — so no CORS and first-party session cookies).
- Wrote a full deploy guide ([guide 23](guide/23-deploy.md)): PostgreSQL (Supabase/Railway), backend on
  Railway with env vars, `app:create-user` for the admin, frontend on Vercel, verification & troubleshooting.
- Tests still green (16), dev app unaffected.

**Why**
- The frontend→backend proxy avoids the classic cross-domain cookie/CORS pain and keeps the setup simple.
  Configs are prepared and documented; the actual cloud deploy is done from the user's accounts.

**Next**
- Go live (create the accounts, deploy), then add the URL + screenshots to the README and pin the repo.

### 2026-07-03 — Entry 025: API integration tests
**Done**
- Added `tests/Api/ApiIntegrationTest` (WebTestCase): boots the real kernel/firewall/DB and tests register/
  login/me/logout, 401 for unauthenticated, duplicate-email 409, **per-user isolation** (A imports 2, B sees
  0), and clear/delete account.
- Runs on **SQLite in-memory** (`.env.test`) with `disableReboot()` — no DB service needed; CI installs
  `pdo_sqlite`.
- Caught two real bugs: `transaction` is a reserved word in SQLite (fixed by quoting the table name), and
  Windows SQLite file-locking (fixed with the in-memory DB). `php bin/phpunit` → **OK (16 tests, 52 assertions)**.
  Documented in [guide 22](guide/22-integration-tests.md).

**Why**
- Integration tests prove the whole stack is wired correctly — the strongest evidence, for an interviewer,
  that the app really works.

**Next**
- The live deployment.

### 2026-07-03 — Entry 024: UX polish — liquid-glass redesign + responsive
**Done**
- Reworked `index.css` into a "liquid glass" system (iOS/WhatsApp-inspired): translucent frosted cards and
  a floating rounded navbar (`backdrop-filter`), pill buttons/inputs, a tinted background, and WhatsApp-style
  chat bubbles (accent user bubble, asymmetric radii). Light + dark tokens.
- Responsive: navbar wraps, charts stack, tables scroll horizontally (`.table-scroll`) on mobile.
- All via tokens/classes — components unchanged. Build passes. Documented in [guide 21](guide/21-ux-glass.md).

**Why**
- A cohesive, modern finish across light/dark and desktop/mobile is what makes the project *feel*
  professional at a glance.

**Next**
- API integration tests, then deploy.

### 2026-07-03 — Entry 023: Account & GDPR (clear data / delete account)
**Done**
- Added `AccountController`: `POST /api/account/clear` (delete the user's transactions) and
  `DELETE /api/account` (delete the account + data, invalidate the session — right to erasure).
- Added an Account page (`/account`, via the email in the navbar) with clear-data, delete-account (danger)
  and a privacy note; `AuthContext.deleteAccount()`. ES/EN.
- Verified: clear 15→0; delete account → session invalidated. Documented in [guide 20](guide/20-account-gdpr.md).

**Why**
- Real accounts need a way out (GDPR), and "clear my data" is a handy reset for the live demo — both cheap
  thanks to the per-user data isolation.

**Next**
- UX & responsive polish, API integration tests, then deploy.

### 2026-07-03 — Entry 022: Demo data + user/admin CLI (polish)
**Done**
- Added `POST /api/demo/load`: loads 2 months of realistic sample movements for the current user (only if
  empty) by reusing the real import + categorization pipeline, so a fresh account is never empty.
- Added a "Load sample data" button to the Movements empty state (ES/EN).
- Added `app:create-user` console command (with `--admin`) to create accounts/admins for a deployment.
- Verified: created an admin; a fresh user loads 15 movements (2 months, 8 categories, VAT computed).
  Documented in [guide 19](guide/19-demo-and-admin.md).

**Why**
- Going public: the biggest demo-killer is an empty screen. Sample data (through the real pipeline) makes
  the app instantly explorable; the CLI safely seeds server accounts.

**Next**
- Account & GDPR (delete/clear), UX & responsive polish, API integration tests, then deploy.

### 2026-07-03 — Entry 021: Authentication & multi-user (Phase 4)
**Done**
- Added a `User` entity + Symfony security-bundle: entity provider, session `json_login`, logout, access
  control. Endpoints: `POST /api/register`, `/api/login`, `/api/logout`, `GET /api/me`.
- Scoped everything to the current user: `Transaction` now belongs to a `User`; imports set it;
  `TransactionRepository::findForUser()`; every service and the stats SQL filter by user (passed via
  `#[CurrentUser]`).
- Frontend: `AuthContext` + a login/register `AuthPage`; `App` gates the whole app; navbar shows the email
  + logout.
- Updated all unit tests to the new signatures. `php bin/phpunit` → **OK (11 tests, 37 assertions)**.
  Verified end to end with session cookies (register → login → scoped data → 401 when logged out).
  Documented in [guide 18](guide/18-auth.md).

**Why**
- Multi-user data isolation is what turns this from a demo into a real SaaS — and enforcing it at the query
  layer is the correct, secure way.

**Next**
- Production hardening + the live deploy.

### 2026-07-02 — Entry 020: Natural-language assistant (AI + fallback)
**Done**
- Added `ChatService`: builds a factual context (balance, income/expenses, top categories, VAT, next IRPF
  payment) and asks Claude to answer using only it; deterministic **data-summary fallback** without a key.
- Added `POST /api/chat` and an **Assistant** page (`/chat`) with a chat UI; added the navbar link and ES/EN
  translations. Wired into the app.
- Added `ChatServiceTest` (fallback path). `php bin/phpunit` → **OK (11 tests, 37 assertions)**. Documented
  in [guide 17](guide/17-chat.md).

**Why**
- Natural-language Q&A grounded on the user's own data is the flagship AI feature — and the fallback keeps
  it honest and always usable.

**Next**
- Phase 4: authentication/multi-user, then the live deploy.

### 2026-07-02 — Entry 019: Cash-flow forecast (Phase 3 begins)
**Done**
- Added `ForecastService` (linear projection of the balance at +30/60/90 days from the average daily net)
  and `GET /api/forecast`.
- Added a `Forecast` line chart on the Dashboard (current balance + avg. monthly net tiles + an
  "estimate" note). Wired `/api/forecast` into `useFinanceData`.
- Added `ForecastServiceTest`. `php bin/phpunit` → **OK (10 tests, 33 assertions)**. Documented in
  [guide 16](guide/16-forecast.md).

**Why**
- A forward-looking number is what a freelancer actually worries about ("will I make it?"), and a
  transparent, clearly-labeled model keeps it honest.

**Next**
- Natural-language questions over the transactions (AI), then auth + deploy.

### 2026-07-02 — Entry 018: Norma 43 importer (Phase 2 complete)
**Done**
- Added `ImportService::importNorma43()` — parses the fixed-width AEB Cuaderno 43 format (record 22 =
  movement, 23 = concept), and an `import()` dispatcher that **auto-detects** CSV vs Norma 43 by content.
- The same upload endpoint/button now handles both; the file input accepts `.csv` and `.n43`.
- Added `ImportServiceTest` (CSV + Norma 43, built from 80-char records). `php bin/phpunit` →
  **OK (8 tests, 25 assertions)**. Verified end to end with a sample `.n43` (4 movements imported).
  Documented in [guide 15](guide/15-norma43.md). **Phase 2 is complete.**

**Why**
- Norma 43 is what every Spanish bank exports; parsing it (and salaries/VAT correctly) is the domain
  depth that differentiates the project.

**Next**
- Phase 3 (cash-flow forecast, NL chat, open banking) or preparing the live deploy.

### 2026-07-02 — Entry 017: Continuous Integration (GitHub Actions)
**Done**
- Added `.github/workflows/ci.yml` with two parallel jobs: **backend** (PHP 8.4 → `composer install` →
  `php bin/phpunit`) and **frontend** (Node 24 → `npm ci` → `npm run build`).
- Added a CI status badge (+ stack badges) to the README.

**Why**
- Running the tests and build on every push prevents regressions from being merged, and the green badge
  is instant credibility for anyone browsing the repo.

**Next**
- A live deployment with a link and screenshots in the README.

### 2026-07-02 — Entry 016: Unit tests for the finance logic (PHPUnit)
**Done**
- Installed PHPUnit (`symfony/test-pack`).
- Added `VatServiceTest` and `IrpfServiceTest`: pure unit tests that build Transaction/Category objects in
  memory and feed them via a **stub** repository (no DB). Assert VAT (336/23/313), IRPF Q1 payment 230.64,
  salary excluded, and the next-deadline countdown.
- `php bin/phpunit` → **OK (6 tests, 14 assertions)**, no notices (used `createStub` per PHPUnit 13).
  Documented in [guide 13](guide/13-testing.md).

**Why**
- The tax logic is where a bug costs the most; testing it proves correctness, guards against regressions,
  and is a strong interview talking point.

**Next**
- CI (GitHub Actions running the tests) + a live deploy, or the Norma 43 importer.

### 2026-07-02 — Entry 015: Internationalization (ES/EN toggle)
**Done**
- Added a dependency-free i18n: a translations dictionary (en/es), a `LanguageContext` with `useTranslation()`
  (`{ lang, setLang, t }`), and an EN/ES toggle button in the navbar. Choice persists in localStorage.
- Replaced hardcoded UI strings across navbar, pages, charts and tax panels with `t('key')`; `t()`
  supports `{param}` placeholders (e.g. the IRPF deadline alert, quarter prefix Q/T).
- Default language Spanish; switches instantly. Documented in [guide 12](guide/12-i18n.md).

**Why**
- A bilingual UI signals international product thinking and showcases the author's ES/EN profile — a
  strong fit for remote/EU roles.

**Next**
- Norma 43 importer (finish Phase 2), then production concerns (tests, auth, deploy).

### 2026-07-02 — Entry 014: Visual redesign (design tokens, no "AI look")
**Done**
- Replaced the template CSS (purple accent) with a **design-token system** in `index.css`: surfaces, ink,
  one brand blue, semantic money colors, radii, shadows — plus a full **dark theme**.
- Refactored every component/page to **class names** reading the tokens (`.card`, `.btn`, `.table`,
  `.tag`, `.navbar`, `.stat-value`, `.alert-warn`…). Charts read `--chart-*` at runtime to follow the theme.
- Sober, financial styling (hairline borders, subtle shadows, tabular figures) — deliberately not the
  flashy AI-template look. Documented in [guide 11](guide/11-visual-redesign.md).

**Why**
- With all the functionality in place, a clean professional look is what turns "functional" into
  "impressive" for a recruiter.

**Next**
- Internationalization: an ES/EN language toggle.

### 2026-07-02 — Entry 013: Frontend split into pages + navbar (React Router)
**Done**
- Added React Router: a `Layout` (navbar + `<Outlet/>`) that loads data once via `useFinanceData()` and
  shares it with pages through the Outlet context.
- Split the single page into **Movements** (`/`), **Dashboard** (`/dashboard`) and **Taxes** (`/taxes`).
- Extracted shared helpers (`lib/format`, `components/Stat`). Verified all routes return 200 (SPA
  fallback) and the API proxy still works. Documented in [guide 10](guide/10-frontend-routing.md).

**Why**
- A real multi-page SPA structure is cleaner, scalable and what a recruiter expects — and it sets the
  stage for the visual redesign.

**Next**
- Visual redesign: a clean, professional look that doesn't look AI-generated.

### 2026-07-02 — Entry 012: IRPF estimate (modelo 130) + deadline alert
**Done**
- Added `IrpfService` (reuses `VatService::baseCents()`): cumulative 20% of year-to-date net
  (self-employment income − deductible expenses, without VAT), salary excluded, per-quarter with
  official deadlines and a "next deadline" countdown.
- Added `GET /api/irpf` and an IRPF panel (quarter table + a warning banner when a deadline is < 30 days).
- Verified: Q1 net 1153.21 → payment 230.64; next deadline Q2 2026-07-20 (18 days). Documented in
  [guide 09](guide/09-irpf.md).

**Why**
- IRPF + VAT together tell a complete tax story — the core value for a Spanish freelancer and the
  clearest demonstration of the finance moat.

**Next**
- Norma 43 import, then the frontend redesign.

### 2026-07-02 — Entry 011: VAT panel (Phase 2 begins)
**Done**
- Added `VatService` (category → Spanish VAT-rate map, cents-based VAT extraction) and `GET /api/vat`.
- Added a VAT summary panel in React: output VAT, input VAT and net (to pay / to reclaim).
- Verified on the sample data: output 336.00, input 23.00, net 313.00 to pay — salary and autónomo fee
  correctly carry no VAT. Documented in [guide 08](guide/08-vat-panel.md).

**Why**
- This is the finance moat: computing VAT correctly (right rates per category, salaries exempt, exact
  cents) is exactly the domain knowledge that differentiates this project.

**Next**
- IRPF estimate (modelo 130) and quarterly deadline alerts.

### 2026-07-02 — Entry 010: Dashboard with charts
**Done**
- Added `GET /api/stats` (raw SQL aggregation: totals, spending by category, income/expenses by month).
- Added a `Dashboard` React component (Recharts): a single-hue horizontal bar for category spending and a
  two-series bar for monthly income vs expenses. Colors chosen by the data-viz method and **validated**
  with the skill's script (CVD ΔE 74.6). Charts refresh on import/categorize.
- Documented in [guide 07](guide/07-dashboard.md).

**Why**
- This is the most "sellable" screen: at a glance you see where the money goes — the payoff of the
  import + categorization work.

**Next**
- Phase 2: the VAT panel (output vs input VAT, net due) — the finance moat.

### 2026-07-02 — Entry 009: AI categorization (with rule fallback)
**Done**
- Added `CategorizerService`: a fixed category list, a deterministic Spanish keyword rule engine, and an
  optional Claude call (via HttpClient) that is validated against the allowed list.
- Added `POST /api/transactions/categorize` and a "🧠 Categorize" button + Category column in React.
- Verified with no API key: 8/8 categorized correctly by rules (Mercadona→Supermercado,
  Repsol→Combustible, Nómina→Nómina, etc.). Documented in [guide 06](guide/06-ai-categorization.md).

**Why**
- Categorization is what turns a raw list into insight. Keeping AI optional (rule fallback) means the app
  always works and stays honest — a deliberate architecture principle.

**Next**
- Dashboard: spending by category and by month (Recharts), then the VAT panel (Phase 2).

### 2026-07-02 — Entry 008: List transactions (visible end-to-end loop)
**Done**
- Added `GET /api/transactions` (maps entities to a plain DTO array, ordered by date).
- Rebuilt the React `App.jsx`: a transactions table (euro formatting, red/green by sign), an
  income/expenses/balance summary, and a file input that uploads a CSV (FormData) and refreshes.
- Verified: the endpoint returns 8 rows through the Vite proxy and the frontend builds cleanly.
  Documented in [guide 05](guide/05-list-transactions.md).

**Why**
- First fully visible loop: upload a CSV → see your movements. It turns the backend work into something
  a person (or a recruiter) can actually see.

**Next**
- AI categorization: assign a category to each transaction with Claude + a rule-based fallback.

### 2026-07-02 — Entry 007: CSV import
**Done**
- Added `ImportService` (parses a bank CSV) and `POST /api/import/csv` (thin controller).
- The parser auto-detects the `,`/`;` separator, maps bilingual columns, and handles the Spanish
  number format (`1.234,56`) — keeping money as an exact decimal string.
- Added a sample file and verified end to end: imported 8 rows (balance 3316.21), 0 errors, accents
  preserved. Documented in [guide 04](guide/04-csv-import.md).

**Why**
- This is the first tangible value: a real bank export becomes structured data. Keeping the finance
  parsing correct (Spanish decimals, no float) is the differentiator.

**Next**
- Expose the transactions via an API endpoint and render them in the React frontend.

### 2026-07-02 — Entry 006: Database + domain model (Phase 1 begins)
**Done**
- Installed Doctrine (`symfony/orm-pack`) and MakerBundle.
- Started local PostgreSQL (`pg_ctl`), configured `DATABASE_URL` in `.env.local`, created the `cuentia`
  database.
- Wrote the `Category` and `Transaction` entities (+ repositories); generated and ran the first
  migration. Tables `category` and `transaction` now exist.
- Documented it in [guide 03](guide/03-database-and-entities.md). Decided to use auto-increment
  integer IDs for now (simpler than UUID); updated ARCHITECTURE accordingly.

**Why**
- The entities are the backbone of the app. Getting money right from day one (`decimal`, not float)
  avoids a whole class of accounting bugs.

**Next**
- CSV import: an endpoint that parses a bank CSV and stores `Transaction` rows (guide 04).

### 2026-07-02 — Entry 005: React frontend + Phase 0 complete
**Done**
- Scaffolded the React app (Vite) in `frontend/`; added a dev **proxy** (`/api` → `:8000`) to avoid CORS.
- `App.jsx` calls `GET /api/health` on mount and shows "✅ API OK".
- Verified end to end: with both servers running, `http://localhost:5173/api/health` is proxied to the
  backend and returns the JSON. **Phase 0 is complete.**
- Documented it in [guide 02](guide/02-frontend-scaffold.md), including the real `localhost` vs
  `127.0.0.1` (IPv6) gotcha we hit.

**Why**
- Proving the frontend↔backend link now, on the simplest possible feature, means every later feature
  is built on a foundation we know works.

**Next**
- Phase 1: domain model (`Transaction`, `Category`), PostgreSQL via Doctrine, and CSV import (guide 03).

### 2026-07-01 — Entry 004: Backend scaffold + first green
**Done**
- Scaffolded the Symfony 8.1 API in `backend/` (`symfony new backend`); removed its nested `.git`
  to keep a single monorepo.
- Added `HealthController` with `GET /api/health`; verified it returns
  `{"status":"ok","service":"cuentia-api"}` via a local server.
- Documented the whole thing in the teaching [`guide/`](guide/) (00-environment, 01-backend-scaffold).

**Why**
- A health endpoint is the smallest possible proof that the request→controller→response chain works —
  the right first milestone before adding a database or features.

**Next**
- Scaffold the React frontend (Vite) and have it call `/api/health` (guide 02).

### 2026-07-01 — Entry 003: Repo published + toolchain installed
**Done**
- Created and pushed the public repo: **github.com/R0b3r7DEV/cuentia** (first commit = docs).
- Installed the backend toolchain via Scoop: **PHP 8.5.7**, **Composer 2.10.2**,
  **Symfony CLI 5.17.1**, **PostgreSQL 18.4** (cluster initialized; local `trust` auth).
- Created a `php.ini` enabling the extensions Symfony needs: `pdo_pgsql`, `pgsql`, `intl`,
  `mbstring`, `openssl`, `curl`, `fileinfo`, `sodium`, `zip`.

**Why**
- Building in public from commit 1 tells a strong story; a fully working local toolchain unblocks Phase 0.

**Next**
- Scaffold the Symfony API and the React app; first green `GET /api/health` → 200.

### 2026-07-01 — Entry 002: Bilingual documentation
**Done:** made all docs bilingual (English first, Spanish below).
**Why:** the author uses the docs in Spanish while learning, and recruiters read the English.
**Next:** same as entry 001 — install tooling and scaffold.

### 2026-07-01 — Entry 001: Project decided, foundations laid
**Done**
- Chose the project and stack (see [ADR 0002](decisions/0002-project-and-stack.md)):
  *Cuentia*, an AI cash-flow & tax copilot, built on **Symfony + React + PostgreSQL + Claude**.
- Created the documentation skeleton: README, ROADMAP, ARCHITECTURE, ADR 0001, ADR 0002, this dev log.
- Defined the initial domain model (`Transaction`, `Category`) and the phased roadmap.

**Why**
- Starting with the *why* written down (ADRs) and a phased plan keeps the build focused and makes
  every decision defensible later — which is the whole point of this project.

**Next**
- Install backend tooling: PHP, Composer, Symfony CLI, PostgreSQL (Phase 0).
- Scaffold the Symfony API and the React app; get a `GET /api/health` returning 200.

---

## Español

### 2026-07-08 — Entrada 040: Cerrar el círculo — diseño → CIE y diseño → presupuesto
**Hecho**
- Enganchado el diseñador de instalación con el resto del módulo de facturación (solo frontend). Desde un
  diseño calculado, dos botones: **«Crear certificado (CIE)»** prellena el formulario de Certificados con
  los datos técnicos (potencia→kW, tensión, suministro, tierra TT, circuitos, IGA 25/40 A, diferencial 30
  mA); **«Materiales → presupuesto»** abre el formulario de Presupuestos con una línea por material.
  Añadido el paso de datos entre pestañas en `BillingPage` (una pestaña entrega datos a otra; al pulsar una
  pestaña manualmente se limpia). Sin placeholders: el usuario completa cliente/identidad donde toca.

**Por qué**
- Es el diferencial que venía señalando: **diseño → CIE → presupuesto → factura** en una sola herramienta,
  cada paso alimentando el siguiente. Los datos que el electricista introdujo una vez fluyen por toda la
  cadena.

**Siguiente**
- Fase 2 (plano 2D) / Fase 3 (3D) del diseñador. La IA agéntica + OCR siguen necesitando una key.

### 2026-07-08 — Entrada 039: Diseñador de instalación — Fase 1 (calculadora ITC-BT-25 + esquema unifilar)
**Hecho**
- Nueva pestaña **Instalación**: una calculadora del REBT **ITC-BT-25**. `InstallationCalculator` (puro,
  con tests) convierte estancias + cargas previstas en circuitos (C1–C12 con sección/PIA, desdoblando en
  C6/C7 al superar los límites de puntos), puntos mínimos por estancia, grado y potencia a contratar,
  diferenciales, lista de materiales y estimación de cable. `InstallationController` expone un `/compute` sin
  estado más CRUD para guardar diseños (entidad `Installation`, columnas JSON; el resultado se recalcula
  desde la entrada guardada). El frontend recalcula en vivo y dibuja un **esquema unifilar (SVG)** + tablas
  de circuitos/puntos/materiales. Migración añadida. Suite: **52 tests, 233 aserciones**.
- Basado en la ITC-BT-25 (5 circuitos básico, 9+ elevado; un diferencial por cada 5 circuitos; puntos
  mínimos por estancia). Enfoque honesto: ayuda de predimensionado; el cable es estimación; no es proyecto
  firmado.

**Por qué**
- Aquí empieza la cadena diseño → CIE → presupuesto → factura del electricista. Es la Fase 1 del diseñador
  (enfoque B); Fase 2 = editor 2D de planta, Fase 3 = vista 3D extruida.

**Siguiente**
- Fase 2 (plano 2D), autorrellenar el CIE desde un diseño, materiales → presupuesto. La IA agéntica + OCR
  siguen necesitando una key.

### 2026-07-08 — Entrada 038: CIE — presentable de verdad (listo para firmar + AutoFirma)
**Hecho**
- Llevado el CIE de "borrador" a **listo para firmar**. Investigada la tramitación telemática de la GVA: los
  documentos deben ir en **PDF firmado digitalmente** (DNIe / FNMT / ACCV / Cl@ve-firma) y los presenta el
  instalador habilitado en la sede — **no hay API de terceros**. Elegido el camino seguro (contigo): Cuentia
  nunca maneja el certificado del instalador.
- Mejorado `CiePdf` a un documento CERTINS E completo: declaración de conformidad íntegra (RD 842/2002 +
  ITC-BT, mediciones favorables), *lugar y fecha* y **áreas reservadas de firma electrónica** para empresa e
  instalador ("firma electrónica AutoFirma/ACCV"). Añadido un panel in-app **«Cómo firmarlo y presentarlo»**
  (descargar → firmar con AutoFirma/ACCV → presentar en la sede de la GVA) con enlaces oficiales.

**Por qué**
- "Presentable de verdad" = documento fiel + firma real + presentación. La firma la hace el instalador en
  local (la clave privada nunca sale de su equipo, como recomienda la GVA); la presentación no tiene API
  pública, así que sigue siendo su subida manual — el mismo límite honesto que el envío real a la AEAT de
  Verifactu.

**Siguiente**
- IA agéntica + OCR — ambas necesitan una API key de Anthropic.

### 2026-07-08 — Entrada 037: Certificado de Instalación Eléctrica (CIE / CERTINS E)
**Hecho**
- Investigado el CIE de la Comunitat Valenciana y confirmado un modelo oficial — **CERTINS E (12/2012)**,
  que se presenta telemáticamente con firma digital en la sede de la GVA. Implementada una entidad
  **Certificate** + CRUD `CertificateController` + `CiePdf` (PDF con estructura CERTINS E vía Dompdf) + una
  pestaña **Certificados** en Facturación. Los campos siguen el REBT (RD 842/2002): instalación, titular,
  empresa instaladora/instalador y características técnicas (potencia, tensión, suministro, tierra, IGA,
  diferencial, etc.). Migración añadida.
- Enfoque honesto (nota del formulario + pie del PDF): es un **borrador de ayuda**, no la presentación
  oficial telemática y firmada. Tests: CRUD, validación, PDF, aislamiento por usuario. Suite: **45 tests,
  192 aserciones**. Guía 32. (Corregidos dos bugs que cazaron los tests: lectura de clave indefinida en un
  `installationType` sin definir, y usar null como índice de array en el PDF.)

**Por qué**
- Un plus real para un electricista que use la app: el CIE es el documento que emite en cada trabajo, y
  reutilizar los datos que ya guarda para facturar elimina reescribir.

**Siguiente**
- IA agéntica + OCR — ambas necesitan una API key de Anthropic.

### 2026-07-08 — Entrada 036: Presupuestos + convertir en factura
**Hecho**
- Añadidos los **presupuestos**: entidades `Quote`/`QuoteLine` (no fiscales, sin cadena de hash),
  `QuoteService`, `QuoteController` (`list/create/get/status/convert/pdf`), `QuotePdf`, migración, y una
  pestaña **Presupuestos** (crear con cliente + líneas del catálogo + *válido hasta*, insignia de estado,
  acciones por fila). Lo principal: **convertir** copia el presupuesto a `InvoiceService::create()` para
  producir una factura Verifactu completamente sellada — **idempotente** (se convierte una vez, sin
  duplicar), y marca el presupuesto como `converted` enlazándolo.
- Tests: `QuoteServiceTest` (totales/numeración/serie por defecto/borrador/guarda de líneas vacías) + un
  test de integración de crear → estado → convertir → factura sellada → idempotente → PDF. Suite: **44
  tests, 179 aserciones**. Guía 31. Corregido un bug de creación donde un `status` sin definir releía una
  clave inexistente y dejaba el estado en blanco.

**Por qué**
- Los autónomos presupuestan antes de facturar; convertir el presupuesto aceptado en un clic (en una
  factura conforme) es el flujo natural y mantiene la cadena fiscal separada de las ofertas no fiscales.

**Siguiente**
- IA agéntica + OCR — ambas necesitan una API key de Anthropic.

### 2026-07-08 — Entrada 035: Catálogo de servicios (líneas reutilizables)
**Hecho**
- Añadido un **catálogo de servicios/productos** reutilizable: entidad `Service` (nombre/precio/IVA acotado
  al usuario), CRUD `ServiceController` (`/api/services`), migración `Version20260708075806`, y una pestaña
  **Servicios**. El formulario de factura gana un desplegable **«Añadir del catálogo…»** que añade una línea
  prellenada del servicio elegido (editable). Suite: **40 tests, 157 aserciones** (CRUD + validación +
  aislamiento).

**Por qué**
- Los autónomos facturan los mismos servicios una y otra vez; un catálogo elimina el reescribir. Las líneas
  copian los valores del servicio al crearse, así que borrar un elemento nunca reescribe facturas pasadas.

**Siguiente**
- Presupuestos — documentos no fiscales que se convierten en factura Verifactu real.

### 2026-07-08 — Entrada 034: Pestañas de facturación + gestión de clientes (CRUD)
**Hecho**
- Convertida la pantalla de Facturas en un apartado de **Facturación** con sub-pestañas (`BillingPage` →
  `Facturas`, `Clientes`; llegan `Presupuestos`/`Servicios`). Añadido **CRUD de clientes**:
  `CustomerController` (`GET/POST/PUT/DELETE /api/customers`, acotado al usuario) con **guarda de borrado**
  (409 si el cliente tiene facturas) y `name`/`taxId` obligatorios. Nueva pestaña `Clientes` (listar +
  crear/editar/borrar). Emitir una factura puede reutilizar un cliente existente por **`customerId`**
  (desplegable en el formulario); `InvoiceService` resuelve id → busca-o-crea.
- Tests: un test de integración que cubre crear/listar/actualizar, validación (400), la guarda de borrado
  (409) y el aislamiento por usuario. Suite: **39 tests, 146 aserciones**. Guía 29.

**Por qué**
- Es el paso de "emitir una factura suelta" hacia una herramienta de facturación real: clientes que
  reutilizas, un apartado que albergará presupuestos y un catálogo de servicios a continuación.

**Siguiente**
- Un catálogo de servicios para rellenar líneas, y luego presupuestos con convertir-en-factura.

### 2026-07-08 — Entrada 033: PDF de factura (con QR incrustado)
**Hecho**
- Cada factura se puede descargar ahora como **PDF profesional** (servicio `InvoicePdf` con **Dompdf**, PHP
  puro): emisor/cliente, tabla de líneas, totales, la **huella** Verifactu y el **QR** incrustado como
  `data:` URI en SVG. `GET /api/invoices/{id}/pdf`; la página de facturas muestra un enlace **Descargar
  PDF** junto al de XML. Guía 28. Suite: **38 tests, 134 aserciones** (render unitario + endpoint de
  integración).
- Además, **README** actualizado para mostrar la facturación Verifactu y la banca abierta, con un recuadro
  de ingeniería sobre la cadena de hash y una sección de alcance reescrita con honestidad.

**Por qué**
- El QR/XML eran artefactos de desarrollador; un PDF es el documento que un autónomo envía de verdad. Dompdf
  lo mantiene libre de gd/imagick, y DejaVu Sans renderiza el € y los acentos.

**Siguiente**
- IA agéntica + OCR — ambas necesitan una API key de Anthropic.

### 2026-07-06 — Entrada 032: Banca abierta (GoCardless) — tras un flag de función
**Hecho**
- Construida una importación real de banca abierta vía **GoCardless Bank Account Data** (PSD2):
  `GoCardlessClient` (token → instituciones → requisition → transacciones de cuenta), `OpenBankingService`
  (conectar + importar con mapeo) y `BankController` (`/api/bank/status|institutions|connect|import`). Los
  movimientos llevan ahora un `externalId` (migración `Version20260706103931`) para que las reimportaciones
  **salten duplicados**. Componente frontend `BankConnect` en la página de Movimientos: elige banco →
  autoriza (enlace alojado de GoCardless) → importa.
- **Flag de función:** deshabilitada salvo que estén `GOCARDLESS_SECRET_ID`/`_KEY`; entonces la UI muestra un
  aviso honesto de "no configurada" y los endpoints que la requieren devuelven 503.
- Tests: `GoCardlessClientTest` (MockHttpClient), `OpenBankingServiceTest` (mapeo, fallback al nombre del
  acreedor, conteos de dedup), test de integración de la ruta deshabilitada. Suite: **36 tests, 128
  aserciones**.

**Por qué**
- Crear credenciales de aplicación de GoCardless pide más datos personales de los que tienen sentido para un
  portfolio, así que la integración está construida y **testeada contra la forma documentada de la API pero
  no ejecutada en vivo** — publicarla tras un flag es la forma honesta de mostrar la capacidad sin fingir que
  se ha probado de extremo a extremo.

**Siguiente**
- IA agéntica + OCR — ambas necesitan una API key de Anthropic.

### 2026-07-06 — Entrada 031: Facturación Verifactu — Fase C (QR + XML)
**Hecho**
- Cada factura tiene ya sus dos artefactos Verifactu. `VerifactuQr` construye la URL `ValidarQR` de la AEAT
  (nif, numserie, fecha, importe) y la renderiza como **SVG** con `endroid/qr-code` (el SVG no necesita
  gd/imagick); `VerifactuXml` serializa un XML `RegistroAlta` con `DOMDocument`. Endpoints
  `GET /api/invoices/{id}/qr` (image/svg+xml) y `/xml` (descarga). La página de facturas muestra el QR
  escaneable + un enlace de descarga del XML en el detalle desplegado, con una nota de que es una
  demostración fiel (host de pruebas), no un envío real.
- Tests: `VerifactuDocumentsTest` (campos de la URL del QR, render SVG, XML bien formado + con la huella,
  primer registro vs. encadenado) + cobertura de integración de ambos endpoints. Suite: **28 tests, 106
  aserciones**.

**Por qué**
- SVG en vez de PNG mantiene la imagen Docker y el CI libres de la extensión `gd`/`imagick`. El QR y el XML
  siguen el formato de la AEAT fielmente pero apuntan al host de pruebas — Cuentia no es un emisor
  registrado, así que el envío SOAP real queda en la Fase D (fuera de alcance), como dice el ADR.

**Siguiente**
- Banca abierta (GoCardless Bank Account Data) — importación real de movimientos bancarios.

### 2026-07-06 — Entrada 030: Página de facturas — Verifactu visible en la interfaz
**Hecho**
- Nueva página **Facturas** (React): emitir una factura (cliente + líneas dinámicas con previsualización
  del total en cliente), ver la lista, y desplegar cualquier fila para revelar su **huella Verifactu**
  (hash, el registro con el que encadena, sello temporal). Un botón **«Verificar cadena»** llama a
  `/api/invoices/verify` y muestra una insignia verde «🔒 Cadena íntegra · N registros verificados» (o una
  ámbar «rota en …»). Añadidos la ruta, el enlace del navbar y los textos ES/EN. Build del frontend en
  verde (604 módulos).

**Por qué**
- El motor Verifactu (fases A–B) vivía solo en la API y era invisible para quien abriera la app en vivo.
  Esta página convierte la cadena inalterable en algo que un visitante puede *ver y operar* — el sentido de
  la funcionalidad para un portfolio.

**Siguiente**
- Fase C: el QR de la factura (a la AEAT) y la exportación XML, integrados en esta misma página.

### 2026-07-06 — Entrada 029: Facturación Verifactu — Fase B (cadena de hash inalterable)
**Hecho**
- Cada factura emitida genera ahora un `InvoiceRecord` (registro de alta Verifactu) con una **huella
  SHA-256 encadenada al registro anterior** — el mecanismo antifraude central. Añadidos `VerifactuHasher`
  (cadena canónica + hash), `VerifactuChain` (verificador de integridad), la entidad + repositorio del
  registro, la generación enganchada en `InvoiceService`, y un endpoint `GET /api/invoices/verify`; el
  detalle de factura devuelve ahora su bloque `verifactu` (hash, previousHash, generatedAt). Añadido un
  `taxId` (NIF) al `User` como emisor de la huella. Migración `Version20260706083619`.
- Tests: `VerifactuChainTest` (determinismo, encadenado, detección de manipulación — tanto un campo alterado
  como un registro reesellado pero desenganchado) + un test de integración de extremo a extremo (dos
  facturas → `/verify` ok, count 2, encadenado, aislamiento por usuario). Suite: **24 tests, 86 aserciones**.

**Por qué**
- Un test de campo alterado falló *solo en SQLite*: un `NUMERIC` `1210.00` se releía como `1210`, así que
  la huella recalculada tras el viaje a la BD no coincidía con la sellada (PostgreSQL devuelve `1210.00` y
  lo ocultaba en la prueba en vivo). Lección aprendida: un hash criptográfico nunca debe depender de cómo
  formatee un valor la base de datos — los importes se normalizan a dos decimales en la cadena canónica.

**Siguiente**
- Fase C: el QR de la factura (a la AEAT) y la exportación XML.

### 2026-07-06 — Entrada 028: Facturación Verifactu — Fase A (modelo de dominio)
**Hecho**
- Añadido el dominio de facturación: entidades `Customer`, `Invoice` e `InvoiceLine` (todas ligadas a un
  `User`), sus repositorios, un `InvoiceService` y un `InvoiceController` (`GET`/`POST /api/invoices`,
  `GET /api/invoices/{id}`). La migración `Version20260706075837` crea las tres tablas.
- Los totales se calculan en **céntimos enteros** (nunca floats) y los números de factura son
  **correlativos por serie** (`nextNumber = MAX(number)+1`) — ambos requisitos previos a la cadena de hash
  Verifactu.
- Escrito el [ADR 0003](decisions/0003-verifactu-invoicing.md) (alcance A→D + nota honesta de
  monetización) y la [guía 24](guide/24-verifactu-invoicing.md). Añadido `InvoiceServiceTest`
  (totales/IVA/numeración + guardia de líneas vacías): la suite ya tiene **18 tests, 59 aserciones**.
  Prueba en vivo: una línea al 21 % + dos al 10 % → base 1100.00, IVA 220.00, total 1320.00, número 2026/1.

**Por qué**
- Verifactu (Orden HAC/1177/2024) será obligatorio en 2026; adelantarse lo convierte en un escaparate
  creíble. El modelo de dominio tiene que estar bien — totales exactos al céntimo y numeración sin huecos —
  antes de poner encima la cadena de hash inalterable.

**Siguiente**
- Fase B: el `InvoiceRecord` con el hash SHA-256 encadenado y tests de detección de manipulación.

### 2026-07-03 — Entrada 027: Hosting gratis — Render + Neon (caducó el trial de Railway)
**Hecho**
- Cambiado el objetivo de despliegue a planes gratuitos: **Render** (servicio web Docker) para el backend y
  **Neon** (PostgreSQL que no se pausa) para la base de datos; Vercel igual. Añadido `render.yaml` (blueprint).
- **Corregido un fallo del Dockerfile**: el `$PORT` de Apache se sustituía en *build* (quedaría vacío);
  ahora se fija al arrancar el contenedor (`${PORT:-8080}`), así funciona en Render/Koyeb/Fly/Railway.
- Actualizada la guía de despliegue (Render + Neon, nota de arranque en frío, alternativa Koyeb).

**Por qué**
- Caducó el trial de Railway y pagar no es opción; como el Dockerfile es agnóstico del host, solo cambian
  las instrucciones de hosting, no la app.

**Siguiente**
- Desplegar en Render + Neon, y luego añadir la URL + capturas al README y fijar el repo.

### 2026-07-03 — Entrada 026: Configuración de despliegue (Docker + Vercel)
**Hecho**
- Añadido `backend/Dockerfile` (Apache + PHP 8.4, ejecuta migraciones al arrancar), `public/.htaccess`
  (apache-pack), una config `when@prod` (proxies de confianza + cookies seguras) y `frontend/vercel.json`
  (rutas SPA + proxy de `/api` al backend — sin CORS y cookies de sesión de primera parte).
- Escrita una guía de despliegue completa ([guía 23](guide/23-deploy.md)): PostgreSQL (Supabase/Railway),
  backend en Railway con variables, `app:create-user` para el admin, frontend en Vercel, verificación y
  resolución de problemas.
- Tests siguen en verde (16), la app en dev sin cambios.

**Por qué**
- El proxy frontend→backend evita el clásico dolor de cookies cross-domain/CORS y mantiene todo simple. La
  config está lista y documentada; el despliegue real se hace desde las cuentas del usuario.

**Siguiente**
- Publicar (crear cuentas, desplegar), y luego añadir la URL + capturas al README y fijar el repo.

### 2026-07-03 — Entrada 025: Tests de integración de la API
**Hecho**
- Añadido `tests/Api/ApiIntegrationTest` (WebTestCase): arranca el kernel/firewall/BD reales y prueba
  register/login/me/logout, 401 sin auth, email duplicado 409, **aislamiento por usuario** (A importa 2, B
  ve 0), y limpiar/borrar cuenta.
- Corre sobre **SQLite en memoria** (`.env.test`) con `disableReboot()` — sin servicio de BD; CI instala
  `pdo_sqlite`.
- Cazó dos errores reales: `transaction` es palabra reservada en SQLite (resuelto entrecomillando el nombre
  de la tabla) y el bloqueo de fichero SQLite en Windows (resuelto con la BD en memoria). `php bin/phpunit`
  → **OK (16 tests, 52 assertions)**. Documentado en la [guía 22](guide/22-integration-tests.md).

**Por qué**
- Los tests de integración demuestran que toda la pila está bien cableada — la prueba más fuerte, para un
  entrevistador, de que la app funciona de verdad.

**Siguiente**
- El despliegue en vivo.

### 2026-07-03 — Entrada 024: Pulido UX — rediseño liquid glass + responsive
**Hecho**
- Rehecho `index.css` como un sistema "liquid glass" (inspirado en iOS/WhatsApp): tarjetas translúcidas
  esmeriladas y una navbar flotante redondeada (`backdrop-filter`), botones/inputs de píldora, fondo con
  tinte, y burbujas de chat estilo WhatsApp (burbuja de usuario en color, radios asimétricos). Tokens claro
  + oscuro.
- Responsive: la navbar se envuelve, los gráficos se apilan, las tablas hacen scroll horizontal
  (`.table-scroll`) en móvil.
- Todo vía tokens/clases — componentes sin cambios. Compila. Documentado en la [guía 21](guide/21-ux-glass.md).

**Por qué**
- Un acabado moderno y coherente en claro/oscuro y escritorio/móvil es lo que hace que el proyecto
  *se sienta* profesional de un vistazo.

**Siguiente**
- Tests de integración de la API, y luego deploy.

### 2026-07-03 — Entrada 023: Cuenta y RGPD (limpiar datos / borrar cuenta)
**Hecho**
- Añadido `AccountController`: `POST /api/account/clear` (borra los movimientos del usuario) y
  `DELETE /api/account` (borra cuenta + datos, invalida la sesión — derecho al olvido).
- Añadida una página de Cuenta (`/account`, vía el email en la navbar) con limpiar datos, borrar cuenta
  (peligro) y una nota de privacidad; `AuthContext.deleteAccount()`. ES/EN.
- Verificado: limpiar 15→0; borrar cuenta → sesión invalidada. Documentado en la [guía 20](guide/20-account-gdpr.md).

**Por qué**
- Las cuentas reales necesitan una salida (RGPD), y "limpiar mis datos" es un reinicio cómodo para la demo
  en vivo — ambos baratos gracias al aislamiento de datos por usuario.

**Siguiente**
- Pulido UX y responsive, tests de integración de la API, y luego deploy.

### 2026-07-03 — Entrada 022: Datos de ejemplo + CLI de usuario/admin (pulido)
**Hecho**
- Añadido `POST /api/demo/load`: carga 2 meses de movimientos de ejemplo realistas para el usuario actual
  (solo si está vacío) reutilizando el pipeline real de import + categorización, para que una cuenta nueva
  nunca esté vacía.
- Añadido un botón "Cargar datos de ejemplo" en el estado vacío de Movimientos (ES/EN).
- Añadido el comando de consola `app:create-user` (con `--admin`) para crear cuentas/admins en un deploy.
- Verificado: creado un admin; un usuario nuevo carga 15 movimientos (2 meses, 8 categorías, IVA calculado).
  Documentado en la [guía 19](guide/19-demo-and-admin.md).

**Por qué**
- Para publicar: lo que más mata una demo es una pantalla vacía. Los datos de ejemplo (por el pipeline
  real) hacen la app explorable al instante; el CLI siembra cuentas de servidor con seguridad.

**Siguiente**
- Cuenta y GDPR (borrar/limpiar), pulido UX y responsive, tests de integración de la API, y luego deploy.

### 2026-07-03 — Entrada 021: Autenticación y multiusuario (Fase 4)
**Hecho**
- Añadida entidad `User` + security-bundle de Symfony: proveedor de entidad, `json_login` con sesión,
  logout, control de acceso. Endpoints: `POST /api/register`, `/api/login`, `/api/logout`, `GET /api/me`.
- Todo acotado al usuario actual: `Transaction` pertenece a un `User`; las importaciones lo asignan;
  `TransactionRepository::findForUser()`; cada servicio y el SQL de stats filtran por usuario (vía
  `#[CurrentUser]`).
- Frontend: `AuthContext` + `AuthPage` de login/registro; `App` protege toda la app; la navbar muestra el
  email + salir.
- Actualizados todos los tests unitarios a las nuevas firmas. `php bin/phpunit` → **OK (11 tests, 37
  assertions)**. Verificado end-to-end con cookies de sesión (registro → login → datos acotados → 401 sin
  sesión). Documentado en la [guía 18](guide/18-auth.md).

**Por qué**
- El aislamiento de datos multiusuario es lo que convierte esto de una demo en un SaaS real — e imponerlo
  en la capa de consulta es la forma correcta y segura.

**Siguiente**
- Endurecimiento de producción y el deploy en vivo.

### 2026-07-02 — Entrada 020: Asistente en lenguaje natural (IA + fallback)
**Hecho**
- Añadido `ChatService`: construye un contexto factual (balance, ingresos/gastos, categorías top, IVA,
  próximo pago de IRPF) y le pide a Claude que responda usando solo eso; **fallback** determinista de
  resumen sin clave.
- Añadido `POST /api/chat` y una página **Asistente** (`/chat`) con UI de chat; añadido el enlace en la
  navbar y traducciones ES/EN.
- Añadido `ChatServiceTest` (ruta de fallback). `php bin/phpunit` → **OK (11 tests, 37 assertions)**.
  Documentado en la [guía 17](guide/17-chat.md).

**Por qué**
- El Q&A en lenguaje natural anclado en los datos del propio usuario es la función IA estrella — y el
  fallback la mantiene honesta y siempre utilizable.

**Siguiente**
- Fase 4: autenticación/multiusuario y luego el deploy en vivo.

### 2026-07-02 — Entrada 019: Previsión de tesorería (empieza la Fase 3)
**Hecho**
- Añadido `ForecastService` (proyección lineal del saldo a +30/60/90 días según el neto diario medio) y
  `GET /api/forecast`.
- Añadido un gráfico de líneas `Forecast` en el Dashboard (saldo actual + neto mensual medio + nota de
  "estimación"). Conectado `/api/forecast` a `useFinanceData`.
- Añadido `ForecastServiceTest`. `php bin/phpunit` → **OK (10 tests, 33 assertions)**. Documentado en la
  [guía 16](guide/16-forecast.md).

**Por qué**
- Una cifra a futuro es lo que de verdad preocupa a un autónomo ("¿llegaré?"), y un modelo transparente y
  claramente etiquetado lo mantiene honesto.

**Siguiente**
- Preguntas en lenguaje natural sobre los movimientos (IA), y luego auth + deploy.

### 2026-07-02 — Entrada 018: Importador Norma 43 (Fase 2 completa)
**Hecho**
- Añadido `ImportService::importNorma43()` — parsea el formato de ancho fijo Cuaderno 43 de la AEB
  (registro 22 = movimiento, 23 = concepto), y un despachador `import()` que **auto-detecta** CSV vs
  Norma 43 por el contenido.
- El mismo endpoint/botón de subida maneja ambos; el input acepta `.csv` y `.n43`.
- Añadido `ImportServiceTest` (CSV + Norma 43, con registros de 80 caracteres). `php bin/phpunit` →
  **OK (8 tests, 25 assertions)**. Verificado end-to-end con un `.n43` de ejemplo (4 movimientos).
  Documentado en la [guía 15](guide/15-norma43.md). **La Fase 2 está completa.**

**Por qué**
- Norma 43 es lo que exporta cualquier banco español; parsearlo (y nóminas/IVA correctamente) es la
  profundidad de dominio que diferencia el proyecto.

**Siguiente**
- Fase 3 (previsión de tesorería, chat en lenguaje natural, open banking) o preparar el deploy en vivo.

### 2026-07-02 — Entrada 017: Integración continua (GitHub Actions)
**Hecho**
- Añadido `.github/workflows/ci.yml` con dos jobs en paralelo: **backend** (PHP 8.4 → `composer install`
  → `php bin/phpunit`) y **frontend** (Node 24 → `npm ci` → `npm run build`).
- Añadida una insignia de estado de CI (+ badges de stack) al README.

**Por qué**
- Ejecutar los tests y el build en cada push evita que se fusionen regresiones, y la insignia verde da
  credibilidad instantánea a quien navegue el repo.

**Siguiente**
- Un despliegue en vivo con enlace y capturas en el README.

### 2026-07-02 — Entrada 016: Tests unitarios de la lógica fiscal (PHPUnit)
**Hecho**
- Instalado PHPUnit (`symfony/test-pack`).
- Añadidos `VatServiceTest` e `IrpfServiceTest`: tests unitarios puros que construyen objetos
  Transaction/Category en memoria y los pasan por un **stub** del repositorio (sin BD). Comprueban el IVA
  (336/23/313), el pago del T1 de IRPF 230,64, la nómina excluida y la cuenta atrás del vencimiento.
- `php bin/phpunit` → **OK (6 tests, 14 assertions)**, sin avisos (usé `createStub`, como recomienda
  PHPUnit 13). Documentado en la [guía 13](guide/13-testing.md).

**Por qué**
- La lógica fiscal es donde más cuesta un error; testearla demuestra corrección, protege de regresiones y
  es un gran argumento en una entrevista.

**Siguiente**
- CI (GitHub Actions ejecutando los tests) + un deploy en vivo, o el importador Norma 43.

### 2026-07-02 — Entrada 015: Internacionalización (botón ES/EN)
**Hecho**
- Añadido un i18n sin dependencias: diccionario de traducciones (en/es), un `LanguageContext` con
  `useTranslation()` (`{ lang, setLang, t }`), y un botón EN/ES en la navbar. La elección persiste en
  localStorage.
- Sustituidos los textos fijos de la interfaz (navbar, páginas, gráficos, paneles fiscales) por
  `t('clave')`; `t()` admite marcadores `{param}` (p.ej. el aviso de vencimiento del IRPF, prefijo T/Q).
- Idioma por defecto español; cambia al instante. Documentado en la [guía 12](guide/12-i18n.md).

**Por qué**
- Una interfaz bilingüe demuestra pensamiento de producto internacional y muestra el perfil ES/EN del
  autor — encaja con puestos remotos/UE.

**Siguiente**
- Importador Norma 43 (cerrar la Fase 2) y luego temas de producción (tests, auth, deploy).

### 2026-07-02 — Entrada 014: Rediseño visual (tokens de diseño, sin "look de IA")
**Hecho**
- Sustituido el CSS de plantilla (acento morado) por un **sistema de tokens de diseño** en `index.css`:
  superficies, tinta, un azul de marca, colores semánticos del dinero, radios, sombras — más un **tema
  oscuro** completo.
- Refactorizados todos los componentes/páginas a **clases** que leen los tokens (`.card`, `.btn`,
  `.table`, `.tag`, `.navbar`, `.stat-value`, `.alert-warn`…). Los gráficos leen `--chart-*` en tiempo de
  ejecución para seguir el tema.
- Estética sobria y financiera (bordes finos, sombras sutiles, cifras tabulares) — deliberadamente lejos
  del look de plantilla de IA. Documentado en la [guía 11](guide/11-visual-redesign.md).

**Por qué**
- Con toda la funcionalidad ya lista, un aspecto limpio y profesional es lo que convierte "funcional" en
  "impresionante" para un reclutador.

**Siguiente**
- Internacionalización: un botón de idioma ES/EN.

### 2026-07-02 — Entrada 013: Frontend dividido en páginas + navbar (React Router)
**Hecho**
- Añadido React Router: un `Layout` (navbar + `<Outlet/>`) que carga los datos una vez con
  `useFinanceData()` y los comparte con las páginas por el contexto del Outlet.
- Dividida la página única en **Movements** (`/`), **Dashboard** (`/dashboard`) y **Taxes** (`/taxes`).
- Extraídos helpers compartidos (`lib/format`, `components/Stat`). Verificado que todas las rutas
  devuelven 200 (fallback SPA) y que el proxy de API sigue funcionando. Documentado en la
  [guía 10](guide/10-frontend-routing.md).

**Por qué**
- Una estructura real de SPA multipágina es más limpia, escalable y lo que un reclutador espera — y
  prepara el terreno para el rediseño visual.

**Siguiente**
- Rediseño visual: un aspecto limpio y profesional que no parezca generado por IA.

### 2026-07-02 — Entrada 012: Estimación de IRPF (modelo 130) + aviso de vencimiento
**Hecho**
- Añadido `IrpfService` (reutiliza `VatService::baseCents()`): 20% acumulado del neto del año hasta la
  fecha (ingresos de actividad − gastos deducibles, sin IVA), excluyendo la nómina, por trimestre con los
  vencimientos oficiales y una cuenta atrás del "próximo vencimiento".
- Añadido `GET /api/irpf` y un panel de IRPF (tabla de trimestres + banner de aviso cuando falta < 30 días).
- Verificado: neto T1 1153,21 → pago 230,64; próximo vencimiento T2 2026-07-20 (18 días). Documentado en
  la [guía 09](guide/09-irpf.md).

**Por qué**
- IRPF + IVA juntos cuentan una historia fiscal completa — el valor central para un autónomo español y la
  demostración más clara del foso financiero.

**Siguiente**
- Importación Norma 43 y luego el rediseño del frontend.

### 2026-07-02 — Entrada 011: Panel de IVA (empieza la Fase 2)
**Hecho**
- Añadido `VatService` (mapa categoría → tipo de IVA español, cálculo de IVA en céntimos) y `GET /api/vat`.
- Añadido un panel de IVA en React: IVA repercutido, soportado y neto (a pagar / a compensar).
- Verificado con los datos de ejemplo: repercutido 336,00, soportado 23,00, neto 313,00 a pagar — la
  nómina y la cuota de autónomo correctamente sin IVA. Documentado en la [guía 08](guide/08-vat-panel.md).

**Por qué**
- Este es el foso financiero: calcular el IVA bien (tipos correctos por categoría, nóminas exentas,
  céntimos exactos) es justo el conocimiento del dominio que diferencia este proyecto.

**Siguiente**
- Estimación de IRPF (modelo 130) y avisos de trimestre.

### 2026-07-02 — Entrada 010: Panel con gráficos
**Hecho**
- Añadido `GET /api/stats` (agregación en SQL: totales, gasto por categoría, ingresos/gastos por mes).
- Añadido un componente `Dashboard` en React (Recharts): barra horizontal de un tono para el gasto por
  categoría y barras de dos series para ingresos vs gastos por mes. Colores elegidos con el método de
  data-viz y **validados** con el script de la skill (CVD ΔE 74.6). Los gráficos se refrescan al
  importar/categorizar.
- Documentado en la [guía 07](guide/07-dashboard.md).

**Por qué**
- Es la pantalla más "vendible": de un vistazo ves a dónde va el dinero — la recompensa del trabajo de
  importación + categorización.

**Siguiente**
- Fase 2: el panel de IVA (repercutido vs soportado, neto a pagar) — el foso financiero.

### 2026-07-02 — Entrada 009: Categorización con IA (con fallback por reglas)
**Hecho**
- Añadido `CategorizerService`: lista fija de categorías, motor de reglas por palabras clave en español, y
  una llamada opcional a Claude (vía HttpClient) validada contra la lista permitida.
- Añadido `POST /api/transactions/categorize` y un botón "🧠 Categorize" + columna Categoría en React.
- Verificado sin API key: 8/8 categorizadas correctamente por reglas (Mercadona→Supermercado,
  Repsol→Combustible, Nómina→Nómina, etc.). Documentado en la [guía 06](guide/06-ai-categorization.md).

**Por qué**
- La categorización convierte una lista en información útil. Mantener la IA opcional (fallback por reglas)
  hace que la app siempre funcione y sea honesta — un principio de arquitectura deliberado.

**Siguiente**
- Panel: gasto por categoría y por mes (Recharts), y luego el panel de IVA (Fase 2).

### 2026-07-02 — Entrada 008: Listar movimientos (bucle visible de punta a punta)
**Hecho**
- Añadido `GET /api/transactions` (mapea entidades a un array DTO plano, ordenado por fecha).
- Reescrito el `App.jsx` de React: tabla de movimientos (formato euro, rojo/verde según signo), un
  resumen de ingresos/gastos/balance, y un input de fichero que sube un CSV (FormData) y refresca.
- Verificado: el endpoint devuelve 8 filas a través del proxy de Vite y el frontend compila sin errores.
  Documentado en la [guía 05](guide/05-list-transactions.md).

**Por qué**
- Primer bucle totalmente visible: subes un CSV → ves tus movimientos. Convierte el trabajo del backend
  en algo que una persona (o un reclutador) puede ver de verdad.

**Siguiente**
- Categorización con IA: asignar una categoría a cada movimiento con Claude + fallback por reglas.

### 2026-07-02 — Entrada 007: Importación CSV
**Hecho**
- Añadido `ImportService` (parsea un CSV bancario) y `POST /api/import/csv` (controlador fino).
- El parser autodetecta el separador `,`/`;`, mapea columnas bilingües y maneja el formato numérico
  español (`1.234,56`) — manteniendo el dinero como string decimal exacto.
- Añadido un fichero de ejemplo y verificado de punta a punta: importadas 8 filas (balance 3316.21),
  0 errores, acentos preservados. Documentado en la [guía 04](guide/04-csv-import.md).

**Por qué**
- Es el primer valor tangible: un extracto real del banco se convierte en datos estructurados. Mantener
  correcto el parseo financiero (decimales españoles, sin float) es el diferenciador.

**Siguiente**
- Exponer los movimientos vía un endpoint de API y mostrarlos en el frontend React.

### 2026-07-02 — Entrada 006: Base de datos + modelo de dominio (empieza la Fase 1)
**Hecho**
- Instalado Doctrine (`symfony/orm-pack`) y MakerBundle.
- Arrancado PostgreSQL local (`pg_ctl`), configurado `DATABASE_URL` en `.env.local`, creada la base de
  datos `cuentia`.
- Escritas las entidades `Category` y `Transaction` (+ repositorios); generada y ejecutada la primera
  migración. Ya existen las tablas `category` y `transaction`.
- Documentado en la [guía 03](guide/03-database-and-entities.md). Decidido usar IDs enteros
  autoincrementales por ahora (más simple que UUID); actualizada la ARQUITECTURA en consecuencia.

**Por qué**
- Las entidades son la columna vertebral de la app. Hacer bien el dinero desde el primer día
  (`decimal`, no float) evita toda una familia de errores de contabilidad.

**Siguiente**
- Importación CSV: un endpoint que parsea un CSV bancario y guarda filas `Transaction` (guía 04).

### 2026-07-02 — Entrada 005: Frontend React + Fase 0 completa
**Hecho**
- Generada la app React (Vite) en `frontend/`; añadido un **proxy** de desarrollo (`/api` → `:8000`)
  para evitar CORS.
- `App.jsx` llama a `GET /api/health` al montarse y muestra "✅ API OK".
- Verificado de punta a punta: con ambos servidores en marcha, `http://localhost:5173/api/health` se
  reenvía por proxy al backend y devuelve el JSON. **La Fase 0 está completa.**
- Documentado en la [guía 02](guide/02-frontend-scaffold.md), incluyendo el detalle real de
  `localhost` vs `127.0.0.1` (IPv6) que nos pasó.

**Por qué**
- Demostrar ya el enlace frontend↔backend, con la función más simple posible, hace que cada función
  posterior se construya sobre una base que sabemos que funciona.

**Siguiente**
- Fase 1: modelo de dominio (`Transaction`, `Category`), PostgreSQL vía Doctrine e importación CSV
  (guía 03).

### 2026-07-01 — Entrada 004: Scaffold del backend + primer verde
**Hecho**
- Generada la API Symfony 8.1 en `backend/` (`symfony new backend`); eliminado su `.git` anidado
  para mantener un único monorepo.
- Añadido `HealthController` con `GET /api/health`; verificado que devuelve
  `{"status":"ok","service":"cuentia-api"}` con un servidor local.
- Documentado todo en la [`guide/`](guide/) didáctica (00-entorno, 01-scaffold del backend).

**Por qué**
- Un endpoint de salud es la prueba más pequeña posible de que la cadena
  petición→controlador→respuesta funciona — el primer hito correcto antes de añadir base de datos o
  funciones.

**Siguiente**
- Generar el frontend React (Vite) y que llame a `/api/health` (guía 02).

### 2026-07-01 — Entrada 003: Repo publicado + entorno instalado
**Hecho**
- Creado y pusheado el repo público: **github.com/R0b3r7DEV/cuentia** (primer commit = docs).
- Instalado el entorno del backend con Scoop: **PHP 8.5.7**, **Composer 2.10.2**,
  **Symfony CLI 5.17.1**, **PostgreSQL 18.4** (clúster inicializado; auth `trust` en local).
- Creado un `php.ini` que habilita las extensiones que Symfony necesita: `pdo_pgsql`, `pgsql`,
  `intl`, `mbstring`, `openssl`, `curl`, `fileinfo`, `sodium`, `zip`.

**Por qué**
- Construir en público desde el commit 1 cuenta una buena historia; un entorno local funcionando
  desbloquea la Fase 0.

**Siguiente**
- Generar la API Symfony y la app React; primer "verde" `GET /api/health` → 200.

### 2026-07-01 — Entrada 002: Documentación bilingüe
**Hecho:** toda la documentación es ahora bilingüe (inglés primero, español debajo).
**Por qué:** el autor usa los docs en español mientras aprende, y el reclutador lee el inglés.
**Siguiente:** lo mismo que la entrada 001 — instalar herramientas y generar el scaffold.

### 2026-07-01 — Entrada 001: Proyecto decidido, cimientos puestos
**Hecho**
- Elegido proyecto y stack (ver [ADR 0002](decisions/0002-project-and-stack.md)):
  *Cuentia*, un copiloto financiero con IA, sobre **Symfony + React + PostgreSQL + Claude**.
- Creado el esqueleto de documentación: README, ROADMAP, ARCHITECTURE, ADR 0001, ADR 0002 y este diario.
- Definido el modelo de dominio inicial (`Transaction`, `Category`) y el roadmap por fases.

**Por qué**
- Empezar con el *porqué* escrito (ADRs) y un plan por fases mantiene el foco y hace que cada decisión
  sea defendible más adelante — que es justo el objetivo de este proyecto.

**Siguiente**
- Instalar el tooling del backend: PHP, Composer, Symfony CLI, PostgreSQL (Fase 0).
- Generar la API Symfony y la app React; conseguir un `GET /api/health` que devuelva 200.
