# 08 — VAT panel (Phase 2) · Panel de IVA (Fase 2)

Goal / Objetivo: compute output VAT (IVA repercutido) vs input VAT (IVA soportado) and the net due.

> This is the **finance moat**. Listing transactions is easy; knowing which VAT rate applies to each
> kind of movement — and that salaries and Social Security carry none — is domain knowledge a generic
> developer doesn't have. / Este es el **foso financiero**. Listar movimientos es fácil; saber qué tipo
> de IVA aplica a cada movimiento — y que nóminas y Seguridad Social no llevan — es conocimiento que un
> dev genérico no tiene.

---

## 1. The concept (quick finance primer)

- **Output VAT (IVA repercutido):** the VAT contained in your **income** (what you charged clients).
- **Input VAT (IVA soportado):** the VAT contained in your **expenses** (what you paid suppliers).
- **Net = output − input.** If positive you pay it to the tax office; if negative you reclaim/carry it.
- To get the VAT out of a **gross** amount at rate *r*%: `base = gross / (1 + r/100)`, `vat = gross − base`.

ES: *Repercutido* = IVA de tus ingresos; *soportado* = IVA de tus gastos; *neto* = repercutido − soportado
(si es positivo, a pagar; si es negativo, a compensar).

## 2. The service

File: `src/Service/VatService.php`

- **A category → rate map** encodes the domain rules: `Ingresos de cliente` 21%, `Restauración`/`Supermercado`
  10%, `Nómina` and `Cuota autónomo` 0% (no VAT), etc. Unknown category → 0% (we don't invent VAT).
- **`splitVat()`** extracts base + VAT from a gross amount.
- **Money is handled in integer cents** — no floats for money, staying exact — then formatted back to a
  2-decimal string.
- **`summary()`** returns totals (`outputVat`, `inputVat`, `net`, bases) plus a **breakdown by rate**
  (0 / 10 / 21%).

```php
private function splitVat(int $grossC, int $rate): array
{
    if ($rate === 0) return [$grossC, 0];
    $baseC = (int) round($grossC * 100 / (100 + $rate));
    return [$baseC, $grossC - $baseC];   // [base, vat] in cents
}
```

## 3. The endpoint + panel

- Backend: `GET /api/vat` returns the summary.
- Frontend: a **VAT summary** section with three tiles — Output VAT, Input VAT, and Net (labeled "to pay"
  or "to reclaim" by sign). It refreshes with the rest on import/categorize.

## 4. Verify (real numbers from the sample data)

```powershell
Invoke-RestMethod "http://127.0.0.1:8000/api/vat"
```

```
outputVat : 336.00   (from "Ingresos de cliente" 1.600 base @ 21%)
inputVat  :  23.00   (10%: 8.25  ·  21%: 14.75)
net       : 313.00   → to pay to the tax office
```

- **EN:** Correct: the two client invoices (1.210 + 726 = 1.936 gross) contain 336 € of VAT on a 1.600 €
  base; salary and the autónomo fee correctly contribute **no** VAT.
- **ES:** Correcto: las dos facturas de cliente (1.210 + 726 = 1.936 brutos) contienen 336 € de IVA sobre
  una base de 1.600 €; la nómina y la cuota de autónomo aportan **cero** IVA, como debe ser.

> Simplification / Simplificación: rates are inferred from the category (sensible Spanish defaults). A
> future version can store a real per-line VAT rate on each transaction. / Los tipos se infieren de la
> categoría (valores españoles razonables). Una versión futura puede guardar el tipo de IVA real por línea.

---

**Next / Siguiente:** IRPF estimate (modelo 130) and quarterly deadline alerts — more of the finance
moat. / Estimación de IRPF (modelo 130) y avisos de trimestre — más foso financiero.
