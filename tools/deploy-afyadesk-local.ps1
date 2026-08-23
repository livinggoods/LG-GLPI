param(
    [switch]$SkipBackup
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Php = "C:\xampp\php\php.exe"

Set-Location $Root

if (-not $SkipBackup) {
    & "$PSScriptRoot\backup-afyadesk-local.ps1"
}

& $Php bin\console afyadesk:seed-service-areas --allow-superuser
& $Php bin\console afyadesk:seed-access-levels --allow-superuser
& $Php bin\console afyadesk:seed-whatsapp-templates --allow-superuser
& $Php bin\console cache:clear --no-interaction --allow-superuser
& "$PSScriptRoot\afyadesk-local-health.ps1"
