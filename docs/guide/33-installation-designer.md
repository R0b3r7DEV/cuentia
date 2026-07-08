# 33 — Installation designer, Phase 1 · Diseñador de instalación, Fase 1

Goal / Objetivo: from a dwelling's rooms and expected loads, compute its interior electrical installation
per **REBT ITC-BT-25** — circuits, minimum points per room, protections, a bill of materials, an estimated
cable length and a **single-line diagram** — and feed it (later) into the CIE and a quote.

*Objetivo: a partir de las estancias y las cargas previstas de una vivienda, calcular su instalación
interior según **REBT ITC-BT-25** — circuitos, puntos mínimos por estancia, protecciones, lista de
materiales, estimación de cable y **esquema unifilar**.*

---

## The engine / El motor

`InstallationCalculator` (pure PHP, heavily unit-tested) is the core. Given `{ grade, supplyType, loads,
rooms[] }` it returns the full result:

- **Circuits** — mandatory básico C1/C2/C5, appliance C3 (cocina/horno) & C4 (lavadora…), and elevado
  extras C8–C12 per the checked loads. Categories **split into additional circuits** when the per-circuit
  point limit is exceeded (lighting >30 → C6, general sockets >20 → C7, bath/kitchen >6 → extra C5). Each
  circuit carries its **section (mm²)** and **PIA (A)** from the ITC-BT-25 table.
- **Points per room** — minimum lights / general sockets / bath-kitchen sockets / switches, from surface.
- **Grade & power** — auto-detects *elevado* (surface > 160 m² or heating/AC/dryer/automation/EV), sets the
  power to contract (5 750 W básico / 9 200 W elevado).
- **Protections** — one differential per 5 circuits (min 2 on elevado), IGA sized by grade.
- **Materials** — IGA, differentials, one magnetotérmico per circuit (grouped by rating), sockets, lights,
  switches. **Cable** is a rough estimate (a trunk run + a span per point + 15 % slack), grouped by section.

## Honest scope / Alcance honesto

A **pre-dimensioning aid** per ITC-BT-25; cable metres are an estimate (refined in Phase 2 with a real
layout). **Not** a substitute for a project/memoria técnica signed by a competent technician — stated in the
UI note and everywhere. Same honesty line as Verifactu/CIE.

## API

```
POST /api/installations/compute      → stateless: input → full result (used for the live preview)
GET/POST/PUT/DELETE /api/installations   → save/list/open/delete designs (user-scoped)
```

Saved designs store only the **input**; the result is recomputed from it on read, so it never goes stale.

## Frontend

An **Instalación** tab in Billing: rooms editor (type + m²), expected-load checkboxes, grade/supply
selectors; it recomputes live (debounced) and shows the summary, the **single-line diagram (SVG)**, the
circuits table, points per room, and the materials list. Designs can be saved, reopened and deleted.

## Verify / Verificar

```powershell
php bin/phpunit --filter 'InstallationCalculator|InstallationDesigner'
#  básico 5 circuits · circuit specs · elevado from loads · C6/C7 splits · empty input · materials/cable
#  integration: compute · save (result derived) · CRUD · isolation
php bin/phpunit
#  OK (52 tests, 233 assertions)
```

---

**Next / Siguiente:** Phase 2 — a 2D floor-plan editor (place symbols, exact cable from positions), then
Phase 3 — an extruded 3D view. Also: autofill the CIE and turn the materials into a quote. /
Fase 2 — editor 2D de planta; Fase 3 — vista 3D; y autorrellenar el CIE + materiales → presupuesto.
