# 23 — Live deploy · Despliegue en vivo

Goal / Objetivo: put Cuentia online — **frontend on Vercel**, **backend on Railway**, **PostgreSQL**.

> Architecture: the frontend proxies `/api/*` to the backend (via `vercel.json`), so the browser only
> talks to one origin — **no CORS, first-party session cookies**. / El frontend hace de proxy de `/api/*`
> al backend (vía `vercel.json`), así el navegador habla con un solo origen — **sin CORS, cookies de
> sesión de primera parte**.

---

## Prepared files (already in the repo)

| File | Purpose |
|---|---|
| `backend/Dockerfile` | Apache + PHP 8.4 image; runs migrations on boot |
| `backend/public/.htaccess` | Front-controller rewrite (symfony/apache-pack) |
| `backend/config/packages/framework.yaml` | `when@prod`: trusted proxies + secure cookies |
| `frontend/vercel.json` | SPA routing + `/api` proxy to the backend |

---

## 1. Database (PostgreSQL)

Use your existing **Supabase** project (or add a PostgreSQL plugin in Railway). Grab the connection URL, in
Doctrine format:

```
DATABASE_URL="postgresql://USER:PASSWORD@HOST:5432/DBNAME?serverVersion=16&charset=utf8"
```

- **ES:** Usa tu proyecto **Supabase** (o añade PostgreSQL en Railway). Copia la URL de conexión en
  formato Doctrine.

## 2. Backend on Railway

1. Create a project at [railway.app](https://railway.app) → **Deploy from GitHub repo** → pick `cuentia`.
2. In the service settings, set **Root Directory** = `backend` (Railway will use `backend/Dockerfile`).
3. Add **environment variables**:

   | Variable | Value |
   |---|---|
   | `APP_ENV` | `prod` |
   | `APP_DEBUG` | `0` |
   | `APP_SECRET` | a random hex string — generate: `php -r "echo bin2hex(random_bytes(16));"` |
   | `DATABASE_URL` | your PostgreSQL URL (step 1) |
   | `TRUSTED_PROXIES` | `REMOTE_ADDR` |
   | `ANTHROPIC_API_KEY` | *(optional)* enables AI categorization + chat |

4. Deploy. On boot the container **runs the migrations** automatically. Copy the public backend URL
   (e.g. `https://cuentia-production.up.railway.app`).
5. Create your accounts from the Railway shell (Service → Shell/Command):

   ```bash
   php bin/console app:create-user admin@yourdomain.com "a-strong-password" --admin
   php bin/console app:create-user demo@yourdomain.com "demo-password"
   ```

## 3. Frontend on Vercel

1. In `frontend/vercel.json`, replace `REPLACE-WITH-YOUR-BACKEND-URL` with your Railway host (no protocol
   in the host part is fine; keep `https://`), commit and push:

   ```json
   { "source": "/api/:path*", "destination": "https://cuentia-production.up.railway.app/api/:path*" }
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
- **500 on first request** → check `DATABASE_URL`; watch the Railway deploy logs for the migration step.
- **AI features return a "summary"/rules only** → set `ANTHROPIC_API_KEY` on Railway.

> Not tested from this machine (no Docker/cloud here). These files are a solid starting point; we'll debug
> together against the real logs on the first deploy. / No probado desde esta máquina (sin Docker/nube). Son
> un punto de partida sólido; lo depuramos juntos con los logs reales en el primer despliegue.

---

**This is the final planned step.** Once live, add the URL + screenshots to the project README and pin the
repo on your profile. / **Es el último paso planeado.** Ya en vivo, añade la URL + capturas al README y fija
el repo en tu perfil.
