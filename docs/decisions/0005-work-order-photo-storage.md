# ADR 0005 — Work-order photos: re-encoded JPEG in Postgres (bytea)

- **Status:** Accepted · **Date:** 2026-11-01

**Languages:** [English](#english) · [Español](#español)

---

## English

### Context
P1 of [PLAN-v2](../PLAN-v2.md) lets the electrician attach photos to a work order from the phone (the
broken socket, the panel before and after). The photos are evidence for the client and end up in the
work-order PDF. Where should they live?

Constraints:
- **One pilot user** for now; the backend runs as a single container with a managed Postgres, no object
  storage configured yet.
- A photo straight from a phone is **3–8 MB** and carries **EXIF metadata, often with GPS coordinates** — of
  the client's home.
- Uploaded images are **untrusted input**: the declared MIME type and file name mean nothing, and an image
  header can claim enormous dimensions to exhaust the server's memory (a *decompression bomb*).

### Decision
- **Store photos as `bytea` in Postgres**, in a `work_order_photo` table (`ON DELETE CASCADE` from the order).
  Backups, user isolation and deletion come for free with the rest of the data.
- **Shrink on the phone first**: the frontend downscales to ≤ 1600 px and JPEG before uploading (same
  approach as the floor-plan editor), so a photo travels and is stored at roughly **150–400 KB**.
- **Never keep the original bytes.** `ImageSanitizer` sniffs the real type (JPEG/PNG only), rejects anything
  over 4 MB or **40 Mpx — checked from the header, before decoding**, decodes it with GD and **re-encodes** a
  fresh JPEG (≤ 1600 px, quality 82). Re-encoding drops every metadata block, **EXIF GPS included**.
- **Caps**: at most **6 photos per order**, and photos can only be added or removed while the order is still
  editable (`pendiente` / `en_curso`).
- **Serving**: `GET /api/work-orders/{id}/photos/{photoId}` looks the photo up **inside the user's own
  order** (never by photo id alone, so there is no IDOR), with `Content-Type: image/jpeg` and
  `X-Content-Type-Options: nosniff`.

The client's signature follows the same rules on a smaller scale: a PNG from the canvas, sniffed, size-capped
and re-encoded, stored in `work_order.signature_png`.

### Consequences
- Simple and safe for the pilot: no new service, no bucket permissions, no signed URLs.
- **Known limit: it bloats the database.** 6 photos × ~300 KB ≈ 2 MB per order; a few hundred orders a year
  is still well under a GB, but it grows backups and makes the detail query heavier.
- **When to revisit:** more than one active user, or the database passing ~5 GB. The move is to object
  storage (S3 / Cloudflare R2): keep `work_order_photo` as metadata (`storageKey`, size, dimensions), serve
  through short-lived signed URLs, and migrate the existing rows with a one-off command. The API shape
  (`url` per photo) does not need to change.
- GD becomes a required PHP extension (`ext-gd` in `composer.json`, in CI and in the Dockerfile).

---

## Español

### Contexto
El P1 del [PLAN-v2](../PLAN-v2.md) permite al electricista adjuntar fotos a un parte desde el móvil (el
enchufe quemado, el cuadro antes y después). Son una prueba para el cliente y acaban en el PDF del parte.
¿Dónde se guardan?

Condiciones:
- **Un único usuario piloto** por ahora; el backend es un solo contenedor con un Postgres gestionado, sin
  almacenamiento de objetos configurado.
- Una foto tal cual sale del móvil pesa **3–8 MB** y lleva **metadatos EXIF, muchas veces con coordenadas
  GPS**: las de la casa del cliente.
- Las imágenes subidas son **entrada no fiable**: el tipo MIME y el nombre declarados no significan nada, y la
  cabecera de una imagen puede declarar dimensiones enormes para agotar la memoria del servidor (una *bomba
  de descompresión*).

### Decisión
- **Guardar las fotos como `bytea` en Postgres**, en una tabla `work_order_photo` (`ON DELETE CASCADE` desde
  el parte). Copias de seguridad, aislamiento por usuario y borrado vienen gratis con el resto de datos.
- **Reducir primero en el móvil**: el frontend la baja a ≤ 1600 px y a JPEG antes de subirla (igual que el
  editor de planos), así que viaja y se guarda en unos **150–400 KB**.
- **No guardar nunca los bytes originales.** `ImageSanitizer` mira el tipo real (solo JPEG/PNG), rechaza
  cualquier cosa de más de 4 MB o **40 Mpx —comprobado en la cabecera, antes de decodificar—**, la decodifica
  con GD y **vuelve a codificar** un JPEG nuevo (≤ 1600 px, calidad 82). Al re-codificar se pierden todos los
  metadatos, **incluido el GPS del EXIF**.
- **Topes**: como mucho **6 fotos por parte**, y solo se pueden añadir o quitar mientras el parte se puede
  editar (`pendiente` / `en_curso`).
- **Servirlas**: `GET /api/work-orders/{id}/photos/{photoId}` busca la foto **dentro del parte del propio
  usuario** (nunca solo por el id de la foto, así no hay IDOR), con `Content-Type: image/jpeg` y
  `X-Content-Type-Options: nosniff`.

La firma del cliente sigue las mismas reglas a menor escala: un PNG del lienzo, comprobado, con tope de
tamaño y re-codificado, guardado en `work_order.signature_png`.

### Consecuencias
- Sencillo y seguro para el piloto: sin servicios nuevos, sin permisos de buckets ni URLs firmadas.
- **Límite conocido: hincha la base de datos.** 6 fotos × ~300 KB ≈ 2 MB por parte; unos cientos de partes
  al año siguen muy por debajo de 1 GB, pero engordan las copias y hacen más pesada la consulta del detalle.
- **Cuándo revisarlo:** con más de un usuario activo, o si la base pasa de ~5 GB. El paso es a
  almacenamiento de objetos (S3 / Cloudflare R2): `work_order_photo` se queda con los metadatos
  (`storageKey`, tamaño, dimensiones), se sirven con URLs firmadas de corta duración y las filas existentes
  se migran con un comando puntual. La forma de la API (`url` por foto) no tiene que cambiar.
- GD pasa a ser una extensión PHP obligatoria (`ext-gd` en `composer.json`, en el CI y en el Dockerfile).
