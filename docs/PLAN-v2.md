# PLAN v2 — Cuentia: vertical de electricista + facturación para uso real

> Documento de trabajo para revisión del owner. Escrito en español (audiencia: el owner y el autónomo
> piloto). Los artefactos duraderos que produzca (ADR en `docs/decisions/`, guías en `docs/guide/`) se
> escriben **bilingües** como el resto, según la convención del repo.
>
> **Estado: BORRADOR — pendiente de aprobación. No se escribe código hasta el visto bueno.**

---

## 0. Principios invariantes (aplican a TODAS las tareas)

- **Dinero en céntimos enteros**; los totales se persisten como strings decimales. Nunca `float`.
- **Numeración de facturas sin huecos** por serie (`InvoiceRepository::nextNumber`).
- **La cadena de hash Verifactu no se rompe.** La huella (`VerifactuHasher::fingerprint`) depende solo de:
  `issuerNif, fullNumber, issueDate, vatTotal, total, generatedAt, previousHash`. Ninguna tarea nueva puede
  añadir campos a esa huella. Toda migración que toque datos de facturación incluye un test que ejecuta
  `GET /api/invoices/verify` sobre facturas preexistentes y exige `ok:true`.
- **PHPUnit para todo servicio nuevo.** CI (`.github/workflows/ci.yml`, 3 jobs) verde en cada PR.
- **Una feature = una rama = un PR** con descripción, migración Doctrine y documentación actualizada.
- **UX para no técnicos, en español**, vía `frontend/src/i18n/translations.js`. Sin jerga de desarrollo.
  Cada pantalla nueva debe ser usable a pie de obra desde el móvil por alguien sin perfil técnico.

## 1. Hallazgos de la revisión del código (lo que se reutiliza y lo que hay que arreglar)

**Se reutiliza tal cual:**
- Patrón de **conversión idempotente** `QuoteService::convert()` → `WorkOrderService::convert()` (P1). La clave:
  un FK `convertedInvoice` en el origen; si ya existe, se devuelve sin duplicar.
- `InvoiceService::create()` como única puerta de emisión (numeración + cadena de hash + totales).
- Patrón PDF `QuotePdf`/`InvoicePdf` (Dompdf, PHP puro) → `WorkOrderPdf` (P1), medidas en `CiePdf` (P2).
- Filosofía del **panel de cumplimiento** (`InstallationCalculator::validateLayout` + `CompliancePanel.jsx`):
  bloquear la emisión y nombrar exactamente qué falta → se replica en las mediciones del CIE (P2).
- Cifrado por usuario (`SecretCipher`) y BYOK como precedente de campos por usuario.

**⚠️ Dos avisos (reportados antes de tocar nada, según método de trabajo):**

- **A1 — Toda factura lleva hoy QR de pruebas.** `InvoiceService::create()` crea siempre un `InvoiceRecord`,
  e `InvoicePdf::html()` imprime el QR de la AEAT y la leyenda «entorno de pruebas — demostración» *siempre
  que hay registro*. Una factura real del piloto saldría con un QR que apunta al host de PRUEBAS de la AEAT.
  **Esto lo arregla el P0.** No es una contradicción normativa, es un riesgo de uso real.
- **A2 — Falta el perfil fiscal del emisor.** `User` tiene `email` + `taxId`, pero **ni nombre ni domicilio
  fiscal**. El **RD 1619/2012 (art. 6)** exige en factura ordinaria: nombre y apellidos/razón social del
  emisor, NIF y domicilio. `InvoicePdf` hoy imprime el *email* como emisor. El P0 debe añadir el perfil
  fiscal del emisor o la factura «estándar» no será conforme. Se incluye en el alcance del P0.
- **A3 — Fecha de obligación Verifactu: a verificar contra el BOE.** El enunciado cita julio-2027 para
  autónomos; mi conocimiento apunta a fechas anteriores (RD 254/2025) que se han ido aplazando. **No lo
  afirmo de memoria.** Al redactar el ADR 0004 se cita el RD y la fecha exactos del BOE vigente. El diseño
  (estándar por defecto, Verifactu opt-in demo) es correcto **sea cual sea** la fecha, porque la factura
  ordinaria es válida hoy.

---

## P0 — Modo dual de facturación (estándar / Verifactu demo)  ·  Complejidad: **M**

**Objetivo:** que el piloto emita facturas ordinarias válidas (RD 1619/2012) por defecto, sin QR ni XML ni
leyenda Verifactu, manteniendo **la cadena de hash interna activa** (integridad + numeración sin huecos) en
ambos modos. El modo Verifactu queda como demostración claramente marcada, sin validez fiscal.

