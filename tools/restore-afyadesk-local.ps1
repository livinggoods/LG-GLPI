param(
    [Parameter(Mandatory = $true)][string]$SqlFile,
    [string]$MysqlPath = "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe",
    [string]$User = "root"
)

$ErrorActionPreference = "Stop"
if (!(Test-Path -LiteralPath $SqlFile)) {
    throw "SQL backup was not found: $SqlFile"
}
if (!(Test-Path -LiteralPath $MysqlPath)) {
    throw "mysql.exe was not found at $MysqlPath"
}

Get-Content -LiteralPath $SqlFile | & $MysqlPath --user=$User
Write-Host "Restore completed from $SqlFile"
