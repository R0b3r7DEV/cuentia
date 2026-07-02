# 16 — Cash-flow forecast (Phase 3) · Previsión de tesorería (Fase 3)

Goal / Objetivo: project the running balance 30 / 60 / 90 days ahead.

---

## The model (transparent on purpose)

- **EN:** A simple, honest **linear projection**: from the imported transactions we compute the current
  balance and the **average daily net** over the observed period, then project the balance forward. No
  black-box ML — the model is easy to explain and clearly labeled as an estimate.
- **ES:** Una **proyección lineal** simple y honesta: a partir de los movimientos importados calculamos el
  saldo actual y el **neto diario medio** del periodo observado, y proyectamos el saldo hacia delante. Sin
  ML de caja negra — el modelo es fácil de explicar y se etiqueta claramente como estimación.

```php
$daysObserved  = max(1, $first->diff($last)->days + 1);
$avgDailyCents = $balanceCents / $daysObserved;
// balance at +h days = current + avgDaily * h
```

## Backend

- `ForecastService::summary()` → `{ currentBalance, avgMonthlyNet, points[] }`, where `points` is the
  balance at day offsets 0/30/60/90. Money in cents; euros as strings.
- `GET /api/forecast`.

## Frontend

- `Forecast.jsx` renders a **line chart** (Recharts) of the projected balance, using the theme's chart
  color. The Dashboard shows current balance + avg. monthly net tiles, the chart, and a note stating it's
  an estimate.

## Tested

`ForecastServiceTest`: +1000 (Jan 1) and −400 (Jan 11) → balance 600 over 11 days → avg monthly 1636.36,
+30d ≈ 2236.36; empty input → all zeros. `php bin/phpunit` → **OK (10 tests, 33 assertions)**.

## Verify

```powershell
Invoke-RestMethod "http://127.0.0.1:8000/api/forecast"
```

Open `http://localhost:5173` → **Dashboard**: a "Cash-flow forecast" card with the projected-balance line.

- **EN:** Honest framing matters: the note makes clear this is a linear estimate, not a promise — the same
  intellectual honesty as the trading-bot project.
- **ES:** El encuadre honesto importa: la nota deja claro que es una estimación lineal, no una promesa — la
  misma honestidad intelectual que el proyecto del bot de trading.

---

**Next / Siguiente:** natural-language questions over your transactions (AI), then auth + deploy.
/ preguntas en lenguaje natural sobre tus movimientos (IA), y luego auth + deploy.
