# 44 — CIE assistant: step by step, with Claude · Asistente del CIE paso a paso

Goal / Objetivo: guide a self-employed electrician through a CIE on the phone, and let Claude do the boring
parts — reading the client's electricity bill, answering "what goes here?", reviewing the draft — without
ever deciding compliance.

*Objetivo: guiar al electricista por el CIE desde el móvil y dejar que Claude haga lo tedioso (leer la
factura de la luz, responder dudas, repasar el borrador) sin decidir nunca si la instalación cumple.*

---

## The steps / Los pasos

Certificados → **🧭 Hacer el certificado paso a paso**: 1 start (from a designed installation or from
scratch) · 2 client and supply · 3 installation · 4 protections and circuits · 5 installation company (from
"Mis datos") · 6 review, save, PDF and next steps (AutoFirma, GVA e-office). A design handed over from
Installation opens the assistant at step 2 with the technical part filled in.

Each step has a **fixed guide text** (always available, no cost). Steps and fields live in `lib/cie.js`
(Vitest: every form field belongs to exactly one step; every required field is asked somewhere).

## What Claude does / Lo que hace Claude

| Button | Endpoint | Model / effort |
|---|---|---|
| 📷 Foto de la factura de la luz (photo or PDF) | `POST /api/certificates/assistant/bill` | Claude Opus 5.5, `low` |
| ¿Qué pongo aquí? | `POST /api/certificates/assistant/help` | Claude Opus 5.5, `low` |
| Repasar con Claude | `POST /api/certificates/assistant/review` | Claude Opus 5.5, `medium` |

- Official Anthropic PHP SDK (`anthropic-ai/sdk`) behind `App\Assistant\ClaudeGateway`, so the logic is
  tested with a fake. **Structured outputs** (`output_config.format`, JSON Schema) — no free-text parsing.
  **Server-side fallback** (`fallbacks: "default"`) when the model declines for policy reasons.
- **Each user's own API key** (Account → Integrations, stored encrypted). Without one, the guide works and the
  Claude buttons are disabled with a link to add it.
- Rate limit: 40 Claude calls / hour per user (they spend the user's credit).
- Errors as fixed codes: `no_api_key`, `invalid_api_key` (409), `declined` (422), `unavailable`, `bad_output`
  (502), `too_many_requests` (429).

## Safeguards / Salvaguardas

1. **Claude proposes, the electrician decides.** Bill fields only fill what is empty (what was typed wins),
   are marked ✨ until touched, and what a rule can verify is verified: **CUPS** check letters (mod 529) and
   **NIF/NIE** letter (mod 23), postal code range. A misread digit shows ⚠️.
2. **Contracted power ≠ maximum admissible power.** It is offered ("usarla como potencia máxima"), never
   written by itself.
3. **Compliance is never Claude's.** The system prompt forbids saying an installation "complies", asks to name
   the ITC-BT for any regulatory value and to say so when unsure. ITC-BT-25 checks stay deterministic.
4. **Least personal data.** The bill is re-encoded (`ImageSanitizer`, EXIF/GPS dropped) or sent as PDF, read
   once and **not stored**; the screen says so. Help and review **never** send the client's name, NIF,
   addresses, postal code or CUPS (unit test asserts it).
5. **Prompt injection.** Bill text and user questions are wrapped and declared as data, not instructions;
   outputs are schema-constrained and post-validated (unknown fields/severities dropped, max 6 review items).

## Also / Además

- `certificate.cups` (new column), on the form, the assistant and the CIE PDF.
- The classic form still exists ("Nuevo"); a design from Installation now also brings the company from
  "Mis datos".