### Backend
- **`User`**: nuevo campo `billingMode` (`'standard'` | `'verifactu'`, default `'standard'`). Migración con
  `DEFAULT 'standard' NOT NULL` (Postgres rellena las filas existentes → segura, sin el fallo de la columna
  NOT NULL sin default). Nuevos campos de perfil emisor: `businessName`, `fiscalAddress` (nullable).
- **`InvoiceService`**: **sin cambios en la emisión** — se sigue creando el `InvoiceRecord` siempre (la
  cadena vive en los dos modos). La huella no cambia → la cadena no se rompe.
- **`InvoicePdf::build()`**: nuevo parámetro `bool $showVerifactu`. El bloque QR + leyenda solo se renderiza
  si es `true`. En estándar: factura RD 1619/2012 con los campos obligatorios (nº, fecha, emisor
  nombre+NIF+domicilio, cliente, base, tipo y cuota de IVA, total) y **sin** QR/leyenda.
- **`InvoiceController`**: `pdf()` decide `showVerifactu = ($user->getBillingMode() === 'verifactu')`. Los
  endpoints `/qr` y `/xml` devuelven **403** (o 404) si el usuario está en modo estándar (no se exponen
  artefactos de demostración en uso real). `detail()` sigue devolviendo el bloque `verifactu` para la UI,
  pero la UI lo oculta en modo estándar.
- **`AccountController`**: exponer y permitir cambiar `billingMode` + perfil emisor (PATCH).

### Frontend (ES, no técnico)
- **Cuenta → Facturación**: selector claro «Modo de facturación»:
  - *Estándar (recomendado)* — «Facturas normales, válidas para tus clientes.»
  - *Verifactu (demostración)* — «Solo para probar el formato antifraude. **Sin validez fiscal.**»
- **Formulario perfil emisor** (nombre fiscal / razón social + domicilio) con aviso si falta al facturar.
- En modo estándar: **ocultar** botones de QR y XML; sin badge Verifactu. En modo Verifactu: badge naranja
  «DEMO — sin validez fiscal» junto a la factura y en el PDF.

### Tests (PHPUnit)
- Estándar: el PDF **no** contiene QR ni la leyenda Verifactu; el `InvoiceRecord` se crea igual y
  `GET /api/invoices/verify` → `ok:true`.
- Verifactu: el PDF **sí** contiene el QR.
- `/qr` y `/xml` → 403 en modo estándar; 200 en Verifactu.
- **Test de cadena sobre datos preexistentes**: crear N facturas, cambiar de modo, verificar `ok:true`
  (la huella no depende del modo).

### Docs
- **ADR 0004 — Modo dual de facturación** (bilingüe): por qué estándar por defecto, por qué la cadena sigue
  activa en ambos, cita exacta RD 1619/2012 y fecha de obligación Verifactu del BOE vigente (ver A3).
- **Guía 38** — cómo funciona el modo dual y cómo cambia el piloto de modo.

### Criterios de aceptación
Un usuario nuevo emite una factura y descarga un PDF **sin QR ni leyenda**, con su nombre y domicilio
fiscales; la verificación de la cadena sigue en verde; puede activar el modo demo y ver el QR.

---

## P1 — Partes de trabajo (work orders)  ·  Complejidad: **XL**

**Objetivo:** cubrir el 80% real del electricista (avisos y reparaciones), rellenable a pie de obra desde el
móvil, con firma del cliente y conversión idempotente a factura.

### Backend
- **`WorkOrder`**: `user` FK, `customer` FK, `title`, `description`, `status`
  (`pendiente→en_curso→terminado→facturado`), `scheduledAt`, `laborHours` (decimal), `hourlyRate` (céntimos,
  configurable; default en perfil), `signaturePng` (nullable), `convertedInvoice` FK (idempotencia),
  timestamps.
- **`WorkOrderLine`**: espejo de `InvoiceLine` (description, quantity, unitPrice, vatRate), alimentada desde
  el catálogo de servicios existente.
- **`WorkOrderPhoto`**: fotos subidas desde el móvil. **Decisión de almacenamiento → ADR 0005** (ver deuda
  técnica): para el piloto, JPEG **reducido en cliente** (patrón `downscale` del editor de planos) guardado
  como `bytea` en Postgres, con tope de tamaño y de número de fotos. Limitación conocida: hincha la BD;
  migrar a almacenamiento de objetos (S3/R2) cuando haya más de un usuario.
