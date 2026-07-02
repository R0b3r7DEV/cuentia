# 10 — Frontend structure: pages & routing · Estructura del frontend: páginas y enrutado

Goal / Objetivo: split the single page into **Movements / Dashboard / Taxes** pages with a navbar.

> This is the first step of the redesign: a real SPA structure (React Router) instead of one long page.
> ES: Es el primer paso del rediseño: una estructura de SPA real (React Router) en vez de una única
> página larga.

---

## 1. Install the router

```powershell
npm install react-router-dom
```

## 2. The shape

```
App (BrowserRouter + Routes)
└── Layout            ← navbar + <Outlet/> + loads data once (useFinanceData)
    ├── / …………… MovementsPage   (import, categorize, transactions table)
    ├── /dashboard … DashboardPage  (income/expenses/balance + charts)
    └── /taxes …… TaxesPage       (VAT panel + IRPF panel)
```

- **EN:** `Layout` renders the `Navbar` and an `<Outlet/>` (where the active page appears). It calls
  `useFinanceData()` **once** and shares the data + a `reload()` with every page through the Outlet
  **context** — so navigating doesn't refetch, and an import on the Movements page updates the charts and
  tax panels too.
- **ES:** `Layout` renderiza la `Navbar` y un `<Outlet/>` (donde aparece la página activa). Llama a
  `useFinanceData()` **una vez** y comparte los datos + un `reload()` con cada página a través del
  **contexto** del Outlet — así navegar no vuelve a pedir datos, y una importación en Movements actualiza
  también los gráficos y los paneles fiscales.

## 3. Key pieces

| File | Role |
|---|---|
| `src/hooks/useFinanceData.js` | Loads transactions + stats + VAT + IRPF; exposes `reload()` |
| `src/components/Layout.jsx` | Navbar + `<Outlet context={data} />` |
| `src/components/Navbar.jsx` | `NavLink`s (active link highlighted) |
| `src/pages/MovementsPage.jsx` | Import, categorize, transactions table |
| `src/pages/DashboardPage.jsx` | Summary tiles + charts |
| `src/pages/TaxesPage.jsx` | VAT + IRPF panels |
| `src/lib/format.js`, `src/components/Stat.jsx` | Shared helpers |

- **EN:** Pages read the shared data with `useOutletContext()`. This is a clean pattern: one data source,
  many consumers, no prop-drilling. Reusable bits (currency formatting, the stat tile) live in shared files.
- **ES:** Las páginas leen los datos compartidos con `useOutletContext()`. Es un patrón limpio: una fuente
  de datos, muchos consumidores, sin pasar props en cascada. Lo reutilizable (formato de moneda, la
  tarjeta de estadística) vive en ficheros compartidos.

## 4. Verify

```powershell
# Vite on :5173, backend on :8000
# every client route returns the app (SPA fallback); /api is proxied to the backend
GET /           -> 200    GET /dashboard -> 200    GET /taxes -> 200
GET /api/transactions (via proxy) -> 8 rows
```

Open `http://localhost:5173` and use the navbar to move between Movements, Dashboard and Taxes.

- **EN:** The Vite dev server serves `index.html` for unknown paths (SPA fallback), so client-side routes
  like `/taxes` work on a hard refresh too.
- **ES:** El servidor de Vite sirve `index.html` para rutas desconocidas (fallback SPA), así que las rutas
  de cliente como `/taxes` funcionan también al recargar.

---

**Next / Siguiente:** the visual redesign — a clean, professional look (typography, spacing, color) that
does **not** look AI-generated. / el rediseño visual — un aspecto limpio y profesional (tipografía,
espaciado, color) que **no** parezca generado por IA.
