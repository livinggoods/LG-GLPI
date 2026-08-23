param(
    [switch] $Once
)

$ErrorActionPreference = "Stop"

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$PhpPath = "C:\xampp\php\php.exe"
$HostName = "127.0.0.1"
$Port = 8081
$Url = "http://${HostName}:${Port}/"
$LogDir = Join-Path $ProjectRoot "files\_log\local-server"
$MonitorLog = Join-Path $LogDir "watcher.log"

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

function Write-AfyaDeskLog {
    param([string] $Message)

    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $MonitorLog -Value "[$timestamp] $Message"
}

function Test-AfyaDeskServer {
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri $Url -TimeoutSec 10
        return $response.StatusCode -ge 200 -and $response.StatusCode -lt 500
    } catch {
        return $false
    }
}

function Start-AfyaDeskServer {
    if (!(Test-Path $PhpPath)) {
        Write-AfyaDeskLog "Cannot start AfyaDesk: PHP was not found at $PhpPath"
        return
    }

    Write-AfyaDeskLog "Starting AfyaDesk GLPI on $Url"
    Start-Process `
        -FilePath $PhpPath `
        -ArgumentList @("-S", "${HostName}:${Port}") `
        -WorkingDirectory $ProjectRoot `
        -WindowStyle Hidden
}

Write-AfyaDeskLog "AfyaDesk local server watcher started."

do {
    if (!(Test-AfyaDeskServer)) {
        Start-AfyaDeskServer
        Start-Sleep -Seconds 8
    }

    if ($Once) {
        break
    }

    Start-Sleep -Seconds 30
} while ($true)
