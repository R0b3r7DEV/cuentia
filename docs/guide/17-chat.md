# 17 — Natural-language assistant · Asistente en lenguaje natural

Goal / Objetivo: ask questions about your finances in plain language — with a deterministic fallback.

---

## The idea

- **EN:** A chat where the user types *"How much did I spend on fuel?"* or *"How much VAT do I owe?"*. The
  backend builds a compact, factual **context** from the user's data and asks Claude to answer using
  **only** that context, in the user's language. If there's no API key (or the call fails), it returns a
  **data summary** instead — so the feature is always useful.
- **ES:** Un chat donde el usuario escribe *"¿cuánto gasté en combustible?"* o *"¿cuánto IVA debo?"*. El
  backend construye un **contexto** factual y compacto con los datos del usuario y le pide a Claude que
  responda usando **solo** ese contexto, en el idioma de la pregunta. Sin clave (o si falla), devuelve un
  **resumen de datos** — así la función siempre sirve.

## Backend

`ChatService::answer($question)`:
1. `buildContext()` — balance, income/expenses, top expense categories, VAT summary and the next IRPF
   payment, as a short factual block.
2. If an API key is set → `askClaude()` (HttpClient) with a strict system prompt (*answer only from this
   data, in the question's language*). Returns `source: 'ai'`.
3. Otherwise → returns the context as the answer. Returns `source: 'fallback'`.

```php
if ($this->apiKey !== '') {
    try { return ['answer' => $this->askClaude($q, $ctx), 'source' => 'ai']; }
    catch (\Throwable) { /* fall back */ }
}
return ['answer' => $ctx, 'source' => 'fallback'];
```

`POST /api/chat` with `{ "question": "…" }` → `{ answer, source }`.

- **EN — Grounding matters:** the model only sees the user's aggregated numbers and is told to use *only*
  them, which keeps answers accurate and avoids hallucinated figures.
- **ES — El grounding importa:** el modelo solo ve los números agregados del usuario y se le indica usar
  *solo* esos, lo que mantiene las respuestas exactas y evita cifras inventadas.

## Frontend

A new **Assistant** page (`/chat`): a message log + an input. It posts the question and appends the
answer; when `source` is `fallback`, a small note explains the AI isn't configured. Added to the navbar
and translated (ES/EN).

## Tested

`ChatServiceTest` checks the fallback path (no key): `source === 'fallback'` and the summary contains the
balance, VAT and the categories. `php bin/phpunit` → **OK (11 tests, 37 assertions)**.

## Verify

```powershell
$body = '{"question":"How much did I spend in total?"}'
Invoke-RestMethod "http://127.0.0.1:8000/api/chat" -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($body)) -ContentType "application/json"
```

Open `http://localhost:5173` → **Assistant**: ask a question; with no key you get a data summary, with a
key you get a natural-language answer.

> Enable AI by putting `ANTHROPIC_API_KEY=sk-ant-...` in `backend/.env.local` (never committed).

---

**Next / Siguiente:** Phase 4 — authentication/multi-user, then the live deploy.
/ Fase 4 — autenticación/multiusuario y luego el deploy en vivo.
