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

---

## The CERTINS E model and "Para la GVA" (2026-11-03) / El modelo CERTINS E y «Para la GVA»

What the GVA actually files (verified, not assumed):
- Procedure 440 ("Instalaciones eléctricas de BAJA TENSIÓN que requieran MEMORIA TÉCNICA DE DISEÑO") asks for web
  forms — datos generales, tipo de instalación, **certificado (CERTINS E/V)** — plus the **MTD**, digitally signed.
- **Since 2023 the certificate is not a document**: it is filled in online, and "NO se debe presentar otro documento
  a modo de certificado" (GVA low-voltage FAQ, 2023-11-02, q. 13). The installer signs it from the **Carpeta
  Ciudadana** (1 month to sign, q. 16). **CUPS is mandatory** when supplied from a distribution network (q. 26).
- The live web form sits behind a digital certificate, so its labels were taken from the **official CERTINS E
  model (04/2009)**, sections A–H. The app says so where it shows them.

What changed in Cuentia:
- `App\Cie\CertinsModel`: the model's lists with their exact labels (19 uses, 12 specific risks, supply source,
  design basis). `GET /api/certificates/model`.
- New certificate fields: broken-down address (tipo vía, vía, portal, bis, escalera, piso, puerta, población),
  supply source + distributor, specific risks, secondary uses, use specifications, MTD/project + signature date,
  insulation resistance (MΩ), installer NIF. Old use values mapped by the migration.
- `App\Cie\CertinsSheet`: one certificate as sections A–G (+ the MTD data, which is not part of the certificate)
  and the required fields still missing. Feeds `GET /api/certificates/{id}/gva` and the PDF.
- **Assistant in 8 steps**, in the model order; IGA, differential, circuits… moved to a "datos para la MTD" step.
  It now creates **and edits** (the old flat form did not know the new fields and would have wiped them on save).
- **"Para la GVA" screen** (`/certificates/:id/gva`): sections A–G with the model labels, a 📋 copy button per
  value, the missing fields, and a ready-made message for **Claude in Chrome** to copy the data into the GVA form —
  which forbids it to sign, register or submit and asks it to report what it filled and what is missing.
- **The PDF is a data sheet**, titled as such, saying it is not the certificate.
- Claude reads from the bill also the **distributor** (the network owner, not the retailer) and the address
  **in parts**; the client's broken-down address is never sent to help/review.
- Fix: powers written with a **decimal comma** ("5,75") were silently dropped by the backend.
