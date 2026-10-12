# Временный доступ к F5 Plan с других устройств в одной Wi‑Fi сети.
# Запуск: powershell -ExecutionPolicy Bypass -File serve-lan.ps1

$php = "C:\OSPanel\modules\PHP-8.3\PHP\php.exe"
$port = 8000

$lanIp = (
    Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -match '^192\.168\.\d+\.\d+$' } |
    Select-Object -First 1
).IPAddress

if (-not $lanIp) {
    $lanIp = (
        Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.IPAddress -match '^10\.\d+\.\d+\.\d+$' } |
        Select-Object -First 1
    ).IPAddress
}

if (-not $lanIp) {
    Write-Host "Не найден IP в локальной сети. Подключитесь к Wi‑Fi." -ForegroundColor Red
    exit 1
}

$env:APP_URL = "http://${lanIp}:${port}"

Write-Host ""
Write-Host "F5 Plan — доступ в локальной сети" -ForegroundColor Cyan
Write-Host "  На этом ПК:     http://127.0.0.1:${port}"
Write-Host "  С телефона/ПК:  http://${lanIp}:${port}"
Write-Host ""
Write-Host "Устройства должны быть в той же Wi‑Fi сети." -ForegroundColor Yellow
Write-Host "Остановка: Ctrl+C" -ForegroundColor DarkGray
Write-Host ""

Set-Location $PSScriptRoot

# Собранные стили из public/build, не dev-сервер Vite (localhost:5173)
if (Test-Path "public\hot") { Remove-Item "public\hot" -Force }

& $php artisan serve --host=0.0.0.0 --port=$port