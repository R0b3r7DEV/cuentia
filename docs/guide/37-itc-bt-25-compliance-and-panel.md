# 37 — Checking a plan against ITC-BT-25, and drawing the panel · Comprobar el plano y dibujar el cuadro

Goal / Objetivo: stop a floor plan that falls short of the **minimum points of use** from becoming a
certificate, and derive the **main panel (CGMP)** from the devices actually drawn.

*Objetivo: impedir que un plano por debajo de los **puntos mínimos de utilización** se convierta en
certificado, y derivar el **cuadro general (CGMP)** de los dispositivos realmente dibujados.*

---

## Go to the source / Ir a la fuente

The app already computed "minimum points per room" — from a rule someone invented: *two lights if the room
is over 10 m², three or four sockets*. It looked plausible. It was wrong.

The real table is **Tabla 2 of ITC-BT-25** (RD 842/2002), reproduced in the Ministry's
[Guía Técnica de Aplicación GUÍA-BT-25](https://industria.gob.es/Calidad-Industrial/seguridadindustrial/instalacionesindustriales/baja-tension/Documents/bt/guia_bt_25_jul12R2.pdf)
(jul-12, rev. 2). Downloading the PDF and reading the table found **five** discrepancies:

| Room | What we had | What the regulation says |
|---|---|---|
| Cocina | **no sockets at all**, only C5 | C2×2 (extractor, frigorífico), C3×1 (25 A cocina/horno), C4×3 (lavadora, lavavajillas, termo), C5×3 (encima del plano de trabajo) |
| Salón / dormitorio | 3 sockets, 4 if > 10 m² | **3 as a floor**, "una por cada 6 m² redondeado al entero superior" — a 25 m² living room needs 5 |
| Pasillo | points from **surface** | ruled by **length**: a light every 5 m, a second socket beyond 5 m |
| Terraza | 1 socket | light and switch, **no socket** |
| Grade | > 5 circuits ⇒ *electrificación elevada* | > 5 circuits ⇒ **an extra differential**. Splitting circuits "no supondrá el paso a electrificación elevada" (2.3.1) |

That last one was not cosmetic: it raised the contracted power from 5 750 W to 9 200 W with no cause — a
real bill for the end customer.

*La app ya calculaba "puntos mínimos por estancia", con una regla inventada. La tabla real es la **tabla 2
de la ITC-BT-25**, y al leerla aparecieron cinco desviaciones. La última no era cosmética: subía la potencia
contratada de 5 750 W a 9 200 W sin motivo.*

The eight triggers for *electrificación elevada* are: surface > 160 m²; foreseen A/C, electric heating,
home automation or a tumble dryer; more than 30 lighting points; more than 20 general-purpose sockets; more
than 6 C5 sockets.

## Which circuit feeds a socket / De qué circuito cuelga una toma

Counting "sockets" is not enough: a kitchen needs nine of them, on **four different circuits**. So a socket
now declares its circuit (`C2`, `C3`, `C4`, `C5`, `C10`), chosen when it is placed and drawn on the plan.

A socket that declares none is **not** treated as a violation. It is credited against whatever its room
still lacks, most specific circuit first — the benefit of the doubt, so a plan saved before this feature
existed is never accused of a shortfall it may not have. But a socket that explicitly says `C2` does *not*
satisfy the `C5` a bathroom requires. Generosity where the data is absent, none where it contradicts.

*Contar "enchufes" no basta: una cocina necesita nueve, en cuatro circuitos distintos. Una toma sin circuito
se imputa a lo que a su estancia le falte; una que declara C2 no vale para el C5 de un baño.*

## The check / La comprobación

`InstallationCalculator::validateLayout()` attributes each device to the room whose **polygon contains it**
(ray casting), measures the room by the **shoelace formula** — so an L-shaped living room counts by its real
surface, not by its bounding box — and compares against `roomRequirements()`.

Every shortfall is named: *"cocina · faltan 3 · base 16 A 2p+T (lavadora, lavavajillas, termo) (0/3)"*.
While a shortfall exists, the **Create CIE button is disabled**.

An empty plan is reported as `checked: false` — **unchecked, not compliant**. Nothing drawn is not proof of
anything, and a validator that says "OK" to an empty input is worse than no validator.

## The panel / El cuadro

`panelSchedule()` derives the **CGMP** from the devices on the plan rather than from the theoretical
minimums:

- circuits sized by the points **really connected**, split when they exceed the maximum of tabla 1
  (C1: 30 points, C2: 20, C5: 6, C3: 2, C4: 3);
- the **IGA** rated from the contracted power (5 750 → 25 A, 9 200 → 40 A…, per §2.1);
- **one differential per five circuits**;
- **DIN modules** counted so the enclosure can be ordered. One differential plus its five circuits is
  exactly 12 modules — one row.

Comparing the single-line diagram (theoretical) with the panel (drawn) is informative on its own: more
circuits in the panel means more points placed than the minimum.

### One tally, two readers / Un conteo, dos lectores

Both the compliance check and the panel read from a single private `tally()`. This is the point of the
refactor: if each counted on its own, they would eventually disagree — the panel telling you the plan
complies while the board wires a circuit that isn't there. Now they **cannot** disagree about what is
installed.

*La comprobación y el cuadro leen de un único `tally()`. Si contase cada uno por su cuenta, acabarían
discrepando, y ese tipo de fallo es infernal de perseguir.*

## Honest scope / Alcance honesto

A plan can only prove the **number of points of use**. A certificate also rests on conductor sections,
earthing, insulation and continuity measurements, the protections actually fitted, and the works as built —
and it is signed by an authorised installer, who answers for it.

What this closes is one whole family of rejections — *"missing sockets"* — the commonest and the silliest.
The rest is still the installer's. **No software can promise a certificate will never be returned**, and the
app says so on the panel itself.

DIN modules assume 2P / 1P+N devices at two modules each; some manufacturers ship single-module breakers.
The ICP sits in its own sealed compartment and is not counted.

*Un plano solo puede demostrar el número de puntos de utilización. Ningún software puede prometer que un
certificado no será devuelto; lo que se cierra es la familia de rechazos por "faltan tomas".*
