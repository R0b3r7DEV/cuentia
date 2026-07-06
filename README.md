# Cuentia — AI cash-flow & tax copilot for freelancers and SMBs

**Languages:** [English](#english) · [Español](#español)

> Import your bank movements, let AI categorize them, and instantly see your VAT, your
> estimated income tax and your cash-flow forecast — the numbers your accountant asks for,
> without the spreadsheet.

> ⚠️ **Work in progress.** This project is being built in public, step by step, with every
> decision documented in [`docs/`](docs/). Follow the build in [`docs/DEVLOG.md`](docs/DEVLOG.md).

[![CI](https://github.com/R0b3r7DEV/cuentia/actions/workflows/ci.yml/badge.svg)](https://github.com/R0b3r7DEV/cuentia/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP_8.4-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony_8-000000?logo=symfony&logoColor=white)
![React](https://img.shields.io/badge/React_19-20232A?logo=react&logoColor=61DAFB)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-4169E1?logo=postgresql&logoColor=white)

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

### What it solves — and what would make it a real product (honest take)

Product sense matters, so let's be honest about scope. Cuentia gives a freelancer **visibility**: at a
glance, categorized movements, an **estimate** of VAT/IRPF, and a cash-flow forecast. That's genuinely
useful — but it's a **"vitamin," not a "painkiller."**

The problems a Spanish freelancer actually *pays* to solve are:

- **Issuing invoices** — and soon mandatory **e-invoicing / Verifactu**;
- **Filing taxes correctly** (modelo 130 / 303) without risking a penalty;
- doing it **fast and with confidence** — which is why they pay a *gestoría*.

Cuentia **estimates**; a product people pay for must be something they can **act on** (file, invoice) and
**trust**. To cross that line it would need an **accurate, per-line tax engine** (deductibility rules,
exemptions, reverse charge…), **invoicing / e-invoicing**, a **real bank connection** (open banking), and
**filing / accountant integration** — validated with real users first.

This project's goal was never revenue: it's to demonstrate building **serious business software** at the
intersection of web development and finance. Understanding the distinction above is part of that.

### Tech stack

- **Backend:** PHP · Symfony · PostgreSQL
- **Frontend:** React
- **AI:** Anthropic Claude (categorization + natural-language queries)

### Run it locally

Requirements: PHP, Composer, Symfony CLI, PostgreSQL and Node.js (see
[guide 00](docs/guide/00-environment.md)). Then:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-dev.ps1
```

This starts PostgreSQL, the Symfony API (`:8000`) and the Vite dev server, then open
**http://localhost:5173**. The first time, install dependencies (`cd backend; composer install`
and `cd frontend; npm install`) and create the database (see [guide 03](docs/guide/03-database-and-entities.md)).

> The servers only respond while they are running — if the page says *connection refused*, start them.

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

### Qué resuelve — y qué le faltaría para ser producto (visión honesta)

El criterio de producto importa, así que seamos honestos con el alcance. Cuentia le da al autónomo
**visibilidad**: de un vistazo, movimientos categorizados, una **estimación** de IVA/IRPF y una previsión
de tesorería. Es útil de verdad — pero es una **"vitamina", no un "analgésico".**

Los problemas por los que un autónomo español *paga* realmente son:

- **Emitir facturas** — y pronto la **factura electrónica obligatoria / Verifactu**;
- **Presentar bien los impuestos** (modelo 130 / 303) sin arriesgarse a una sanción;
- hacerlo **rápido y con confianza** — por eso pagan a una *gestoría*.

Cuentia **estima**; un producto por el que se paga tiene que ser algo sobre lo que puedas **actuar**
(declarar, facturar) y en lo que puedas **confiar**. Para cruzar esa línea necesitaría un **motor fiscal
preciso por línea** (reglas de deducibilidad, exenciones, inversión del sujeto pasivo…), **facturación /
factura electrónica**, una **conexión bancaria real** (open banking) e **integración de presentación /
gestor** — validado antes con usuarios reales.

El objetivo de este proyecto nunca fue facturar: es demostrar que sé construir **software de negocio
serio** en la intersección entre desarrollo web y finanzas. Entender esa distinción forma parte de ello.

### Stack tecnológico

- **Backend:** PHP · Symfony · PostgreSQL
- **Frontend:** React
- **IA:** Anthropic Claude (categorización + consultas en lenguaje natural)

### Ejecutar en local

Requisitos: PHP, Composer, Symfony CLI, PostgreSQL y Node.js (ver
[guía 00](docs/guide/00-environment.md)). Después:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-dev.ps1
```

Esto arranca PostgreSQL, la API Symfony (`:8000`) y el servidor de Vite; luego abre
**http://localhost:5173**. La primera vez, instala dependencias (`cd backend; composer install`
y `cd frontend; npm install`) y crea la base de datos (ver [guía 03](docs/guide/03-database-and-entities.md)).

> Los servidores solo responden mientras están en marcha — si la página dice *connection refused*,
> arráncalos.

### Documentación

| Documento | Qué es |
|---|---|
| [docs/ROADMAP.md](docs/ROADMAP.md) | Plan por fases, del MVP al producto completo |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Diseño del sistema y modelo de dominio |
| [docs/DEVLOG.md](docs/DEVLOG.md) | Diario de cada paso dado |
| [docs/decisions/](docs/decisions/) | Registros de Decisiones de Arquitectura (ADR) |
