# Architecture

**Languages:** [English](#english) · [Español](#español)

> This is a living document. It describes the target design; sections are marked *(planned)*
> until implemented.

---

## English

### High-level shape

```
┌──────────────┐      REST/JSON      ┌───────────────────────────┐
│  React app   │ ──────────────────► │   Symfony API (PHP)       │
│  (frontend)  │ ◄────────────────── │                           │
└──────────────┘                     │  Controllers → Services   │
                                     │  ├─ Import (CSV/Norma43)   │
                                     │  ├─ Categorizer (AI + rules)│
                                     │  ├─ Tax (VAT/IRPF)         │
                                     │  └─ Forecast               │
                                     └───────────┬───────────────┘
                                                 │ Doctrine ORM
                                          ┌──────▼───────┐   ┌──────────────┐
                                          │ PostgreSQL   │   │  Claude API  │
                                          └──────────────┘   └──────────────┘
```

**Why this split:** the React frontend is a thin presentation layer; all the value (and all the
finance logic) lives in the Symfony backend as **services** that are unit-testable in isolation.
The AI is one pluggable service among several — never load-bearing on its own.

### Backend layering (planned)

- **Controllers** — thin. Parse the request, call a service, return JSON. No business logic.
- **Services** — where the real work lives:
  - `ImportService` — parse a bank file into `Transaction` entities.
  - `CategorizerService` — assign a `Category` to a transaction (AI, with a rule-based fallback).
  - `TaxService` — VAT and IRPF calculations. **Pure, deterministic, heavily tested.**
  - `ForecastService` — project cash flow from recurring movements.
- **Entities (Doctrine)** — the persisted domain model.
- **Repositories** — queries.

### Domain model (initial)

```
Transaction
  id            int             -- auto-increment primary key
  bookedAt      date            -- value date of the movement
  description   text            -- raw text from the bank
  amount        decimal(12,2)   -- signed: negative = expense, positive = income
  currency      char(3)         -- 'EUR'
  category_id   fk → Category   -- nullable until categorized
  categorySource enum           -- 'ai' | 'rule' | 'manual'
  vatRate       decimal(4,2)    -- nullable; e.g. 21.00 (fills the VAT panel)
  importedFrom  enum            -- 'csv' | 'norma43' | 'openbanking'
  createdAt     timestamptz

Category
  id      int                   -- auto-increment primary key
  name    text                  -- 'Software', 'Suppliers', 'Client income', ...
  kind    enum                  -- 'income' | 'expense'
  color   text
```

> **Money rule (non-negotiable):** all monetary values use exact decimals
> (`decimal`, never floats). This is a hard requirement in accounting software and a classic
> interview question.

### Key principles

1. **Finance logic is pure and tested.** Tax math must be verifiable without a database or an API.
2. **AI is optional.** Every AI feature degrades gracefully to a deterministic fallback.
3. **Spain-first, correct-first.** Better to model one country's tax rules correctly than many vaguely.
4. **Document the *why*.** Non-obvious decisions become ADRs in [`decisions/`](decisions/).

---

## Español

> Documento vivo. Describe el diseño objetivo; las secciones marcadas *(planned/previsto)* aún no
> están implementadas.

### Vista general

```
┌──────────────┐      REST/JSON      ┌───────────────────────────┐
│  App React   │ ──────────────────► │   API Symfony (PHP)       │
│  (frontend)  │ ◄────────────────── │                           │
└──────────────┘                     │  Controladores → Servicios │
                                     │  ├─ Import (CSV/Norma43)   │
                                     │  ├─ Categorizador (IA+reglas)│
                                     │  ├─ Fiscal (IVA/IRPF)      │
                                     │  └─ Previsión              │
                                     └───────────┬───────────────┘
                                                 │ Doctrine ORM
                                          ┌──────▼───────┐   ┌──────────────┐
                                          │ PostgreSQL   │   │  Claude API  │
                                          └──────────────┘   └──────────────┘
```

**Por qué esta separación:** el frontend React es una capa fina de presentación; todo el valor (y toda
la lógica financiera) vive en el backend Symfony como **servicios** testeables de forma aislada. La IA
es un servicio enchufable más, nunca el pilar que sostiene todo.

### Capas del backend (previsto)

- **Controladores** — finos. Parsean la petición, llaman a un servicio y devuelven JSON. Sin lógica de negocio.
- **Servicios** — donde ocurre el trabajo real:
  - `ImportService` — parsea un fichero bancario a entidades `Transaction`.
  - `CategorizerService` — asigna una `Category` a un movimiento (IA, con fallback por reglas).
  - `TaxService` — cálculos de IVA e IRPF. **Puro, determinista, muy testeado.**
  - `ForecastService` — proyecta la tesorería a partir de movimientos recurrentes.
- **Entidades (Doctrine)** — el modelo de dominio persistido.
- **Repositorios** — consultas.

### Modelo de dominio (inicial)

```
Transaction (movimiento)
  id            int             -- auto-increment primary key
  bookedAt      date            -- fecha valor del movimiento
  description   text            -- texto en bruto del banco
  amount        decimal(12,2)   -- con signo: negativo = gasto, positivo = ingreso
  currency      char(3)         -- 'EUR'
  category_id   fk → Category   -- nulo hasta categorizar
  categorySource enum           -- 'ai' | 'rule' | 'manual'
  vatRate       decimal(4,2)    -- nulo; p.ej. 21.00 (alimenta el panel de IVA)
  importedFrom  enum            -- 'csv' | 'norma43' | 'openbanking'
  createdAt     timestamptz

Category (categoría)
  id      int                   -- auto-increment primary key
  name    text                  -- 'Software', 'Proveedores', 'Ingresos de cliente', ...
  kind    enum                  -- 'income' | 'expense'
  color   text
```

> **Regla del dinero (innegociable):** todos los valores monetarios usan decimales exactos
> (`decimal`, nunca coma flotante). Es un requisito duro en software de contabilidad y una pregunta
> clásica de entrevista.

### Principios clave

1. **La lógica financiera es pura y testeada.** El cálculo fiscal debe verificarse sin BD ni API.
2. **La IA es opcional.** Toda función de IA degrada con elegancia a un fallback determinista.
3. **España primero, correcto primero.** Mejor modelar bien las reglas de un país que muchas a medias.
4. **Documentar el *porqué*.** Las decisiones no obvias se convierten en ADRs en [`decisions/`](decisions/).
