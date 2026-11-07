# Plan: llevar Cuentia al VPS con fireteanu.tech

*Plan for moving Cuentia from Render + Neon + Vercel to our own VPS under fireteanu.tech. Written in Spanish
because it is a checklist for the owner; the commands are the same in any language.*

Estado: **pendiente** — se hace cuando esté registrado `fireteanu.tech`. Mientras tanto Cuentia sigue en
Vercel (web) + Render (API) + Neon (base de datos).

---

## 0. Qué cambia, en una frase

Todo pasa a una sola máquina y un solo dominio: **`https://cuentia.fireteanu.tech`**. Caddy sirve la web
(los ficheros de `frontend/dist`) y reenvía `/api/*` al contenedor de Symfony; PostgreSQL corre al lado en
otro contenedor, sin puerto abierto a internet.

```
navegador ──HTTPS──▶ Caddy (80/443, certificado automático)
                       ├─ /        → frontend/dist (ficheros estáticos)
                       └─ /api/*   → api:8080 (Symfony, Apache)  ──▶ db:5432 (PostgreSQL, solo red interna)
```

Por qué así:

- **Un solo origen** (web y API en el mismo dominio): las cookies de sesión siguen siendo `SameSite`, no hace
  falta CORS y la CSP no cambia (`connect-src 'self'`).
- **IP real del cliente** (punto 5 de la revisión): hoy la API ve la IP de Vercel, no la del usuario, así que
  los límites por IP (registro, reenvío de correos) cuentan a todos como si fueran uno. No se ha arreglado en
  Render a propósito: la API también se puede llamar directamente en `cuentia-api.onrender.com`, y si
  confiáramos en la cabecera `X-Forwarded-For` cualquiera se la inventaría para saltarse el límite. En el VPS
  la API **solo** es accesible a través de Caddy, que pone la IP real; entonces sí es seguro confiar en él.
- **Copias de seguridad nuestras** (punto 3): Neon gratis no da copias que podamos guardar fuera.

## 1. Antes del día del cambio

1. **Registrar `fireteanu.tech`** y crear en su DNS (IONOS):
   - `A  cuentia.fireteanu.tech → <IP del VPS>` (la IP está en `~/.ssh/config` del Mac, no en notas).
   - Si el VPS tiene IPv6: `AAAA` igual. Si no, no poner `AAAA`.
   - TTL bajo (300 s) para poder corregir rápido.
2. **Comprobar los puertos 80/443 del VPS.** Docker *rootless* (usuario `guaita`) no puede abrir puertos por
   debajo de 1024. Mirar primero cómo publica IronLog:
   - Si ya hay un proxy (Caddy/nginx) en 80/443 → Cuentia se añade como **otro sitio** en ese proxy; no se
     levanta un Caddy nuevo.
   - Si no hay → con el usuario `n2k` (sudo):
     `echo 'net.ipv4.ip_unprivileged_port_start=80' | sudo tee /etc/sysctl.d/99-rootless-ports.conf && sudo sysctl --system`.
3. **Cortafuegos**: solo 2222 (SSH), 80 y 443 abiertos (`sudo ufw status`). PostgreSQL **nunca** se publica.
4. **Versión de PostgreSQL de Neon**: en la consola de Neon, `SHOW server_version;`. En el VPS se usa la misma
   versión mayor o una más nueva (pg_restore no restaura bien hacia atrás).
5. **Correo**: de momento se sigue enviando desde `no-reply@xpl0day.com` (ya verificado en Brevo, con DKIM y
   DMARC). Más adelante, si se quiere `no-reply@fireteanu.tech`: dar de alta el dominio en Brevo, poner sus
   registros DKIM/DMARC en el DNS de `fireteanu.tech` y cambiar `MAILER_FROM`.
6. **Avisar al padre**: el día del cambio tendrá que volver a entrar (la sesión es por dominio) y usar la
   dirección nueva. La guía PDF se actualiza con la URL nueva.

## 2. Ficheros que hay que añadir al repositorio (rama `ops/vps`)

`ops/vps/compose.yaml`:

