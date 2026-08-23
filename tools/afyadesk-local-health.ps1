param(
    [string]$Url = "http://127.0.0.1:8081/",
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$MysqlService = "MySQL80"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$report = [ordered]@{
    checked_at = (Get-Date).ToString("s")
    root = $root.Path
    url = $Url
    php_path = $PhpPath
    php_exists = Test-Path -LiteralPath $PhpPath
    mysql_service = $MysqlService
    mysql_status = $null
    http_status = $null
    glpi_version = $null
    is_dev_version = $false
    timezone_enabled_hint = "If GLPI shows the timezone warning, run tools\\seed-afyadesk-mysql-timezones.ps1, then php bin/console database:enable_timezones."
}

try {
    $svc = Get-Service -Name $MysqlService -ErrorAction Stop
    $report.mysql_status = $svc.Status.ToString()
} catch {
    $report.mysql_status = "not found"
}

try {
    $response = Invoke-WebRequest -UseBasicParsing -Uri $Url -TimeoutSec 8
    $report.http_status = [int]$response.StatusCode
} catch {
    $report.http_status = "failed: $($_.Exception.Message)"
}

$constants = Join-Path $root "src\autoload\constants.php"
if (Test-Path -LiteralPath $constants) {
    $match = Select-String -LiteralPath $constants -Pattern "GLPI_VERSION', '([^']+)'" | Select-Object -First 1
    if ($match) {
        $report.glpi_version = $match.Matches[0].Groups[1].Value
        $report.is_dev_version = $report.glpi_version -match "dev|alpha|beta|rc"
    }
}

$report | ConvertTo-Json -Depth 3
