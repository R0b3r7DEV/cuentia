# ADR 0001 — Record architecture decisions

- **Status:** Accepted · **Date:** 2026-07-01

**Languages:** [English](#english) · [Español](#español)

---

## English

### Context
This project is built to learn and to be defensible in a technical interview. Six months from now,
"why did I do it this way?" must have a written answer — both for me and for anyone reviewing the repo.

### Decision
We keep short **Architecture Decision Records (ADRs)** in `docs/decisions/`. One file per significant
decision, numbered sequentially. Each ADR states the context, the decision, and the consequences.
An ADR is immutable once accepted; if we change our mind, we write a new ADR that supersedes it.

### Consequences
- Every non-obvious choice has a paper trail.
- A recruiter reading the repo sees *reasoning*, not just code.
- Small overhead per decision — acceptable and worth it.

---

## Español

### Contexto
Este proyecto se construye para aprender y para ser defendible en una entrevista técnica. Dentro de
seis meses, "¿por qué lo hice así?" debe tener una respuesta escrita — tanto para mí como para quien
revise el repo.

### Decisión
Mantenemos **Registros de Decisiones de Arquitectura (ADR)** cortos en `docs/decisions/`. Un archivo
por decisión relevante, numerados en secuencia. Cada ADR indica el contexto, la decisión y las
consecuencias. Un ADR es inmutable una vez aceptado; si cambiamos de idea, escribimos uno nuevo que lo
sustituye.

### Consecuencias
- Toda elección no obvia deja rastro escrito.
- Un reclutador que lee el repo ve *razonamiento*, no solo código.
- Pequeño coste por decisión — aceptable y merece la pena.