```yaml
services:
  db:
    image: postgres:17-alpine          # misma mayor que Neon o superior
    restart: unless-stopped
    environment:
      POSTGRES_DB: cuentia
      POSTGRES_USER: cuentia
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes: [db:/var/lib/postgresql/data]
    healthcheck: { test: ["CMD", "pg_isready", "-U", "cuentia"], interval: 10s, retries: 5 }

  api:
    build: { context: ../../backend }
    restart: unless-stopped
    env_file: .env.prod                # NUNCA en git (ver sección 3)
    environment: { PORT: "8080" }
    depends_on: { db: { condition: service_healthy } }

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    ports: ["80:80", "443:443"]
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - ../../frontend/dist:/srv:ro
      - caddy_data:/data
    depends_on: [api]

volumes: { db: {}, caddy_data: {} }
```

`ops/vps/Caddyfile` (las cabeceras de seguridad que hoy están en `frontend/vercel.json` pasan aquí):

```
cuentia.fireteanu.tech {
    encode zstd gzip

    handle /api/* {
        reverse_proxy api:8080      # Caddy pone X-Forwarded-For con la IP real del cliente
    }
    handle {
        root * /srv
        try_files {path} /index.html
        file_server
    }

    header {
        Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests"
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        Referrer-Policy strict-origin-when-cross-origin
        Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()"
        Strict-Transport-Security "max-age=63072000; includeSubDomains"
        Cross-Origin-Opener-Policy same-origin
        -Server
    }
}
```

`ops/vps/deploy.sh`: `git pull` → `npm ci && npm run build` en `frontend/` → `docker compose build api` →
`docker compose up -d`. Las migraciones ya se ejecutan solas al arrancar el contenedor (y si una falla, el
contenedor no arranca: se queda la versión anterior).

## 3. Variables de entorno (`ops/vps/.env.prod`, solo en el servidor, permisos `600`)

| Variable | Valor | Ojo |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `prod` / `0` | |
| `APP_SECRET` | **el mismo de Render** | Cifra las claves de Anthropic y del banco guardadas. Si cambia, esas claves dejan de poder leerse y cada usuario tendría que volver a ponerlas. Copiarlo desde el panel de Render a mano, sin pegarlo en ningún chat ni fichero del repo. |
| `DATABASE_URL` | `postgresql://cuentia:${DB_PASSWORD}@db:5432/cuentia?serverVersion=17&charset=utf8` | `DB_PASSWORD` largo y aleatorio (`openssl rand -hex 24`), también solo en el servidor. |
| `TRUSTED_PROXIES` | `private_ranges` | Confía solo en la red interna de Docker (Caddy). Con esto `getClientIp()` da la IP real y los límites por IP funcionan. |
| `FRONTEND_URL` | `https://cuentia.fireteanu.tech` | Los enlaces de los correos (confirmar email, recuperar contraseña) salen de aquí. |
| `MAILER_DSN` | el de Brevo (igual que en Render) | |
| `MAILER_FROM` | `Cuentia <no-reply@xpl0day.com>` | Hasta verificar `fireteanu.tech` en Brevo. |
| `ALERT_EMAIL` | tu correo | Avisos de error (punto 4). Vacío = sin avisos. |
| `ANTHROPIC_API_KEY` | opcional | Cada usuario pone su propia clave en «Mis datos». |

## 4. El día del cambio (paso a paso)

Elegir una hora en la que el padre no esté usando la app (por la noche). Unos 30–45 minutos.

1. **Congelar**: en Render, *Suspend* del servicio `cuentia-api` (nadie puede escribir en Neon a partir de
   aquí; la web de Vercel mostrará «el servidor no responde»).
2. **Copia de Neon** (desde el VPS, sin instalar nada, con un contenedor de un solo uso):
   ```bash
   read -s NEON_URL      # pegar la URL de conexión de Neon; no queda en el historial ni en ficheros
   docker run --rm -e NEON_URL postgres:17-alpine sh -c 'pg_dump "$NEON_URL" -Fc --no-owner --no-acl' > ~/cuentia-neon.dump
   unset NEON_URL
   ```
3. **Levantar solo la base de datos** y restaurar:
   ```bash
   cd ~/cuentia/ops/vps && docker compose up -d db
   docker compose exec -T db pg_restore -U cuentia -d cuentia --no-owner --no-acl < ~/cuentia-neon.dump
   ```
4. **Comprobar** que los números cuadran (en Neon y en el VPS):
   `SELECT (SELECT count(*) FROM app_user), (SELECT count(*) FROM invoice), (SELECT count(*) FROM work_order);`
   y que `doctrine_migration_versions` tiene la misma última migración.
5. **Levantar todo**: `./deploy.sh` → `docker compose ps` (los tres *healthy/running*) →
   `curl -s https://cuentia.fireteanu.tech/api/health`.
