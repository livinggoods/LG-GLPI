$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$constants = Join-Path $root "src\autoload\constants.php"
$version = $null

if (Test-Path -LiteralPath $constants) {
    $match = Select-String -LiteralPath $constants -Pattern "GLPI_VERSION', '([^']+)'" | Select-Object -First 1
    if ($match) {
        $version = $match.Matches[0].Groups[1].Value
    }
}

if ($version -eq $null) {
    throw "Could not read GLPI_VERSION from $constants"
}

if ($version -match "dev|alpha|beta|rc") {
    Write-Warning "This checkout is based on an unstable GLPI version: $version"
    Write-Host "Production action: rebase AfyaDesk custom commits onto the latest stable GLPI tag, test locally, then deploy through PR."
    exit 2
}

Write-Host "Stable GLPI base detected: $version"
