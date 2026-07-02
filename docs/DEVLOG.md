# Dev log · Diario de desarrollo

**Languages:** [English](#english) · [Español](#español)

A running, dated journal of every meaningful step: what we did, why, and what's next.
Newest entries at the top.

*Diario fechado de cada paso importante: qué hicimos, por qué y qué viene después. Las entradas más
recientes van arriba.*

---

## English

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
