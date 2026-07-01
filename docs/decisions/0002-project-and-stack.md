# ADR 0002 — Project scope and technology stack

- **Status:** Accepted · **Date:** 2026-07-01

**Languages:** [English](#english) · [Español](#español)

---

## English

### Context
The goal is a single **star portfolio project**: high value today, differentiated, and defensible in
an interview. The author's differentiator is a double background in **web development** and **business
& finance**, targeting remote/international roles (strong fit for EU fintech).

Constraints:
- The author is starting year 2 of a Web Application Development diploma, where **PHP/Symfony** and
  **JavaScript** are taught. The existing portfolio has no PHP project.
- Existing repos already cover: invoice extraction SaaS, RAG tutor, security agent. The new project
  must not duplicate them.

### Decision
**Project:** *Cuentia* — an AI cash-flow & tax copilot for freelancers and SMBs. It imports bank
movements, categorizes them with AI, and produces VAT/IRPF figures and a cash-flow forecast.

**Stack:** **Symfony (PHP) + PostgreSQL** backend, **React** frontend, **Claude** for AI.

Why this project: the finance domain (VAT, IRPF, cash flow) is the **moat** — knowledge a generic
developer cannot fake in an interview; it (eventually) uses **real bank data** via a free EU
open-banking API; and it is distinct from the author's existing projects.

Why this stack: learning **Symfony** while building the star project covers the year-2 syllabus *and*
fills the PHP gap in the portfolio (double benefit); PHP/Symfony has strong demand in the target
SMB/EU market; React is already familiar.

### Alternatives considered
- **FastAPI/Python + Next** — strongest stack and ideal for an AI specialization, but doesn't cover
  the PHP gap. Kept as a fallback if Symfony proves too slow to learn.
- **Java/Spring + React** — reinforces an enterprise backend profile but is more verbose/slower.
- **Simpler analyzer** or **agent/MCP-heavy** project — overlap with existing projects / less demoable.

### Consequences
- There's a learning curve: Symfony is new. Accepted — it's part of the point.
- Tax rules are Spain-specific; the product is deliberately Spain-first.

---

## Español

### Contexto
El objetivo es un único **proyecto estrella** de portfolio: de alto valor hoy, diferenciado y
defendible en una entrevista. El diferenciador del autor es su doble formación en **desarrollo web** y
**administración y finanzas**, con foco en empleo remoto/internacional (encaje fuerte con la fintech de
la UE).

Restricciones:
- El autor empieza 2º de DAW, donde se imparten **PHP/Symfony** y **JavaScript**. El portfolio actual
  no tiene ningún proyecto en PHP.
- Los repos existentes ya cubren: SaaS de extracción de facturas, tutor RAG, agente de seguridad. El
  proyecto nuevo no debe duplicarlos.

### Decisión
**Proyecto:** *Cuentia* — un copiloto financiero con IA para autónomos y pymes. Importa movimientos
bancarios, los categoriza con IA y produce cifras de IVA/IRPF y una previsión de tesorería.

**Stack:** backend **Symfony (PHP) + PostgreSQL**, frontend **React**, **Claude** para la IA.

Por qué este proyecto: el dominio financiero (IVA, IRPF, tesorería) es el **foso** — conocimiento que
un dev genérico no puede fingir en una entrevista; usa (a futuro) **datos bancarios reales** vía una
API de open banking europea gratuita; y es distinto de los proyectos existentes del autor.

Por qué este stack: aprender **Symfony** construyendo el proyecto estrella cubre el temario de 2º *y*
rellena el hueco de PHP en el portfolio (doble beneficio); PHP/Symfony tiene mucha demanda en el
mercado objetivo (pymes/UE); React ya es familiar.

### Alternativas consideradas
- **FastAPI/Python + Next** — el stack más fuerte e ideal para especializarse en IA, pero no cubre el
  hueco de PHP. Se guarda como plan B si Symfony resulta demasiado lento de aprender.
- **Java/Spring + React** — refuerza un perfil backend "enterprise" pero es más verboso/lento.
- **Analizador más simple** o proyecto **muy centrado en agentes/MCP** — solapan con proyectos
  existentes / menos demostrable visualmente.

### Consecuencias
- Hay curva de aprendizaje: Symfony es nuevo. Aceptado — es parte del objetivo.
- Las reglas fiscales son específicas de España; el producto es deliberadamente "España primero".
