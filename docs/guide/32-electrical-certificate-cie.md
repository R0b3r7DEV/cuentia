# 32 — Electrical Installation Certificate (CIE) · Certificado de Instalación Eléctrica

Goal / Objetivo: let an electrician fill in a low-voltage **Electrical Installation Certificate (CIE)**
following the Comunitat Valenciana official model **CERTINS E**, and export it as a PDF — a real "plus"
for a tradesperson using the app.

*Objetivo: que un electricista rellene un **Certificado de Instalación Eléctrica (CIE)** de baja tensión
siguiendo el modelo oficial de la Comunitat Valenciana **CERTINS E**, y lo exporte en PDF.*

---

## Research / Investigación

- **EN:** The Comunitat Valenciana has an **official model — CERTINS E (12/2012)** ("Certificado de
  Instalación Eléctrica en Baja Tensión"), plus CERTINS V for other cases, on the GVA sede electrónica.
  Its structure follows the **REBT (RD 842/2002)**: installation data, owner (titular), installer company &
  authorised installer, technical characteristics, and a compliance declaration. The **official issuance is
  telematic and digitally signed** by an authorised installer — no third party can file it on paper.
- **ES:** La Comunitat Valenciana tiene un **modelo oficial — CERTINS E (12/2012)**, más CERTINS V para
  otros casos, en la sede de la GVA. Sigue el **REBT (RD 842/2002)**. La **emisión oficial es telemática y
  con firma digital** por instalador habilitado.

Sources: GVA trámite [id_proc=440](https://sede.gva.es/es/detall-tramit?id_proc=440), model
[CERTINS E (PDF)](https://www.gva.es/downloads/publicados/IN/23165_ES.pdf).

## Honest scope / Alcance honesto

Cuentia generates a **fill-in draft PDF** that mirrors the CERTINS E structure — a working aid for the
electrician. It is **not** the official submission: that goes telematically, digitally signed, through the
GVA sede electrónica. The form and the PDF footer both say so.

*Cuentia genera un **borrador PDF** con la estructura del CERTINS E — una ayuda. **No** es la presentación
oficial (telemática y firmada digitalmente en la sede de la GVA), como indican el formulario y el pie del PDF.*

## Backend

`Certificate` entity (user-scoped) + `CertificateController` CRUD + PDF:

```
GET/POST/PUT/DELETE  /api/certificates       (address, titularName, companyName required)
GET                  /api/certificates/{id}/pdf   → CERTINS E-style PDF (Dompdf)
```

Fields grouped as: installation (type new/extension/reform, use, emplazamiento), titular, installer company
& authorised installer, and technical characteristics (max/installed power, voltage, supply
single/three-phase, earthing scheme, circuits, derivation section, IGA, differential, earth resistance &
conductor). `CiePdf` renders it with the section layout, declaration and signature blocks.

## Frontend

A **Certificados** tab in the Billing section: a grouped form, a list, per-row **Download PDF / Edit /
Delete**, and a prominent note that it is a draft aid.

## Verify / Verificar

```powershell
php bin/phpunit --filter Certificates
#  create (with technical fields) · validation (400) · update · PDF (%PDF) · delete · per-user isolation
php bin/phpunit
#  OK (45 tests, 192 assertions)
```

---

**Next / Siguiente:** agentic AI + OCR (need an Anthropic API key). / IA agéntica + OCR (necesitan API key
de Anthropic).
