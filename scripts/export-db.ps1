param(
    [Parameter(Mandatory = $true)]
    [string]$CommitName
)

$ErrorActionPreference = "Stop"

$safeName = ($CommitName.ToLower() -replace '[^a-z0-9._-]+', '-').Trim('-')
if ([string]::IsNullOrWhiteSpace($safeName)) {
    throw "CommitName non valido."
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$dumpDir = Join-Path $projectRoot "database"
if (-not (Test-Path $dumpDir)) {
    New-Item -ItemType Directory -Path $dumpDir | Out-Null
}

$dbName = "rewrite_2.0"
$dumpFile = Join-Path $dumpDir ($safeName + ".sql")
$mysqlDump = "C:\xampp\mysql\bin\mysqldump.exe"

if (-not (Test-Path $mysqlDump)) {
    throw "mysqldump non trovato in $mysqlDump"
}

& $mysqlDump -u root --default-character-set=utf8mb4 $dbName -r $dumpFile

if (-not (Test-Path $dumpFile)) {
    throw "Export fallito: file non creato."
}

Write-Output "Database esportato: $dumpFile"
