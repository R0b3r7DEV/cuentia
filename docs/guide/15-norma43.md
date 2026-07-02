# 15 — Norma 43 import · Importación Norma 43

Goal / Objetivo: import the standard Spanish bank statement format (AEB Cuaderno 43), auto-detected.

> Supporting Norma 43 is pure domain knowledge — it's the format every Spanish bank exports. Parsing it
> correctly is exactly the kind of thing that sets this project apart. / Soportar Norma 43 es puro
> conocimiento del dominio — es el formato que exporta cualquier banco español.

---

## 1. What Norma 43 is

- **EN:** A **fixed-width** text format. Each line is a *record* whose first 2 characters are a type code:
  `11` account header, `22` a movement, `23` free-text concept lines, `33`/`88` footers. Fields are read
  by **character position**, not by a delimiter.
- **ES:** Un formato de texto de **ancho fijo**. Cada línea es un *registro* cuyos 2 primeros caracteres
  son un código de tipo: `11` cabecera de cuenta, `22` movimiento, `23` líneas de concepto, `33`/`88`
  registros finales. Los campos se leen por **posición de carácter**, no por separador.

Movement record (`22`) fields we use:

| Positions | Field |
|---|---|
| 7–12 | Operation date (`YYMMDD`) |
| 24 | Debit/credit (`1` = debit/expense, `2` = credit/income) |
| 25–38 | Amount (14 digits, 2 implied decimals) |

The description comes from the following `23` records (two 38-char fields each).

## 2. The parser

`ImportService::importNorma43()` walks the lines and **assembles** each movement: a `22` record starts a
new transaction (date, sign from the debit/credit flag, amount from cents), and the `23` records that
follow append to its description. A new `22` (or a `33`/`88` footer) flushes the current one.

```php
$isDebit = substr($raw, 23, 1) === '1';
$cents   = (int) ltrim(substr($raw, 24, 14), '0') ?: 0;
$amount  = number_format($cents / 100, 2, '.', '');
if ($isDebit) $amount = '-' . $amount;      // debit = expense
```

## 3. Auto-detection (one import for both formats)

`ImportService::import()` sniffs the content: if the first line starts with `11`/`22` and is ≥ 80 chars,
it's Norma 43; otherwise it's CSV. So the **same upload endpoint and button** handle both — the frontend
just accepts `.csv` and `.n43` files.

```php
public function import(string $content): array
{
    return $this->looksLikeNorma43($content) ? $this->importNorma43($content) : $this->importCsv($content);
}
```

## 4. Tested

`tests/Service/ImportServiceTest.php` builds real 80-char records in memory and asserts the parse:
credit `1.234,56` → `1234.56`, debit `60,00` → `-60.00`, description from the `23` line, date `260115` →
`2026-01-15`. Plus a CSV test. `php bin/phpunit` → **OK (8 tests, 25 assertions)**.

## 5. Verify

A sample file lives at [`docs/sample-data/extracto-ejemplo.n43`](../sample-data/extracto-ejemplo.n43).

```powershell
Invoke-RestMethod "http://127.0.0.1:8000/api/import/csv" -Method Post -InFile docs\sample-data\extracto-ejemplo.n43 -ContentType text/plain
# → { "imported": 4, "errors": [] }
```

- **EN:** Imported 4 movements with correct amounts and dates — the endpoint auto-detected the format.
  **Phase 2 is complete.**
- **ES:** Importados 4 movimientos con importes y fechas correctos — el endpoint auto-detectó el formato.
  **La Fase 2 está completa.**

---

**Next / Siguiente:** Phase 3 (cash-flow forecast, natural-language chat, real open banking) or wrapping
up for deploy. / Fase 3 (previsión de tesorería, chat en lenguaje natural, open banking real) o preparar
el deploy.
