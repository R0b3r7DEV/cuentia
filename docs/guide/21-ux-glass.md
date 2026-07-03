# 21 — UX polish: "liquid glass" + responsive · Pulido UX: glass + responsive

Goal / Objetivo: a modern, iOS-style glass look with chat bubbles, that works on mobile.

---

## The look

- **EN:** A **"liquid glass"** refresh (inspired by iOS 26 / WhatsApp): translucent frosted surfaces
  (`backdrop-filter: blur`), pill shapes, a **floating rounded navbar**, soft shadows and a subtle tinted
  background so the glass has something to blur. Still one restrained brand blue — premium, not flashy.
- **ES:** Un lavado **"liquid glass"** (inspirado en iOS 26 / WhatsApp): superficies translúcidas
  esmeriladas (`backdrop-filter: blur`), formas de píldora, una **navbar flotante redondeada**, sombras
  suaves y un fondo con un tinte sutil para que el glass tenga algo que desenfocar. Sigue con un solo azul
  de marca contenido — premium, no llamativo.

## How it's done (still tokens)

- All the new look lives in `index.css` **design tokens**: `--glass`, `--glass-strong`, `--glass-border`,
  `--blur`, bigger radii and softer shadows, plus light/dark variants. Components didn't change — the same
  `.card`, `.btn`, `.navbar` classes now render as glass.
- **Chat bubbles** use asymmetric radii for a WhatsApp-style tail: the user bubble is accent-colored and
  right-aligned (`border-radius: 20px 20px 6px 20px`), the assistant bubble is a light surface, left-aligned.

```css
.card { background: var(--glass); backdrop-filter: blur(var(--blur)) saturate(160%); border: 1px solid var(--glass-border); border-radius: var(--radius); }
.navbar-inner { border-radius: 999px; backdrop-filter: blur(18px) saturate(180%); } /* floating pill */
.chat-user { background: var(--accent); color: #fff; border-radius: 20px 20px 6px 20px; }
```

## Responsive

- The navbar wraps on small screens; charts stack to one column; cards and paddings shrink; tables get a
  horizontal scroll wrapper (`.table-scroll`) so they never break the layout on mobile.
- **Full dark mode** is preserved — the glass tokens have their own dark values (not an auto-invert).

## Verify

```powershell
npm run build   # compiles
```

Open `http://localhost:5173` — a floating glass navbar, frosted cards, pill buttons, chat bubbles, and a
tinted background. Resize the window (or open on a phone) to see it adapt; toggle your OS dark mode to see
the dark glass.

- **EN:** The design reads as one polished system across light/dark and desktop/mobile — the kind of finish
  that makes a recruiter take the project seriously.
- **ES:** El diseño se lee como un sistema pulido en claro/oscuro y escritorio/móvil — el tipo de acabado
  que hace que un reclutador se tome el proyecto en serio.

---

**Next / Siguiente:** API integration tests, then the live deploy.
/ Tests de integración de la API, y luego el deploy en vivo.
