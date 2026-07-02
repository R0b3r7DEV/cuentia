# 04 — CSV import · Importación CSV

Goal / Objetivo: an endpoint that reads a bank CSV and stores the movements as `Transaction` rows.

---

## 1. The service (where the real work lives)

File: `src/Service/ImportService.php`

- **EN:** We put the parsing logic in a **service**, not in the controller. Why? A service has a single
  job, receives its dependencies (here `EntityManagerInterface`) via the constructor, and can be
  unit-tested without HTTP. This is the core idea of clean Symfony code: **thin controllers, rich services**.
- **ES:** Ponemos la lógica de parseo en un **servicio**, no en el controlador. ¿Por qué? Un servicio
  tiene una única responsabilidad, recibe sus dependencias (aquí `EntityManagerInterface`) por el
  constructor, y se puede testear sin HTTP. Es la idea central del código limpio en Symfony:
  **controladores finos, servicios ricos**.

What `importCsv()` does / Qué hace `importCsv()`:

1. Splits the text into lines and **auto-detects the separator** (`,` or `;`).
2. Reads the header and finds the `date/fecha`, `description/concepto`, `amount/importe` columns
   (bilingual column names).
3. For each row: parses the date, parses the amount, creates a `Transaction`, and `persist()`s it.
4. Calls `flush()` **once** at the end — a single database round-trip for all rows (efficient).
5. Returns `{ imported, errors }`, collecting per-row errors instead of failing the whole file.

### The finance detail that matters / El detalle financiero que importa

```php
// "1.234,56" (Spanish) and "1234.56" (international) both become "1234.56"
if ($hasDot && $hasComma) { /* dot = thousands, comma = decimal */ }
elseif ($hasComma)        { /* comma = decimal */ }
```

- **EN:** Spanish bank exports write amounts like `1.234,56` (dot = thousands, comma = decimal) and use
  `;` as the separator. Handling this correctly — and keeping the value as a **string**, never a float —
  is exactly the domain knowledge that makes this project credible.
- **ES:** Los extractos de bancos españoles escriben importes como `1.234,56` (punto = miles, coma =
  decimal) y usan `;` como separador. Manejar esto bien — y mantener el valor como **string**, nunca
  float — es justo el conocimiento del dominio que hace creíble este proyecto.

## 2. The controller (thin)

File: `src/Controller/ImportController.php`

```php
#[Route('/api/import/csv', name: 'api_import_csv', methods: ['POST'])]
public function importCsv(Request $request, ImportService $import): JsonResponse
{
    $file = $request->files->get('file');
    $csv  = $file ? file_get_contents($file->getPathname()) : $request->getContent();
    if (!is_string($csv) || trim($csv) === '') {
        return $this->json(['error' => 'No CSV provided'], 400);
    }
    return $this->json($import->importCsv($csv));
}
```

- **EN:** It accepts either an uploaded file (field `file`, for the future UI) or the raw request body
  (handy for testing). Then it just delegates to the service. Symfony **autowires** `ImportService`
  into the method — no manual wiring needed.
- **ES:** Acepta un fichero subido (campo `file`, para la futura interfaz) o el cuerpo crudo de la
  petición (cómodo para probar). Luego solo delega en el servicio. Symfony **inyecta** (autowiring)
  `ImportService` en el método — sin configuración manual.

## 3. Test it

Sample file: [`docs/sample-data/movimientos-ejemplo.csv`](../sample-data/movimientos-ejemplo.csv)
(Spanish format: `;` separator, comma decimals, accents).

```powershell
# with the backend running on :8000
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/import/csv" -Method Post `
  -InFile "docs\sample-data\movimientos-ejemplo.csv" -ContentType "text/csv; charset=utf-8"
```

Result / Resultado:

```json
{ "imported": 8, "errors": [] }
```

Verify in the database / Verifica en la base de datos:

```powershell
php bin/console dbal:run-sql "SELECT count(*) AS total, sum(amount) AS balance FROM transaction"
# total = 8, balance = 3316.21
```

- **EN:** 8 rows imported; Spanish amounts (`1850,00`, `-52,30`) stored as exact decimals; accents
  preserved. The whole chain **HTTP → controller → service → Doctrine → PostgreSQL** works.
- **ES:** 8 filas importadas; los importes españoles (`1850,00`, `-52,30`) guardados como decimales
  exactos; acentos preservados. Toda la cadena **HTTP → controlador → servicio → Doctrine → PostgreSQL**
  funciona.

> ⚠️ Running the import twice inserts the rows again (no de-duplication yet). Detecting duplicates is a
> later improvement. / Ejecutar la importación dos veces inserta las filas otra vez (aún sin
> de-duplicación). Detectar duplicados es una mejora posterior.

---

**Next / Siguiente:** list the imported transactions through an API endpoint and show them in the
React frontend. / listar los movimientos importados con un endpoint y mostrarlos en el frontend React.
