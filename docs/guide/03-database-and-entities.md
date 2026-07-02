# 03 — Database & domain model · Base de datos y modelo de dominio

Goal / Objetivo: connect PostgreSQL and create the `Transaction` and `Category` tables via Doctrine.

---

## 1. Install Doctrine (the ORM) + MakerBundle

```powershell
composer require symfony/orm-pack
composer require symfony/maker-bundle --dev
```

- **EN:** **Doctrine** is Symfony's ORM (Object-Relational Mapper): it lets us work with PHP objects
  instead of writing SQL by hand. **MakerBundle** generates boilerplate (migrations, entities...).
- **ES:** **Doctrine** es el ORM de Symfony (mapeador objeto-relacional): nos deja trabajar con objetos
  PHP en vez de escribir SQL a mano. **MakerBundle** genera código repetitivo (migraciones, entidades...).

## 2. Start the PostgreSQL server

```powershell
$pg = "$env:USERPROFILE\scoop\apps\postgresql\current"
pg_ctl -D "$pg\data" -l "$pg\server.log" start
```

- **EN:** Scoop installed PostgreSQL but does not run it as a Windows service, so we start it manually
  with `pg_ctl`. You must do this **once per session** (after a reboot). Check it with `pg_isready`.
- **ES:** Scoop instaló PostgreSQL pero no lo ejecuta como servicio de Windows, así que lo arrancamos a
  mano con `pg_ctl`. Hay que hacerlo **una vez por sesión** (tras reiniciar). Compruébalo con `pg_isready`.

## 3. Configure the connection

File: `backend/.env.local` (git-ignored — connection details never go to the repo)

```
DATABASE_URL="postgresql://postgres@127.0.0.1:5432/cuentia?serverVersion=18&charset=utf8"
```

- **EN:** `DATABASE_URL` tells Doctrine how to connect: user `postgres`, no password (local `trust`
  auth), host/port, database `cuentia`, PostgreSQL version 18. We put it in `.env.local` so real
  connection details stay out of git.
- **ES:** `DATABASE_URL` le dice a Doctrine cómo conectarse: usuario `postgres`, sin contraseña (auth
  `trust` en local), host/puerto, base de datos `cuentia`, PostgreSQL versión 18. Va en `.env.local`
  para que los datos reales de conexión no lleguen a git.

## 4. Create the database

```powershell
php bin/console doctrine:database:create
```

- **EN:** Creates the empty `cuentia` database on the server.
- **ES:** Crea la base de datos `cuentia` (vacía) en el servidor.

## 5. Write the entities

Two PHP classes in `src/Entity/` describe our tables using `#[ORM\...]` attributes:

- **`Category`** — `id`, `name`, `kind` (`income`/`expense`), `color`.
- **`Transaction`** — `id`, `bookedAt`, `description`, `amount`, `currency`, a **ManyToOne** relation
  to `Category`, `categorySource`, `vatRate`, `importedFrom`, `createdAt`.

Key teaching points / Puntos clave:

- **EN — Money is `decimal`, not float.** `amount` and `vatRate` use `#[ORM\Column(type: 'decimal')]`,
  and Doctrine returns them as PHP **strings** to keep exact precision. Using `float` for money causes
  rounding errors — a hard no in accounting software (and a classic interview question).
- **ES — El dinero es `decimal`, no float.** `amount` y `vatRate` usan `#[ORM\Column(type: 'decimal')]`
  y Doctrine los devuelve como **strings** de PHP para mantener la precisión exacta. Usar `float` para
  dinero provoca errores de redondeo — algo prohibido en software de contabilidad (y una pregunta
  clásica de entrevista).
- **EN — The relation.** `#[ORM\ManyToOne]` on `Transaction::$category` means "many transactions
  belong to one category". Doctrine creates a `category_id` foreign key automatically.
- **ES — La relación.** `#[ORM\ManyToOne]` en `Transaction::$category` significa "muchos movimientos
  pertenecen a una categoría". Doctrine crea automáticamente una clave foránea `category_id`.

Each entity has a matching **repository** in `src/Repository/` — the class where database queries for
that entity will live. / Cada entidad tiene su **repositorio** en `src/Repository/` — la clase donde
vivirán las consultas de esa entidad.

## 6. Generate and run the migration

```powershell
php bin/console make:migration                          # generates SQL from the entity diff
php bin/console doctrine:migrations:migrate --no-interaction   # applies it to the database
```

- **EN:** `make:migration` compares your entities with the current database and writes a **migration**
  (a versioned SQL script) into `migrations/`. `migrate` runs it. Migrations are committed to git, so
  anyone can rebuild the exact schema — and changes are tracked over time.
- **ES:** `make:migration` compara tus entidades con la base de datos actual y escribe una
  **migración** (un script SQL versionado) en `migrations/`. `migrate` la ejecuta. Las migraciones se
  suben a git, así cualquiera puede reconstruir el esquema exacto — y los cambios quedan registrados.

## 7. Verify

```powershell
php bin/console dbal:run-sql "SELECT table_name FROM information_schema.tables WHERE table_schema='public'"
# → category, transaction, doctrine_migration_versions
```

- **EN:** Confirms the `category` and `transaction` tables now exist.
- **ES:** Confirma que las tablas `category` y `transaction` ya existen.

---

**Next / Siguiente:** [04 — CSV import](04-csv-import.md) — an endpoint that reads a bank CSV and
saves `Transaction` rows.
