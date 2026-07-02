# 05 — List transactions (API + React) · Listar movimientos (API + React)

Goal / Objetivo: expose the transactions via the API and show them in a React table with totals.

---

## 1. The API endpoint

File: `src/Controller/TransactionController.php`

```php
#[Route('/api/transactions', name: 'api_transactions_list', methods: ['GET'])]
public function list(TransactionRepository $repo): JsonResponse
{
    $rows = array_map(fn (Transaction $t) => [
        'id' => $t->getId(), 'bookedAt' => $t->getBookedAt()->format('Y-m-d'),
        'description' => $t->getDescription(), 'amount' => $t->getAmount(),
        'currency' => $t->getCurrency(), 'category' => $t->getCategory()?->getName(),
    ], $repo->findBy([], ['bookedAt' => 'DESC', 'id' => 'DESC']));

    return $this->json($rows);
}
```

- **EN:** We inject the `TransactionRepository` and fetch all rows ordered by date (newest first). We map
  each entity to a **plain array** — a small DTO shape — instead of returning the entity directly. This
  keeps full control of the API contract and avoids exposing internal fields or triggering lazy-loading.
- **ES:** Inyectamos el `TransactionRepository` y traemos todas las filas ordenadas por fecha (más
  reciente primero). Convertimos cada entidad en un **array plano** — una especie de DTO — en vez de
  devolver la entidad. Así controlamos el contrato de la API y no exponemos campos internos ni
  provocamos cargas perezosas.

## 2. The React table

File: `frontend/src/App.jsx`

Three things happen here / Aquí pasan tres cosas:

1. **Load on mount** — `useEffect(load, [])` calls `GET /api/transactions` and stores the result.
2. **Upload** — a file `<input>`; on change it builds a `FormData`, appends the file under `file`, and
   `POST`s it to `/api/import/csv` (multipart). After a successful import it reloads the list.
3. **Display** — a table with the date, description and amount. Amounts are formatted as euros with
   `Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })` and colored red/green by sign.
   A small summary shows income, expenses and balance.

```jsx
const form = new FormData()
form.append('file', file)
await fetch('/api/import/csv', { method: 'POST', body: form })
```

- **EN:** `FormData` is how a browser sends a file to a server (multipart/form-data). The backend reads
  it with `$request->files->get('file')` — the same endpoint we tested earlier with a raw body.
- **ES:** `FormData` es la forma en que el navegador envía un fichero al servidor (multipart/form-data).
  El backend lo lee con `$request->files->get('file')` — el mismo endpoint que probamos antes con el
  cuerpo crudo.

- **EN:** `Intl.NumberFormat` is a built-in browser API for locale-aware number/currency formatting —
  no library needed. `es-ES` + `EUR` gives `1.850,00 €`.
- **ES:** `Intl.NumberFormat` es una API nativa del navegador para formatear números/moneda según la
  configuración regional — sin librerías. `es-ES` + `EUR` da `1.850,00 €`.

## 3. Verify

```powershell
# backend on :8000 and Vite on :5173, then:
Invoke-RestMethod "http://localhost:5173/api/transactions"   # → 8 rows through the proxy
npm run build                                                 # → compiles with no errors
```

Open `http://localhost:5173`: you see the summary (income / expenses / balance) and the table; pick a
CSV with the file input and the table refreshes.

- **EN:** This closes the first visible end-to-end loop: **upload a CSV → see your movements**.
- **ES:** Esto cierra el primer bucle visible de punta a punta: **subes un CSV → ves tus movimientos**.

---

**Next / Siguiente:** AI categorization — assign a category to each transaction with Claude, plus a
rule-based fallback. / Categorización con IA — asignar una categoría a cada movimiento con Claude, más
un fallback por reglas.
