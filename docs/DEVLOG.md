# Dev log · Diario de desarrollo

**Languages:** [English](#english) · [Español](#español)

A running, dated journal of every meaningful step: what we did, why, and what's next.
Newest entries at the top.

*Diario fechado de cada paso importante: qué hicimos, por qué y qué viene después. Las entradas más
recientes van arriba.*

---

## English

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
