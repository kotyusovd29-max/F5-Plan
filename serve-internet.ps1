# Публичный доступ к F5 Plan через Tailscale Funnel (стабильный HTTPS *.ts.net).
# Запуск: powershell -ExecutionPolicy Bypass -File serve-internet.ps1
# Остановка Funnel: & "C:\Program Files\Tailscale\tailscale.exe" funnel --https=443 off

$ErrorActionPreference = "Stop"

$php = "C:\OSPanel\modules\PHP-8.3\PHP\php.exe"
$tailscale = "C:\Program Files\Tailscale\tailscale.exe"
$port = 8000

Set-Location $PSScriptRoot

if (-not (Test-Path $tailscale)) {
    Write-Host "Tailscale не найден: $tailscale" -ForegroundColor Red
    Write-Host "Установите Tailscale и выполните: tailscale login" -ForegroundColor Yellow
    exit 1
}

if (Test-Path "public\hot") {
    Remove-Item "public\hot" -Force
}

function Test-LocalServer {
    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:$port/login" -UseBasicParsing -TimeoutSec 5
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Stop-PortListeners {
    param([int]$TargetPort)

    $connections = Get-NetTCPConnection -LocalPort $TargetPort -State Listen -ErrorAction SilentlyContinue

    foreach ($connection in $connections) {
        if ($connection.OwningProcess -gt 0) {
            Stop-Process -Id $connection.OwningProcess -Force -ErrorAction SilentlyContinue
        }
    }
}

function Get-FunnelPublicUrl {
    $status = & $tailscale status --json 2>$null | ConvertFrom-Json
    $dnsName = ($status.Self.DNSName -replace '\.$', '').Trim()
    if ([string]::IsNullOrWhiteSpace($dnsName)) {
        throw "Не удалось получить DNS-имя узла Tailscale. Выполните: tailscale login"
    }
    return "https://$dnsName"
}

$tunnelUrl = Get-FunnelPublicUrl

if (-not (Test-LocalServer)) {
    Write-Host "Запускаю локальный сервер на порту $port..." -ForegroundColor Cyan
    Stop-PortListeners -TargetPort $port
    Start-Sleep -Seconds 1

    $serveCommand = @"
Set-Location '$PSScriptRoot'
`$env:APP_URL = '$tunnelUrl'
& '$php' artisan serve --host=127.0.0.1 --port=$port
"@

    Start-Process powershell -ArgumentList "-NoExit", "-ExecutionPolicy", "Bypass", "-Command", $serveCommand | Out-Null

    $ready = $false
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        Start-Sleep -Seconds 1
        if (Test-LocalServer) {
            $ready = $true
            break
        }
    }

    if (-not $ready) {
        Write-Host "Не удалось запустить локальный сервер на http://127.0.0.1:$port" -ForegroundColor Red
        exit 1
    }
}

Write-Host "Включаю Tailscale Funnel на порт $port..." -ForegroundColor Cyan
& $tailscale funnel --bg $port
if ($LASTEXITCODE -ne 0) {
    Write-Host "Не удалось включить Funnel. Если нужно разрешить Funnel в админке — откройте ссылку из вывода выше." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Локальный сервер:  http://127.0.0.1:$port" -ForegroundColor Green
Write-Host "Публичная ссылка:  $tunnelUrl" -ForegroundColor Cyan
Write-Host "Funnel работает в фоне. Остановка: tailscale funnel --https=443 off" -ForegroundColor DarkGray
Write-Host ""
