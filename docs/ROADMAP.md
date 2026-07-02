# Roadmap

**Languages:** [English](#english) · [Español](#español)

The project is built in phases. Each phase ends with something that **works and can be demoed** —
no phase leaves the app broken. We favor depth and understanding over feature count.

Legend: ⬜ not started · 🟨 in progress · ✅ done

---

## English

### Phase 0 — Foundations ✅
Goal: a running skeleton and the documentation discipline in place.

- ✅ Decide project + stack (see [ADR 0002](decisions/0002-project-and-stack.md))
- ✅ Documentation skeleton (README, ROADMAP, ARCHITECTURE, DEVLOG, ADRs)
- ✅ Install tooling (PHP, Composer, Symfony CLI, PostgreSQL)
- ✅ Scaffold Symfony API + React app (PostgreSQL connection lands in Phase 1 with the first entity)
- ✅ First green: `GET /api/health` returns 200 and the React app calls it through the Vite proxy

### Phase 1 — MVP: import → categorize → see your money ⬜
Goal: the core loop works end to end with sample data.

- ✅ Domain model: `Transaction`, `Category` (migrations) + PostgreSQL via Doctrine
- ✅ **CSV import** of bank movements (date, description, amount) — handles Spanish number format
- ⬜ **AI categorization** of each transaction (Claude) with a rule-based fallback
- ⬜ Dashboard: income vs expenses, by category and by month
- ⬜ Manual re-categorization (correct the AI) — corrections feed future accuracy

### Phase 2 — The finance moat ⬜
Goal: the features a generic dev could not build without finance knowledge.

- ⬜ **VAT panel**: output VAT (repercutido) vs input VAT (soportado), net due
- ⬜ **IRPF estimate** for freelancers (modelo 130 logic)
- ⬜ Quarterly deadline alerts (modelo 130 / 303)
- ⬜ **Norma 43** parser (the standard Spanish bank statement format)

### Phase 3 — Intelligence & real data ⬜
- ⬜ **Cash-flow forecast** (30 / 60 / 90 days) from recurring movements
- ⬜ **Natural-language chat** over your transactions ("how much on X this quarter?")
- ⬜ **Real open banking** via GoCardless Bank Account Data (free sandbox → real banks)

### Phase 4 — Production quality ⬜
- ⬜ Authentication + multi-user (each user sees only their data)
- ⬜ Tests (unit for the finance logic, integration for the API)
- ⬜ CI (GitHub Actions) + live deploy + screenshots in the README
- ⬜ GDPR basics (privacy policy, data export/delete)

### Scope guardrails (so it stays finishable)
- Spain-first (VAT/IRPF rules for one country, done well, beats ten done badly).
- Freelancer-first persona before full SMB accounting.
- Every AI feature has a **deterministic fallback** so the app is never useless without a key.

---

## Español

El proyecto se construye por fases. Cada fase termina con algo que **funciona y se puede enseñar** —
ninguna fase deja la app rota. Priorizamos profundidad y comprensión sobre número de funciones.

Leyenda: ⬜ sin empezar · 🟨 en progreso · ✅ hecho

### Fase 0 — Cimientos ✅
Objetivo: un esqueleto que arranca y la disciplina de documentación en marcha.

- ✅ Decidir proyecto + stack (ver [ADR 0002](decisions/0002-project-and-stack.md))
- ✅ Esqueleto de documentación (README, ROADMAP, ARCHITECTURE, DEVLOG, ADRs)
- ✅ Instalar herramientas (PHP, Composer, Symfony CLI, PostgreSQL)
- ✅ Generar el backend Symfony (API) + la app React (la conexión a PostgreSQL llega en la Fase 1 con la primera entidad)
- ✅ Primer "verde": `GET /api/health` devuelve 200 y la app React lo llama a través del proxy de Vite

### Fase 1 — MVP: importar → categorizar → ver tu dinero ⬜
Objetivo: el bucle principal funciona de punta a punta con datos de ejemplo.

- ✅ Modelo de dominio: `Transaction`, `Category` (migraciones) + PostgreSQL vía Doctrine
- ✅ **Importación CSV** de movimientos (fecha, descripción, importe) — soporta el formato numérico español
- ⬜ **Categorización con IA** de cada movimiento (Claude) con fallback por reglas
- ⬜ Panel: ingresos vs gastos, por categoría y por mes
- ⬜ Recategorización manual (corregir a la IA) — las correcciones mejoran la precisión futura

### Fase 2 — El foso financiero ⬜
Objetivo: las funciones que un dev genérico no podría construir sin conocer finanzas.

- ⬜ **Panel de IVA**: IVA repercutido vs soportado, neto a pagar
- ⬜ **Estimación de IRPF** para autónomos (lógica del modelo 130)
- ⬜ Avisos de trimestre (modelo 130 / 303)
- ⬜ Parser de **Norma 43** (el formato estándar de extracto bancario español)

### Fase 3 — Inteligencia y datos reales ⬜
- ⬜ **Previsión de tesorería** (30 / 60 / 90 días) a partir de movimientos recurrentes
- ⬜ **Chat en lenguaje natural** sobre tus movimientos ("¿cuánto en X este trimestre?")
- ⬜ **Open banking real** vía GoCardless Bank Account Data (sandbox gratis → bancos reales)

### Fase 4 — Calidad de producción ⬜
- ⬜ Autenticación + multiusuario (cada usuario ve solo sus datos)
- ⬜ Tests (unitarios de la lógica financiera, integración de la API)
- ⬜ CI (GitHub Actions) + deploy en vivo + capturas en el README
- ⬜ Básicos de RGPD (política de privacidad, exportar/borrar datos)

### Límites de alcance (para que sea terminable)
- España primero (las reglas de IVA/IRPF de un país, bien hechas, superan a diez a medias).
- Persona "autónomo" antes que contabilidad completa de pymes.
- Toda función de IA tiene un **fallback determinista**, para que la app nunca sea inútil sin clave.
