# 09 — IRPF estimate & quarter alerts · Estimación de IRPF y avisos de trimestre

Goal / Objetivo: estimate the quarterly IRPF prepayment (modelo 130) and warn about the next deadline.

> More finance moat. Getting modelo 130 right — cumulative, 20% of net, salary excluded — is real
> domain knowledge. / Más foso financiero. Hacer bien el modelo 130 — acumulativo, 20% del neto,
> excluyendo la nómina — es conocimiento del dominio real.

---

## 1. The rules (what modelo 130 is)

- **EN:** A self-employed person (autónomo) prepays income tax every quarter with **modelo 130**:
  **20% of the year-to-date net** (self-employment income − deductible expenses, both **without VAT**),
  minus what was already paid in previous quarters (it's **cumulative**). A **salary ("Nómina")** is
  *employee* income and is **not** part of modelo 130. Deadlines: Q1→Apr 20, Q2→Jul 20, Q3→Oct 20,
  Q4→Jan 30 (next year).
- **ES:** Un autónomo adelanta el IRPF cada trimestre con el **modelo 130**: **20% del neto acumulado del
  año** (ingresos de actividad − gastos deducibles, ambos **sin IVA**), menos lo ya pagado en trimestres
  anteriores (es **acumulativo**). Una **nómina** es renta *del trabajo* y **no** entra en el modelo 130.
  Vencimientos: T1→20 abr, T2→20 jul, T3→20 oct, T4→30 ene (año siguiente).

## 2. The service

File: `src/Service/IrpfService.php`

- Reuses `VatService::baseCents()` to get each amount **without VAT** — a single source of truth for the
  tax rules.
- Counts **only self-employment income** (`Ingresos de cliente`, `Otros ingresos`) — excludes `Nómina`.
- Accumulates income/expense bases per quarter, then walks Q1→Q4 building the **cumulative** payment:
  `payment(q) = 20% × max(0, cumulativeNet) − alreadyPaid`.
- Computes the **next deadline** relative to today, with days left.

```php
$dueCumC   = (int) round(max(0, $cumNetC) * 20 / 100); // 20% of year-to-date net
$paymentC  = max(0, $dueCumC - $paidC);                // minus previous quarters
```

## 3. The endpoint + panel

- Backend: `GET /api/irpf` → `{ year, quarters[], totalPayment, nextDeadline }`.
- Frontend: an **IRPF panel** with a quarter table (net, payment, deadline) and an **alert banner** when
  the next deadline is within 30 days.

## 4. Verify (sample data, "today" = 2026-07-02)

```powershell
Invoke-RestMethod "http://127.0.0.1:8000/api/irpf"
```

```
Q1 2026 · net 1153.21 · payment 230.64 · deadline 2026-04-20
nextDeadline: Q2, 2026-07-20, 18 days left
```

- **EN:** Correct: Q1 net = 1.600 (client income base) − 446.79 (expense bases) = 1.153,21; 20% = 230,64.
  The 1.850 salary is **excluded**. The panel shows a warning because modelo 130 for Q2 is due in 18 days.
- **ES:** Correcto: neto T1 = 1.600 (base de ingresos de cliente) − 446,79 (bases de gasto) = 1.153,21;
  20% = 230,64. La nómina de 1.850 queda **excluida**. El panel avisa porque el modelo 130 del T2 vence
  en 18 días.

> Simplification / Simplificación: all expenses are treated as deductible and withholdings (retenciones)
> are not subtracted yet — both are natural next refinements. / Todos los gastos se tratan como
> deducibles y aún no se restan retenciones — ambos son refinamientos naturales siguientes.

---

**Next / Siguiente:** Norma 43 import (the standard Spanish bank statement format), then the frontend
redesign. / Importación Norma 43 (el formato estándar de extracto bancario español) y luego el rediseño
del frontend.
