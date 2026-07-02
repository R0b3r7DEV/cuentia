# 07 — Dashboard (charts) · Panel (gráficos)

Goal / Objetivo: show spending **by category** and income vs expenses **by month** with charts.

---

## 1. The stats endpoint (SQL does the maths)

File: `src/Controller/StatsController.php` — `GET /api/stats`

- **EN:** We use **raw SQL** (via Doctrine's DBAL `Connection`) for the aggregations. Grouping by month
  and summing income vs expenses separately is clearest and fastest in SQL — let the database do the
  arithmetic. It returns `{ totals, byCategory, byMonth }`.
- **ES:** Usamos **SQL directo** (vía la `Connection` DBAL de Doctrine) para las agregaciones. Agrupar
  por mes y sumar ingresos vs gastos por separado es más claro y rápido en SQL — que la base de datos
  haga las cuentas. Devuelve `{ totals, byCategory, byMonth }`.

```sql
-- income vs expenses per month (conditional sums + Postgres to_char)
SELECT to_char(booked_at, 'YYYY-MM') AS month,
       SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END)  AS income,
       SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) AS expenses
FROM transaction GROUP BY month ORDER BY month;
```

## 2. Choosing the charts (the data-viz method)

Colors come **last**; the data's job picks the form first:

- **Spending by category** → comparing **magnitudes** across categories → a horizontal bar chart, **one
  hue** (blue `#2a78d6`). One series, so no legend — the title names it; values are labeled directly.
- **Income vs expenses by month** → **two series** → two categorical hues (blue income, red `#e34948`
  expenses) **with a legend**.

- **EN:** The blue/red pair was **validated with the skill's script** (worst-case colorblind separation
  ΔE 74.6 — far above the ≥12 target), so the two series are distinguishable for color-blind users, not
  just by convention.
- **ES:** El par azul/rojo se **validó con el script de la skill** (separación para daltonismo en el peor
  caso ΔE 74.6 — muy por encima del objetivo ≥12), así que las dos series se distinguen también para
  personas con daltonismo, no solo por convención.

## 3. The chart component

```powershell
npm install recharts
```

File: `frontend/src/components/Dashboard.jsx` — uses **Recharts** (`BarChart`, `Bar`, `XAxis`, `YAxis`,
`Tooltip`, `Legend`, `ResponsiveContainer`). Amounts are formatted as euros with `Intl.NumberFormat`,
axes/labels use muted ink, gridlines are hairline — all per the data-viz palette.

- **EN:** `ResponsiveContainer` makes the charts fill their column at any width. Each chart has a
  **tooltip** by default (an HTML chart is interactive). Text (values, ticks) stays in muted ink, never
  the series color.
- **ES:** `ResponsiveContainer` hace que los gráficos llenen su columna a cualquier ancho. Cada gráfico
  lleva **tooltip** por defecto (un gráfico HTML es interactivo). El texto (valores, ticks) va en tinta
  apagada, nunca del color de la serie.

## 4. Wiring it in

`App.jsx` now also fetches `/api/stats` in `load()` and renders `<Dashboard stats={stats} />` above the
table. Because `load()` runs after an import or a categorization, the charts refresh automatically.
/ `App.jsx` ahora también pide `/api/stats` en `load()` y renderiza `<Dashboard stats={stats} />` sobre
la tabla. Como `load()` se ejecuta tras importar o categorizar, los gráficos se refrescan solos.

## 5. Verify

```powershell
Invoke-RestMethod "http://127.0.0.1:8000/api/stats"   # totals + byCategory (7) + byMonth (1)
npm run build                                          # compiles (recharts adds to the bundle)
```

Open `http://localhost:5173`: above the table you now see two charts — spending by category and
income vs expenses by month.

- **EN:** This is the most "sellable" screen so far: at a glance you see where the money goes.
- **ES:** Es la pantalla más "vendible" hasta ahora: de un vistazo ves a dónde va el dinero.

---

**Next / Siguiente:** Phase 2 — the **VAT panel** (output vs input VAT, net due), where the finance
knowledge becomes the product. / Fase 2 — el **panel de IVA** (repercutido vs soportado, neto a pagar),
donde el conocimiento financiero se convierte en el producto.
