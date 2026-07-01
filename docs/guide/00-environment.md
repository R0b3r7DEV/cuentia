# 00 — Development environment · Entorno de desarrollo

Goal / Objetivo: install the toolchain (PHP, Composer, Symfony CLI, PostgreSQL) on Windows.

> Each step shows the command once, then explains it in **EN** and **ES**.

---

## 1. Node.js (already installed)

```powershell
node --version   # v24.x
npm --version    # 11.x
```

- **EN:** Node/npm are needed for the React frontend. They were already present.
- **ES:** Node/npm hacen falta para el frontend React. Ya estaban instalados.

## 2. Scoop (Windows package manager)

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned -Force
Invoke-RestMethod get.scoop.sh | Invoke-Expression
```

- **EN:** Scoop installs developer tools into your user folder — no admin rights, easy to remove.
  The first line lets PowerShell run the install script; the second downloads and runs it.
- **ES:** Scoop instala herramientas de desarrollo en tu carpeta de usuario — sin permisos de
  administrador y fácil de desinstalar. La primera línea permite a PowerShell ejecutar el script de
  instalación; la segunda lo descarga y ejecuta.

## 3. PHP, Composer, Symfony CLI, PostgreSQL

```powershell
scoop install php composer symfony-cli postgresql
```

- **EN:** One command installs all four. **PHP** runs Symfony; **Composer** is PHP's dependency
  manager (like npm for PHP); the **Symfony CLI** scaffolds and serves Symfony apps; **PostgreSQL**
  is our database. Scoop also *initialized a database cluster* automatically (superuser `postgres`,
  no password, local `trust` auth — fine for local development).
- **ES:** Un solo comando instala las cuatro. **PHP** ejecuta Symfony; **Composer** es el gestor de
  dependencias de PHP (como npm para PHP); la **CLI de Symfony** genera y sirve apps Symfony;
  **PostgreSQL** es nuestra base de datos. Scoop además *inicializó un clúster de base de datos*
  automáticamente (superusuario `postgres`, sin contraseña, auth `trust` en local — vale para
  desarrollo local).

## 4. Enable PHP extensions (php.ini)

PHP needs some extensions turned on. We create a `php.ini` from the shipped template and enable them:

```powershell
$phpDir = "$env:USERPROFILE\scoop\apps\php\current"
Copy-Item "$phpDir\php.ini-production" "$phpDir\php.ini" -Force
# then append:
#   extension_dir = "<phpDir>\ext"
#   extension=openssl, mbstring, curl, fileinfo, intl, pdo_pgsql, pgsql, sodium, zip
```

- **EN:** On Windows, PHP extensions are `.dll` files that must be enabled in `php.ini`.
  `pdo_pgsql` + `pgsql` let PHP talk to PostgreSQL; `intl`, `mbstring`, `openssl`, etc. are required
  or recommended by Symfony. Without a `php.ini`, none of them load.
- **ES:** En Windows, las extensiones de PHP son ficheros `.dll` que hay que activar en `php.ini`.
  `pdo_pgsql` + `pgsql` permiten a PHP hablar con PostgreSQL; `intl`, `mbstring`, `openssl`, etc. las
  requiere o recomienda Symfony. Sin un `php.ini`, no se carga ninguna.

## 5. Verify · Verificar

```powershell
php -v ; composer -V ; symfony version ; postgres --version
php -m | Select-String pdo_pgsql
```

- **EN:** Confirms each tool responds and that the PostgreSQL driver is loaded in PHP.
- **ES:** Confirma que cada herramienta responde y que el driver de PostgreSQL está cargado en PHP.

Installed versions in this project: PHP 8.5.7 · Composer 2.10.2 · Symfony CLI 5.17.1 · PostgreSQL 18.4.
