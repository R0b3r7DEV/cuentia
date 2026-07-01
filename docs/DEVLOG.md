# Dev log · Diario de desarrollo

**Languages:** [English](#english) · [Español](#español)

A running, dated journal of every meaningful step: what we did, why, and what's next.
Newest entries at the top.

*Diario fechado de cada paso importante: qué hicimos, por qué y qué viene después. Las entradas más
recientes van arriba.*

---

## English

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
