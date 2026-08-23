param(
    [string]$MysqlDumpPath = "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe",
    [string]$Database = "glpi",
    [string]$User = "root",
    [string]$OutputDir = ""
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
if ($OutputDir -eq "") {
    $OutputDir = Join-Path $root "files\_backup"
}
New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$sqlFile = Join-Path $OutputDir "afyadesk-$stamp.sql"
$filesZip = Join-Path $OutputDir "afyadesk-files-$stamp.zip"

if (!(Test-Path -LiteralPath $MysqlDumpPath)) {
    throw "mysqldump was not found at $MysqlDumpPath"
}

& $MysqlDumpPath --user=$User --databases $Database --result-file=$sqlFile
Compress-Archive -Force -Path (Join-Path $root "files"), (Join-Path $root "config") -DestinationPath $filesZip

[ordered]@{
    database_backup = $sqlFile
    files_backup = $filesZip
    created_at = (Get-Date).ToString("s")
} | ConvertTo-Json