- **`WorkOrderService`**: `create`, `updateStatus`, y `convert(User, WorkOrder)` **idempotente** replicando
  `QuoteService::convert()` — materiales → líneas de factura, mano de obra → línea «Mano de obra»
  (`laborHours × hourlyRate`); marca el parte `facturado` y enlaza `convertedInvoice`. Convertir dos veces
  devuelve la misma factura.
- **`WorkOrderPdf`**: parte firmado (datos, materiales, horas, fotos en miniatura, imagen de firma), patrón
  `QuotePdf`.
- **`WorkOrderController`**: CRUD + `/convert` + `/pdf` + subida/borrado de fotos. Todo acotado al `User`.

### Frontend (móvil primero)
- Nueva pestaña **«Partes»** (o página propia). Lista con estado por colores.
- Formulario a pie de obra: cliente, descripción, añadir materiales del catálogo, horas, **cámara**
  (`<input type="file" accept="image/*" capture>`), **firma en canvas táctil** (dedo).
- Botón «Convertir en factura» (reusa el flujo de traspaso ya existente).
- Diseñado móvil primero; táctil, textos claros en español.

### Tests
- `WorkOrderService`: create, transiciones de estado, **convert idempotente** (dos veces → una factura, sin
  duplicar), mapeo materiales+mano de obra, aislamiento por usuario. Integración: subida de foto saneada
  (tipo/imagen/tamaño), `/pdf`.

### Docs
- ADR 0005 (almacenamiento de fotos), Guía 39 (ciclo del parte), DEVLOG.

### Criterios de aceptación
El piloto crea un parte en el móvil, añade materiales y una foto, recoge la firma del cliente, lo marca
terminado, y lo convierte en factura una sola vez.

---

## P2 — Registro de mediciones en el CIE  ·  Complejidad: **L**

**Objetivo:** que el CIE exija y valide mediciones reales, no solo puntos de uso (ITC-BT-25), y las imprima.

### Investigación normativa PRIMERO (leer el reglamento, no un resumen — como en la guía 37)
Fuentes a transcribir del BOE / Guías Técnicas del Ministerio antes de fijar rangos:
- **Resistencia de puesta a tierra**: ITC-BT-18 y ITC-BT-24 — condición `R_a · I_Δn ≤ U_L` (24 V locales
  húmedos / 50 V resto).
- **Resistencia de aislamiento**: ITC-BT-19 (tabla) — mínimo en MΩ según tensión, medida a la tensión de
  ensayo indicada.
- **Disparo del diferencial**: tiempo/corriente según UNE-EN 61008/61009 e ITC-BT-24.
- **Continuidad de conductores de protección**: ITC-BT-18/19.

> No se fija ningún rango «de memoria». Se documenta cada valor y su fuente exacta en la Guía 40, igual que
> se hizo con la GUÍA-BT-25 en la guía 37.

### Backend
- Extender `Certificate` con las mediciones (columna `measurements` JSON o campos): aislamiento (MΩ), tiempo
  de disparo (ms) y corriente de disparo (mA) del diferencial, continuidad (Ω/booleano). (Ya existen
  `earthResistance` y `earthConductorSection`.)
- **`MeasurementValidator`** (servicio puro, testeado): valida cada valor contra el rango REBT y devuelve la
  lista de incumplimientos, con la misma forma que `validateLayout` (nombre exacto de lo que falla).
- El endpoint de emisión/PDF del CIE **bloquea** si hay mediciones fuera de rango.
- **`CiePdf`**: imprime la tabla de mediciones.

### Frontend
- Checklist de mediciones en el flujo del certificado, con panel de cumplimiento (verde/rojo por medición),
  reutilizando el estilo de `CompliancePanel`.

### Tests
- `MeasurementValidator`: dentro/fuera de rango por cada magnitud; CIE bloqueado cuando falla; el PDF incluye
  las mediciones.

### Docs
- Guía 40 (mediciones + fuentes normativas exactas). Actualizar guía 37 con el enlace.

---

## P3 — PWA con modo offline  ·  Complejidad: **XL (la más arriesgada)**

**Objetivo:** partes (P1) y editor de planos usables sin cobertura (garajes, sótanos).

### Frontend
- PWA vía `vite-plugin-pwa` (Workbox): `manifest.webmanifest` + iconos + service worker.
- **Offline-first** para partes y editor de planos: datos en **IndexedDB** (`idb`), **cola de sincronización**
  con resolución **last-write-wins** e **indicador visual** «pendiente de sincronizar».
