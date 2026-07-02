# 06 — AI categorization · Categorización con IA

Goal / Objetivo: assign a category to each transaction — with Claude if available, and a deterministic
rule engine as a fallback.

---

## The principle: AI is optional

- **EN:** A core design rule of this project (see [ARCHITECTURE](../ARCHITECTURE.md)): **every AI feature
  has a deterministic fallback**. If there's no API key — or the AI call fails, or returns garbage — the
  app still categorizes using rules. The AI improves the result; it is never a single point of failure.
- **ES:** Regla de diseño central del proyecto (ver [ARCHITECTURE](../ARCHITECTURE.md)): **toda función de
  IA tiene un fallback determinista**. Si no hay clave — o la llamada falla, o devuelve basura — la app
  categoriza igualmente con reglas. La IA mejora el resultado; nunca es un punto único de fallo.

## 1. The service

File: `src/Service/CategorizerService.php`

- **A fixed category list** (`CATEGORIES`, name → kind) is the single source of truth. Both the rules and
  the AI must return a name from this list.
- **`decide()`** — if an API key is configured, try Claude; on *any* exception, fall through to rules.
- **`ruleCategorize()`** — lowercases the description and matches Spanish keywords
  (`mercadona`→Supermercado, `repsol`→Combustible, `nómina`→Nómina, `factura/cliente`→Ingresos de cliente…),
  using the sign of the amount to decide income vs expense.
- **`aiCategorize()`** — asks Claude (via Symfony `HttpClient`) to return `{"category": "..."}`, then
  **validates** the answer is one of the allowed categories (otherwise it throws → fallback).
- **`getOrCreateCategory()`** — finds the `Category` row by name or creates it with the correct kind.

```php
private function decide(string $description, string $amount): array
{
    if ($this->apiKey !== '') {
        try { return [$this->aiCategorize($description, $amount), 'ai']; }
        catch (\Throwable) { /* fall through to rules */ }
    }
    return [$this->ruleCategorize($description, $amount), 'rule'];
}
```

- **EN:** We record **how** each category was set (`categorySource` = `ai` | `rule`) — useful for trust,
  debugging and metrics.
- **ES:** Registramos **cómo** se asignó cada categoría (`categorySource` = `ai` | `rule`) — útil para la
  confianza, la depuración y las métricas.

## 2. The endpoint

```php
#[Route('/api/transactions/categorize', methods: ['POST'])]
public function categorize(CategorizerService $categorizer): JsonResponse
{
    return $this->json($categorizer->categorizeUncategorized());
}
```

Categorizes every transaction that has no category yet, and returns
`{ categorized, byAi, byRule }`.

## 3. The frontend

`App.jsx` gets a **🧠 Categorize** button (calls the endpoint, then refreshes) and a **Category** column
that shows each transaction's category as a chip. / `App.jsx` gana un botón **🧠 Categorize** (llama al
endpoint y refresca) y una columna **Category** que muestra la categoría como una etiqueta.

## 4. Enabling the AI (optional)

Put a real key in `backend/.env.local` (never commit it):

```
ANTHROPIC_API_KEY=sk-ant-...
```

- **EN:** With a key set, `decide()` calls Claude (model `claude-haiku-4-5` — cheap and fast for
  classification) and `byAi` will be > 0. Without it, everything is categorized by rules.
- **ES:** Con la clave puesta, `decide()` llama a Claude (modelo `claude-haiku-4-5` — barato y rápido para
  clasificar) y `byAi` será > 0. Sin ella, todo se categoriza por reglas.

## 5. Verify

```powershell
Invoke-RestMethod "http://127.0.0.1:8000/api/transactions/categorize" -Method Post
# → { "categorized": 8, "byAi": 0, "byRule": 8 }   (no key set → rules)
```

Result on the sample data (rules only) — all correct / Resultado con los datos de ejemplo (solo reglas)
— todo correcto:

| Description | Category |
|---|---|
| Nómina Empresa SL | Nómina |
| Compra Mercadona | Supermercado |
| Suscripción Adobe Creative Cloud | Software y suscripciones |
| Cliente ACME - factura 001 | Ingresos de cliente |
| Gasolina Repsol | Combustible |
| Cuota autónomo Seguridad Social | Cuota autónomo |
| Restaurante La Tagliatella | Restauración |
| Cliente Beta SL - factura 002 | Ingresos de cliente |

---

**Next / Siguiente:** a dashboard that groups spending **by category** and **by month**, and (Phase 2)
the VAT panel — where the finance knowledge really pays off.
