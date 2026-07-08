# 34 — 2D floor-plan editor, Phase 2 · Editor 2D de planta, Fase 2

Goal / Objetivo: place the installation on a **2D floor plan** — rooms, panel and electrical devices
(sockets, switches, lights) — so the **cable length is measured from real positions** instead of estimated.

*Objetivo: situar la instalación en un **plano 2D** — estancias, cuadro y dispositivos — para medir el
**cable desde posiciones reales** en vez de estimarlo.*

---

## The editor / El editor

`FloorPlanEditor` is a lightweight SVG canvas (metric grid, everything in metres):

- **Rooms** are draggable rectangles; **devices** (socket / switch / light) and the **panel (CGP)** are
  placed by picking a tool and clicking, then dragged with **Move** (or removed with **Delete**).
- **Auto-place** seeds the plan from the design: it lays the rooms out in a flow and drops each room's
  ITC-BT-25 points as devices (lights centred, sockets along the wall, switches by a corner) — a starting
  point the electrician then adjusts.

It emits the layout on every change, so the result refreshes live.

## Cable from positions / Cable desde posiciones

`InstallationCalculator::layoutCable()` measures each device's **Manhattan run to the panel** plus a drop,
with 10 % slack, grouped by device type — a much better figure than the Phase-1 estimate. When a layout has
devices, the result carries `layoutCable` and the UI shows **"Cable from plan: N m"** instead of the rough
estimate. Positions are in metres; the maths is pure and unit-tested.

## Persistence / Persistencia

The layout (panel + room rectangles + devices) is stored on the `Installation` as a JSON column (migration
added) and sanitised server-side. `POST /api/installations/compute` also accepts a `layout` and returns
`layoutCable`, so the live preview and a saved design agree.

## Honest scope / Alcance honesto

A **schematic** plan and a better cable estimate — not a to-scale CAD/BIM drawing, and still a
pre-dimensioning aid, not a signed project. Phase 3 will extrude this same model into a walkable 3D view.

## Verify / Verificar

```powershell
php bin/phpunit --filter 'InstallationCalculator|InstallationLayout'
#  layoutCable measures from device positions · null without devices · persistence via the API
php bin/phpunit
#  OK (55 tests, 244 assertions)
```

---

**Next / Siguiente:** Phase 3 — an extruded, walkable 3D view over the same 2D model
(react-three-fiber, lazy-loaded). / Fase 3 — vista 3D extruida y paseable sobre el mismo modelo 2D.
