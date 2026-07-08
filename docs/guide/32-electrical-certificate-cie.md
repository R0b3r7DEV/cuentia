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

## Sign & file — the three layers / Firmar y presentar — las tres capas

Making a CIE "really submittable" has three layers; Cuentia covers the two it honestly can:

| Layer | Cuentia |
|---|---|
| **A. Faithful document** — a complete CERTINS E-style PDF (all REBT fields, compliance declaration, signature areas) | ✅ produced |
| **B. Digital signature** (DNIe / FNMT / **ACCV** / Cl@ve-firma) | ✅ **sign-ready** — signed by the installer with **AutoFirma / ACCV**; Cuentia never touches the private key |
| **C. Telematic filing** to the GVA | ⛔ no public API — the **installer** uploads it at the GVA sede with their own certificate |

- **EN:** We deliberately do **not** ask for the installer's signing certificate. GVA (and best practice)
  recommend signing PDFs locally with **AutoFirma** or the free **ACCV** signer — the private key never
  leaves the installer's machine. So the PDF is laid out **ready to sign** (a reserved signature area for
  the company and the installer), and the Certificados tab shows the exact steps: download → sign
  (AutoFirma/ACCV) → file at the GVA sede. Layer C stays manual because there is no third-party submission
  API — the same honest boundary as Verifactu's real AEAT submission.
- **ES:** Deliberadamente **no** pedimos el certificado de firma del instalador. La GVA (y la buena
  práctica) recomiendan firmar los PDF en local con **AutoFirma** o el firmador gratuito de la **ACCV** — la
  clave privada nunca sale del equipo del instalador. El PDF queda **listo para firmar** (con área de firma
  para empresa e instalador), y la pestaña Certificados muestra los pasos: descargar → firmar
  (AutoFirma/ACCV) → presentar en la sede de la GVA.

Sources: [GVA sede — certificados admitidos](https://sede.gva.es/es/sede_certificados),
[AutoFirma](https://firmaelectronica.gob.es/Home/Descargas.html), [ACCV](https://www.accv.es).

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
