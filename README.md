# Cuentia — AI cash-flow & tax copilot for freelancers and SMBs

**Languages:** [English](#english) · [Español](#español)

> Import your bank movements, let AI categorize them, and instantly see your VAT, your
> estimated income tax and your cash-flow forecast — the numbers your accountant asks for,
> without the spreadsheet.

> ⚠️ **Work in progress.** This project is being built in public, step by step, with every
> decision documented in [`docs/`](docs/). Follow the build in [`docs/DEVLOG.md`](docs/DEVLOG.md).

---

## English

### The problem

Every freelancer (*autónomo*) and small business in Spain faces the same monthly grind: export
the bank statement, open a spreadsheet, and manually tag each movement — supplier, income, expense
category, VAT — so they know how much tax they owe and whether they'll make payroll. It is tedious,
error-prone, and most of them only find out their real numbers when the quarter is already closing.

### The solution

**Cuentia** turns raw bank movements into financial clarity:

- **Import** movements (CSV / Spanish *Norma 43* file → later, real open banking).
- **Auto-categorize** each movement with AI (supplier, expense category, income).
- **Tax panel**: output vs input VAT, estimated IRPF, and quarterly deadline alerts (modelo 130 / 303).
- **Cash-flow forecast** for the next 30 / 60 / 90 days.
- **Ask in plain language**: *"How much did I spend on software this quarter?"*

### Why this project exists

I'm a web development student with a second diploma in **Business Administration & Finance**. This
project sits exactly on that intersection: the hard part isn't the CRUD, it's *knowing what the
numbers mean* — VAT mechanics, Spanish tax models, what a healthy cash flow looks like. That domain
knowledge is the core of the product.

### Tech stack

- **Backend:** PHP · Symfony · PostgreSQL
- **Frontend:** React
- **AI:** Anthropic Claude (categorization + natural-language queries)

### Documentation

| Doc | What it is |
|---|---|
| [docs/ROADMAP.md](docs/ROADMAP.md) | Phased plan, from MVP to full product |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design and domain model |
| [docs/DEVLOG.md](docs/DEVLOG.md) | Running log of every step taken |
| [docs/decisions/](docs/decisions/) | Architecture Decision Records (ADRs) |

---

## Español

### El problema

Cada autónomo y pequeña empresa en España sufre la misma rutina mensual: exportar el extracto del
banco, abrir una hoja de cálculo y etiquetar a mano cada movimiento —proveedor, ingreso, categoría de
gasto, IVA— para saber cuánto impuesto debe y si llegará a fin de mes. Es tedioso, propenso a errores,
y la mayoría solo descubre sus números reales cuando el trimestre ya se está cerrando.

### La solución

**Cuentia** convierte los movimientos bancarios en claridad financiera:

- **Importar** movimientos (CSV / fichero **Norma 43** → más adelante, open banking real).
- **Categorización automática** de cada movimiento con IA (proveedor, categoría de gasto, ingreso).
- **Panel fiscal**: IVA repercutido vs soportado, estimación de IRPF y avisos de trimestre (modelo 130 / 303).
- **Previsión de tesorería** a 30 / 60 / 90 días.
- **Preguntar en lenguaje natural**: *"¿cuánto gasté en software este trimestre?"*

### Por qué existe este proyecto

Soy estudiante de desarrollo web con un segundo título en **Administración y Finanzas**. Este proyecto
está justo en esa intersección: lo difícil no es el CRUD, es *saber qué significan los números* —la
mecánica del IVA, los modelos fiscales españoles, cómo se ve una tesorería sana—. Ese conocimiento del
dominio es el núcleo del producto.

### Stack tecnológico

- **Backend:** PHP · Symfony · PostgreSQL
- **Frontend:** React
- **IA:** Anthropic Claude (categorización + consultas en lenguaje natural)

### Documentación

| Documento | Qué es |
|---|---|
| [docs/ROADMAP.md](docs/ROADMAP.md) | Plan por fases, del MVP al producto completo |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Diseño del sistema y modelo de dominio |
| [docs/DEVLOG.md](docs/DEVLOG.md) | Diario de cada paso dado |
| [docs/decisions/](docs/decisions/) | Registros de Decisiones de Arquitectura (ADR) |
