# start-dev.ps1
# Starts everything you need for local development: PostgreSQL, the Symfony API and the Vite dev server.
# ES: Arranca todo lo necesario para desarrollo local: PostgreSQL, la API Symfony y el servidor de Vite.
#
# Usage / Uso:   powershell -ExecutionPolicy Bypass -File .\start-dev.ps1
# Then open / Luego abre:   http://localhost:5173

$pg       = "$env:USERPROFILE\scoop\apps\postgresql\current"
$php      = "$env:USERPROFILE\scoop\shims\php.exe"
$backend  = "$PSScriptRoot\backend"
$frontend = "$PSScriptRoot\frontend"

function Listening($port) {
    Test-NetConnection 127.0.0.1 -Port $port -InformationLevel Quiet -WarningAction SilentlyContinue
}

# 1. PostgreSQL (started detached; not a Windows service).
#    ES: PostgreSQL (arrancado en segundo plano; no es un servicio de Windows).
if (-not (Listening 5432)) {
    Write-Host "Starting PostgreSQL..." -ForegroundColor Cyan
    Start-Process "$pg\bin\pg_ctl.exe" -ArgumentList '-D', "$pg\data", '-l', "$pg\server.log", 'start' -WindowStyle Hidden
    Start-Sleep -Seconds 3
} else {
    Write-Host "PostgreSQL already running." -ForegroundColor DarkGray
}

# 2. Symfony API in its own window (so you can see logs / así ves los logs).
Write-Host "Starting Symfony API  -> http://127.0.0.1:8000" -ForegroundColor Cyan
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$backend'; & '$php' -S 127.0.0.1:8000 -t public public/index.php"

# 3. Vite dev server in its own window.
Write-Host "Starting Vite (React) -> http://localhost:5173" -ForegroundColor Cyan
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$frontend'; npm run dev"

Write-Host ""
Write-Host "All set. Open http://localhost:5173" -ForegroundColor Green
Write-Host "To stop: close the two new PowerShell windows (Ctrl+C)." -ForegroundColor DarkGray
