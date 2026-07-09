# 35 — 3D floor-plan view, Phase 3 · Vista 3D de la planta, Fase 3

Goal / Objetivo: extrude the same 2D layout into an **interactive 3D view** (walls, devices, panel) the
electrician can rotate, zoom and pan — the presentation layer over the design model.

*Objetivo: levantar la misma planta 2D a una **vista 3D interactiva** (paredes, dispositivos, cuadro) que
el electricista puede girar, acercar y desplazar — la capa de presentación sobre el modelo de diseño.*

---

## How / Cómo

`FloorPlan3D` renders the layout with **react-three-fiber** (Three.js):

- Each room rectangle is extruded into four **semi-transparent walls** (2.5 m) so you can see inside.
- Devices are boxes at realistic heights — **socket 0.3 m, switch 1.1 m, light 2.4 m (emissive), panel
  1.2 m** — coloured by type, at their 2D positions (2D *y* → 3D *z*).
- A floor plane + grid, ambient + directional light, and **OrbitControls** (drag to rotate, wheel to zoom,
  right-drag to pan).

It reads the **same `layout`** the 2D editor produces — no new data — so the two views always agree.

## Loaded on demand / Cargado bajo demanda

Three.js is heavy, so the whole 3D module is **lazy-loaded** (`React.lazy` + `Suspense`) behind a
**"View in 3D"** toggle. The build confirms it splits into its **own chunk** (`FloorPlan3D-*.js`), so the
main bundle is unchanged and Three.js only downloads when the user opens the view.

## Honest scope / Alcance honesto

A **schematic visualization** — semi-transparent walls and device markers, not a textured architectural
render or a to-scale BIM model. It closes the designer we scoped (enfoque B: useful 2D + a 3D "wow" layer).

## Verify / Verificar

```powershell
npm run build
#  a separate FloorPlan3D-*.js chunk appears; the main bundle stays ~the same size
php bin/phpunit
#  OK (55 tests, 244 assertions)   (backend unchanged this phase)
```

Open **Facturas → Instalación**, build/auto-place a plan, then **View in 3D**.

---

**Designer complete (enfoque B):** Phase 1 calculator + unifilar · integrations (→ CIE, → quote) · Phase 2
2D editor · Phase 3 3D view. / **Diseñador completo:** calculadora + unifilar · integraciones · editor 2D ·
vista 3D.