- La lógica de la cola (pura) se testea con Vitest (el SW en sí no).

### Docs
- **ADR 0006 — estrategia de caché offline y sus límites** (qué se cachea, qué no, conflictos LWW, purga).

> Riesgo: infraestructura frontend nueva y difícil de testear end-to-end. Se puede partir en 2 PRs
> (1: PWA instalable + shell cacheado; 2: cola de sincronización de partes). Recomiendo hacerla **después**
> de P4 si se prefiere valor entregable antes que infraestructura — pero se respeta el orden que decidas.

---

## P4 — Tarifas de material del proveedor  ·  Complejidad: **L**

**Objetivo:** presupuestos con precios reales de tarifa y margen configurable.

### Backend
- **`Tariff`** por usuario: `code`, `description`, `costPrice` (céntimos), `discount`.
- Importación **CSV/XLSX**. XLSX requiere `phpoffice/phpspreadsheet` (dependencia nueva, pesada →
  decisión a confirmar; alternativa: solo CSV en el primer PR y XLSX después).
- **`MaterialCategory`** con margen por categoría: `precioVenta = coste × (1 + margen)`.
- Al importar, detectar líneas de **presupuestos abiertos** cuyo material cambió de precio → aviso.

### Frontend
- Pantalla de importación (subir CSV/XLSX, previsualizar, confirmar), configuración de márgenes por
  categoría, y aviso en presupuestos abiertos.

### Tests
- Parseo de import (CSV y XLSX), cálculo de margen, detección de cambios de precio.

### Docs
- Guía 41, ADR si entra phpspreadsheet.

---

## P5 — Nuevos verticales REBT (SOLO diseño)  ·  Complejidad: **S**

Documento **propuesta** en `docs/decisions/` (ADR 0007, estado *Proposed*), sin implementar: puntos de
recarga de VE (**ITC-BT-52**) y autoconsumo fotovoltaico ≤10 kW, con los **trámites telemáticos de la GVA**
para cada uno. Se entrega para decidir juntos.

---

## Orden de PRs, complejidad y dependencias

| # | PR | Prioridad | Complejidad | Depende de | Migración | Docs |
|---|----|-----------|-------------|------------|-----------|------|
| 1 | Modo dual de facturación + perfil emisor | P0 | M | — | sí (User) | ADR 0004, guía 38 |
| 2 | Partes de trabajo (entidad + servicio + convert) | P1 | L | PR1 | sí | guía 39 |
| 3 | Partes: fotos + firma + PDF + UI móvil | P1 | L | PR2 | sí (fotos) | ADR 0005 |
| 4 | Mediciones CIE + validador + PDF | P2 | L | — | sí (Certificate) | guía 40 |
| 5 | PWA instalable + shell offline | P3 | L | — | no | ADR 0006 |
| 6 | Cola de sincronización de partes | P3 | L | PR3, PR5 | no | — |
| 7 | Tarifas de material + márgenes + import | P4 | L | — | sí | guía 41 |
| 8 | Propuesta verticales VE + FV | P5 | S | — | no | ADR 0007 |

> P1 se parte en dos PRs (lógica primero, media+UI después) para PRs revisables. Se implementa **P1 completo
> (PR2+PR3) antes de tocar P2**, según el método de trabajo.

## Riesgos y deuda técnica anticipada

- **Almacenamiento de fotos (P1):** `bytea` en Postgres hincha la BD y no escala; aceptable para 1 piloto,
  a migrar a S3/R2. Documentado en ADR 0005.
- **PWA/offline (P3):** conflictos con LWW pueden perder datos si dos dispositivos editan el mismo parte;
  aceptable para un usuario, se documenta el límite.
- **phpspreadsheet (P4):** dependencia pesada; evaluar CSV-solo primero.
- **Perfil emisor (P0):** es el mínimo para RD 1619/2012; un emisor con recargo de equivalencia u otras
  particularidades queda fuera del primer alcance.
- **Fecha obligación Verifactu (A3):** se cita del BOE al redactar ADR 0004; no se afirma de memoria.

## Qué necesito de ti para arrancar

1. **Visto bueno al plan** (o ajustes).
2. Confirmar el **orden** (¿P3 antes o después de P4?).
3. Para P4: ¿**CSV-solo** en el primer PR o metemos ya **phpspreadsheet** para XLSX?
4. ¿Creo este `PLAN-v2.md` y cada feature en **ramas** con PR, o trabajamos sobre `master` para el piloto?
