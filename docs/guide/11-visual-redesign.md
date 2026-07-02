# 11 — Visual redesign · Rediseño visual

Goal / Objetivo: a clean, professional look — deliberately **not** the generic "AI-generated" style.

---

## The approach: design tokens, not scattered styles

- **EN:** All the look lives in **CSS custom properties (design tokens)** in `src/index.css`: surfaces,
  ink, one brand color, semantic money colors, radii and shadows. Components use **class names**
  (`.card`, `.btn`, `.table`, `.tag`, `.stat-value`…) that read those tokens — so the whole app is
  consistent and re-themable from one file.
- **ES:** Todo el aspecto vive en **variables CSS (tokens de diseño)** en `src/index.css`: superficies,
  tinta, un color de marca, colores semánticos del dinero, radios y sombras. Los componentes usan
  **clases** (`.card`, `.btn`, `.table`, `.tag`, `.stat-value`…) que leen esos tokens — así toda la app
  es coherente y re-tematizable desde un único fichero.

## Avoiding the "AI look"

- **EN:** No purple gradients, no glows, no rainbow. One restrained **brand blue** (the same as the
  charts), careful neutrals, generous whitespace, hairline borders, subtle shadows, and **tabular
  figures** for money. Sober and financial — the opposite of a flashy template.
- **ES:** Sin gradientes morados, sin glows, sin arcoíris. Un **azul de marca** contenido (el mismo de
  los gráficos), neutros cuidados, aire generoso, bordes finos, sombras sutiles y **cifras tabulares**
  para el dinero. Sobrio y financiero — lo contrario de una plantilla llamativa.

## What changed

| Area | Before | After |
|---|---|---|
| Tokens | template's purple `#aa3bff` | brand blue `#2a78d6` + neutral scale |
| Styling | inline styles everywhere | class names reading tokens |
| Cards | none | `.card` (border + subtle shadow) |
| Table | plain | uppercase muted headers, row hover, tabular amounts |
| Navbar | plain links | sticky bar, active link highlighted |
| Dark mode | — | full dark theme via `prefers-color-scheme` |

## Dark mode & charts

- **EN:** The dark theme is a **second set of token values** under `@media (prefers-color-scheme: dark)`
  — its own steps, not an automatic invert. The charts read `--chart-1/2/grid/muted` at runtime
  (`getComputedStyle`), so they match the active theme too.
- **ES:** El tema oscuro es un **segundo juego de valores de tokens** bajo
  `@media (prefers-color-scheme: dark)` — con sus propios pasos, no un invert automático. Los gráficos
  leen `--chart-1/2/grid/muted` en tiempo de ejecución (`getComputedStyle`), así que también siguen el
  tema activo.

## Verify

```powershell
npm run build            # compiles
# routes still serve: / , /dashboard , /taxes → 200
```

Open `http://localhost:5173` — cards, a sticky navbar with the active tab highlighted, hover states, and
(if your OS is in dark mode) a full dark theme.

---

**Next / Siguiente:** internationalization — an ES/EN language toggle. / internacionalización — un botón
de idioma ES/EN.