6. **Prueba de humo** en el navegador: entrar con la cuenta del padre, abrir una factura y su PDF, una
   verificación VeriFactu, un parte con foto, «Recuperar contraseña» (comprobar que el enlace del correo lleva
   el dominio nuevo), y que el asistente lee la clave de Anthropic guardada (prueba de que `APP_SECRET` es el
   mismo).
7. **Redirigir la dirección vieja**: en `frontend/vercel.json`, un `redirects` de todo
   `cuentia-snowy.vercel.app` a `https://cuentia.fireteanu.tech` (permanente), para que los marcadores viejos
   sigan funcionando.

### Si algo sale mal (vuelta atrás)

Hasta el paso 7, volver es **reanudar el servicio en Render** (*Resume*): Neon no se ha tocado y la web de
Vercel vuelve a funcionar. Pasado el paso 7, lo que se haya escrito en el VPS no está en Neon: para volver
habría que hacer el camino inverso (`pg_dump` del VPS → `pg_restore` en Neon). Por eso Render y Neon se dejan
**dos semanas** sin borrar, suspendidos, antes de darlos de baja.

## 5. Copias de seguridad (punto 3)

Mismo esquema que la de IronLog (`~/ironlog-backup.sh`, cron a las 04:30), pero a otra hora para no
coincidir.

`~/cuentia-backup.sh`:

```bash
#!/bin/sh
set -eu
DIR="$HOME/backups/cuentia"; mkdir -p "$DIR"
F="$DIR/cuentia-$(date +%F).dump"
cd "$HOME/cuentia/ops/vps"
docker compose exec -T db pg_dump -U cuentia -Fc cuentia > "$F.tmp" && mv "$F.tmp" "$F"
# 14 copias diarias + la del día 1 de cada mes durante un año
find "$DIR" -name 'cuentia-*.dump' -mtime +14 ! -name 'cuentia-*-01.dump' -delete
find "$DIR" -name 'cuentia-*-01.dump' -mtime +365 -delete
```

- **Cron** (usuario `guaita`): `15 4 * * * $HOME/cuentia-backup.sh >> $HOME/backups/cuentia/backup.log 2>&1`.
- **Fuera del VPS**: una copia en el mismo servidor no sirve si el servidor se rompe. Cifrar con `age` (clave
  pública en el VPS, privada solo en el Mac) y subir con `rclone` a OneDrive. Así OneDrive solo ve ficheros
  cifrados — hay datos de clientes (nombres, direcciones, CUPS).
- **Probar la restauración** una vez al mes en un contenedor de usar y tirar:
  `docker run --rm -d --name prueba -e POSTGRES_PASSWORD=x postgres:17-alpine` → `pg_restore` de la última copia
  → contar filas. Una copia que nunca se ha restaurado no se sabe si sirve.

## 6. Vigilancia después del cambio

- **Avisos de error por correo**: ya están en el código (`App\Log\AlertMailHandler`); basta con poner
  `ALERT_EMAIL`. Un correo por error distinto y hora como mucho.
- **¿Está vivo?**: un comprobador gratuito (UptimeRobot o similar) contra
  `https://cuentia.fireteanu.tech/api/health` cada 5 minutos, con aviso por correo.
- **Disco**: las copias y los logs de Docker crecen. En `/etc/docker/daemon.json` (o la config rootless):
  `"log-opts": {"max-size": "10m", "max-file": "3"}`. Revisar `df -h` de vez en cuando.
- **Actualizaciones**: `unattended-upgrades` para el sistema; `docker compose pull && ./deploy.sh` una vez al
  mes para Caddy y PostgreSQL (versiones menores).
- **SSH**: solo con clave, sin root, puerto 2222, `fail2ban` activo.

## 7. Después de dos semanas sin problemas

- Borrar el servicio de Render y el proyecto de Neon (antes, una última copia de Neon guardada aparte).
- Quitar de Neon la cuenta huérfana `smoke…@test.local` si todavía existe (o borrarla ya en el VPS).
- Actualizar el README (URL de la demo), `render.yaml` (borrarlo o dejarlo como alternativa documentada) y la
  guía PDF del padre con la dirección nueva.
- Desplegar con GitHub Actions (opcional): un *workflow* que, al hacer merge en `master` y con los tests en
  verde, entra por SSH con una clave de despliegue de solo ese uso y ejecuta `deploy.sh`.
