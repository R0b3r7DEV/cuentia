# Dev log · Diario de desarrollo

**Languages:** [English](#english) · [Español](#español)

A running, dated journal of every meaningful step: what we did, why, and what's next.
Newest entries at the top.

*Diario fechado de cada paso importante: qué hicimos, por qué y qué viene después. Las entradas más
recientes van arriba.*

---

## English

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
