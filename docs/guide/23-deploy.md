# 23 — Live deploy · Despliegue en vivo

Goal / Objetivo: put Cuentia online **for free** — **frontend on Vercel**, **backend on Render**
(Docker), **PostgreSQL on Neon**. (Railway/Koyeb/Fly work too — the Dockerfile is host-agnostic.)

> Architecture: the frontend proxies `/api/*` to the backend (via `vercel.json`), so the browser only
> talks to one origin — **no CORS, first-party session cookies**. / El frontend hace de proxy de `/api/*`
> al backend (vía `vercel.json`), así el navegador habla con un solo origen — **sin CORS, cookies de
> sesión de primera parte**.

---

## Prepared files (already in the repo)

| File | Purpose |
|---|---|
| `backend/Dockerfile` | Apache + PHP 8.4 image; listens on `$PORT`; runs migrations on boot |
| `render.yaml` | Render blueprint (one-click free web service) |
| `backend/public/.htaccess` | Front-controller rewrite (symfony/apache-pack) |
| `backend/config/packages/framework.yaml` | `when@prod`: trusted proxies + secure cookies |
| `frontend/vercel.json` | SPA routing + `/api` proxy to the backend |

---

## 1. Database — Neon (free, doesn't pause)

1. Create a free project at [neon.tech](https://neon.tech).
2. Copy its connection string and put it in Doctrine format (add `serverVersion` + `charset`):

```
DATABASE_URL="postgresql://USER:PASSWORD@HOST/DBNAME?sslmode=require&serverVersion=16&charset=utf8"
```

- **ES:** Crea un proyecto gratis en [neon.tech](https://neon.tech) y copia la URL en formato Doctrine.
  Neon **no se pausa** como Supabase, así que la demo sigue viva semanas después. (Supabase también vale si
  prefieres reusarlo.)

## 2. Backend — Render (free Docker web service)

1. At [render.com](https://render.com) → **New → Blueprint** → select the `cuentia` repo. Render reads
   `render.yaml` and creates the `cuentia-api` web service (free plan) from `backend/Dockerfile`.
2. Set the two secret env vars it asks for:

   | Variable | Value |
   |---|---|
   | `DATABASE_URL` | your Neon URL (step 1) |
   | `ANTHROPIC_API_KEY` | *(optional)* enables AI categorization + chat |

   (`APP_ENV`, `APP_DEBUG`, `TRUSTED_PROXIES` and a random `APP_SECRET` are set by the blueprint.)
3. Deploy. On boot the container **runs the migrations** automatically. Copy the service URL
   (e.g. `https://cuentia-api.onrender.com`).
4. Create your accounts from the Render shell (Service → **Shell**):

   ```bash
   php bin/console app:create-user admin@yourdomain.com "a-strong-password" --admin
   php bin/console app:create-user demo@yourdomain.com "demo-password"
   ```

> **Free-tier cold start:** Render free services sleep after ~15 min idle and take ~30–60 s to wake on the
> next request. Fine for a portfolio demo — just mention it, or ping the URL before showing it.
> ES: **Arranque en frío:** los servicios free de Render se duermen tras ~15 min inactivos y tardan
> ~30–60 s en despertar. Para una demo de portfolio es aceptable — menciónalo o "despierta" la URL antes.
>
> No card? **Koyeb** (free nano service, Docker) is an alternative; the same `backend/Dockerfile` works.
> ES: ¿Sin tarjeta? **Koyeb** (servicio nano free, Docker) es una alternativa con el mismo Dockerfile.

## 3. Frontend on Vercel

1. In `frontend/vercel.json`, replace `REPLACE-WITH-YOUR-BACKEND-URL` with your Render host, commit and push:

   ```json
   { "source": "/api/:path*", "destination": "https://cuentia-api.onrender.com/api/:path*" }
   ```

2. Create a project at [vercel.com](https://vercel.com) → **Import** `cuentia` → set **Root Directory** =
   `frontend` (framework preset **Vite** is auto-detected). Deploy.
3. Open the Vercel URL → the login screen. Log in with the account you created, or register and click
   **Load sample data**.

## 4. Verify

- **EN:** Register/login works, importing a CSV works, the dashboard/VAT/IRPF/forecast/chat all load. The
  session cookie is set on the Vercel domain (first-party) thanks to the proxy.
- **ES:** Registro/login funciona, importar un CSV funciona, y el panel/IVA/IRPF/previsión/chat cargan. La
  cookie de sesión se pone en el dominio de Vercel (primera parte) gracias al proxy.

## Troubleshooting

- **401 after login / cookie not kept** → the `/api` rewrite in `vercel.json` isn't pointing at the backend,
  or `APP_ENV` isn't `prod`. The proxy is what keeps cookies first-party.
- **500 on first request** → check `DATABASE_URL`; watch the Render deploy logs for the migration step.
- **First load is slow** → free-tier cold start (the backend was asleep); it wakes in ~30–60 s.
- **AI features return a "summary"/rules only** → set `ANTHROPIC_API_KEY` on Render.

> Not tested from this machine (no Docker/cloud here). These files are a solid starting point; we'll debug
> together against the real logs on the first deploy. / No probado desde esta máquina (sin Docker/nube). Son
> un punto de partida sólido; lo depuramos juntos con los logs reales en el primer despliegue.

---

**This is the final planned step.** Once live, add the URL + screenshots to the project README and pin the
repo on your profile. / **Es el último paso planeado.** Ya en vivo, añade la URL + capturas al README y fija
el repo en tu perfil.
