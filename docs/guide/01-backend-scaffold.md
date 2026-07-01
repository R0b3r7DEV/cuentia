# 01 — Backend scaffold + first endpoint · Scaffold del backend + primer endpoint

Goal / Objetivo: create the Symfony API and get `GET /api/health` returning 200.

---

## 1. Create the Symfony project

```powershell
symfony new backend
```

- **EN:** Creates a new Symfony project in a `backend/` folder (downloads Symfony + its dependencies
  with Composer). We use a **minimal skeleton** because we're building a JSON API, not a server-rendered
  website — the UI is a separate React app.
- **ES:** Crea un proyecto Symfony nuevo en la carpeta `backend/` (descarga Symfony y sus dependencias
  con Composer). Usamos un **esqueleto mínimo** porque construimos una API JSON, no una web renderizada
  en servidor — la interfaz es una app React aparte.

## 2. Remove the nested git repo

```powershell
Remove-Item backend\.git -Recurse -Force
```

- **EN:** `symfony new` starts its own git repo inside `backend/`. Our project is a **monorepo**
  (backend + frontend + docs in one repo), so we delete the nested `.git` to avoid a repo-inside-a-repo.
- **ES:** `symfony new` crea su propio repo git dentro de `backend/`. Nuestro proyecto es un
  **monorepo** (backend + frontend + docs en un mismo repo), así que borramos el `.git` anidado para
  evitar un repo dentro de otro.

## 3. Understand the structure

```
backend/
├── bin/console      # Symfony's command-line tool
├── config/          # configuration (routes, services, packages)
├── public/          # web root; index.php is the single entry point
├── src/             # our PHP code (Controllers, Entities, Services...)
├── var/             # cache & logs (git-ignored)
└── vendor/          # installed dependencies (git-ignored)
```

- **EN:** Every HTTP request enters through `public/index.php`, Symfony routes it to a **controller**
  method, and that method returns a response. Our code lives in `src/`.
- **ES:** Cada petición HTTP entra por `public/index.php`, Symfony la enruta a un método de un
  **controlador**, y ese método devuelve una respuesta. Nuestro código vive en `src/`.

## 4. Create the health controller

File: `src/Controller/HealthController.php`

```php
#[Route('/api/health', name: 'api_health', methods: ['GET'])]
public function health(): JsonResponse
{
    return $this->json(['status' => 'ok', 'service' => 'cuentia-api']);
}
```

- **EN:** The `#[Route(...)]` **attribute** maps `GET /api/health` to this method. `AbstractController`
  (the base class) gives us helpers like `$this->json()`, which turns a PHP array into a JSON response
  with the correct `Content-Type` header. Symfony auto-discovers controllers in `src/Controller/`
  (see `config/routes.yaml`), so no extra wiring is needed.
- **ES:** El **atributo** `#[Route(...)]` asocia `GET /api/health` a este método. `AbstractController`
  (la clase base) nos da utilidades como `$this->json()`, que convierte un array de PHP en una respuesta
  JSON con la cabecera `Content-Type` correcta. Symfony descubre los controladores de `src/Controller/`
  automáticamente (ver `config/routes.yaml`), así que no hace falta configurar nada más.

## 5. Check the route is registered

```powershell
php bin/console debug:router | Select-String health
#   api_health   GET   /api/health
```

- **EN:** `debug:router` lists every route Symfony knows about. Great for confirming your route exists.
- **ES:** `debug:router` lista todas las rutas que Symfony conoce. Ideal para confirmar que tu ruta existe.

## 6. Run the server and test

The Symfony CLI server is the normal way:

```powershell
symfony server:start        # serves http://127.0.0.1:8000
```

> ⚠️ On this Windows machine the *daemon* mode (`-d`) hit a log-file lock. A reliable alternative is
> PHP's built-in server, using Symfony's front controller as the router:
>
> ```powershell
> php -S 127.0.0.1:8000 -t public public/index.php
> ```

Then, in another terminal:

```powershell
Invoke-RestMethod http://127.0.0.1:8000/api/health
```

Result / Resultado:

```json
{ "status": "ok", "service": "cuentia-api" }
```

- **EN:** A local web server runs the app on port 8000; the request to `/api/health` returns our JSON.
  This is the project's **first green** — proof the whole request→controller→response chain works.
- **ES:** Un servidor web local ejecuta la app en el puerto 8000; la petición a `/api/health` devuelve
  nuestro JSON. Este es el **primer verde** del proyecto — la prueba de que toda la cadena
  petición→controlador→respuesta funciona.

---

**Next / Siguiente:** [02 — React frontend](02-frontend-scaffold.md) — a React app that calls this
endpoint and shows "API OK".
