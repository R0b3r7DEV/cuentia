# 02 — React frontend · Frontend React

Goal / Objetivo: create a React app (Vite) that calls `/api/health` and shows "API OK".

---

## 1. Create the React app

```powershell
npm create vite@latest frontend -- --template react
```

- **EN:** Scaffolds a React project in `frontend/` using **Vite** (a fast dev server + build tool).
  `--template react` picks plain React with JavaScript.
- **ES:** Genera un proyecto React en `frontend/` usando **Vite** (servidor de desarrollo rápido +
  herramienta de build). `--template react` elige React con JavaScript.

## 2. Install dependencies

```powershell
cd frontend
npm install
```

- **EN:** Downloads React, Vite and the plugins into `node_modules/` (git-ignored).
- **ES:** Descarga React, Vite y los plugins en `node_modules/` (ignorado por git).

## 3. Configure the dev proxy (avoid CORS)

File: `vite.config.js`

```js
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: { '/api': 'http://127.0.0.1:8000' },
  },
})
```

- **EN:** The frontend runs on port **5173** and the backend on **8000** — different origins, which
  normally triggers a **CORS** block in the browser. The proxy makes Vite forward any `/api/*` request
  to the backend, so the browser only ever talks to Vite (same origin). The frontend can then use
  relative URLs like `/api/health` and it "just works" in dev.
- **ES:** El frontend corre en el puerto **5173** y el backend en **8000** — orígenes distintos, lo que
  normalmente provoca un bloqueo **CORS** en el navegador. El proxy hace que Vite reenvíe cualquier
  petición `/api/*` al backend, así el navegador solo habla con Vite (mismo origen). El frontend puede
  usar URLs relativas como `/api/health` y funciona en desarrollo sin más.

## 4. Call the API from React

File: `src/App.jsx` (the key part)

```jsx
useEffect(() => {
  fetch('/api/health')
    .then((res) => { if (!res.ok) throw new Error(`HTTP ${res.status}`); return res.json() })
    .then(setHealth)
    .catch((err) => setError(err.message))
}, [])
```

- **EN:** `useEffect(..., [])` runs once after the component mounts. `fetch('/api/health')` calls the
  backend (through the proxy). We store the result in state and render "✅ API OK", or an error message
  if it fails. This is the standard React pattern for loading data from an API.
- **ES:** `useEffect(..., [])` se ejecuta una vez cuando el componente se monta. `fetch('/api/health')`
  llama al backend (a través del proxy). Guardamos el resultado en el estado y mostramos "✅ API OK", o
  un mensaje de error si falla. Es el patrón estándar de React para cargar datos de una API.

## 5. Run both servers and verify

Two terminals (backend + frontend):

```powershell
# terminal 1 — backend
cd backend ; php -S 127.0.0.1:8000 -t public public/index.php

# terminal 2 — frontend
cd frontend ; npm run dev      # opens http://localhost:5173
```

Open `http://localhost:5173` → you should see **"✅ API OK — cuentia-api (ok)"**.

- **EN:** Both servers run at once; the React page loads from Vite and its `fetch` is proxied to the
  Symfony backend. Seeing the green message proves the **frontend ↔ backend** link works end to end.
- **ES:** Ambos servidores corren a la vez; la página React se carga desde Vite y su `fetch` se
  reenvía por proxy al backend Symfony. Ver el mensaje verde demuestra que el enlace
  **frontend ↔ backend** funciona de punta a punta.

> 💡 **Gotcha (real one we hit) / Detalle real que nos pasó:** modern Vite listens on `localhost`,
> which on Windows resolves to IPv6 (`::1`). Testing with `http://127.0.0.1:5173` (IPv4) failed to
> connect, while `http://localhost:5173` worked. Use `localhost` (or run Vite with `--host`).
> ES: Vite escucha en `localhost`, que en Windows resuelve a IPv6 (`::1`). Probar con
> `http://127.0.0.1:5173` (IPv4) fallaba; con `http://localhost:5173` funcionaba.

---

**Phase 0 is complete / La Fase 0 está completa.** Next: the domain model (`Transaction`, `Category`),
database migrations and the CSV import — the start of Phase 1.
