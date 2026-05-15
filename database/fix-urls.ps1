# Sostituisce l'URL locale con quello online nel file SQL
# Gestisce correttamente i dati serializzati PHP (s:N:"...")

$oldUrl  = "http://localhost/rewrite"
$newUrl  = "https://rewrite.alfredofiorillo.it"
$srcFile = "$PSScriptRoot\deploy-ready.sql"
$dstFile = "$PSScriptRoot\deploy-online.sql"

Write-Host "Lettura del file SQL..."
$content = [System.IO.File]::ReadAllText($srcFile, [System.Text.Encoding]::UTF8)

$oldLen = $oldUrl.Length   # 24
$newLen = $newUrl.Length   # 33

Write-Host "Vecchio URL ($oldLen chars): $oldUrl"
Write-Host "Nuovo  URL ($newLen chars): $newUrl"
Write-Host ""

# --- 1. Sostituisci serializzati con quote escaped (dentro SQL dump) ---
# Pattern:  s:24:\"http://localhost/rewrite/percorso\"
# Diventa:  s:N:\"http://rewrite.alfredofiorillo.it/percorso\"
$escapedOld = [regex]::Escape($oldUrl)
$serializedPattern = "s:(\d+):\\""($escapedOld)([^\\""]*)\\""\""

$content = [regex]::Replace($content, 's:(\d+):\\"(' + $escapedOld + ')([^\\"]*)\\"', {
    param($m)
    $suffix  = $m.Groups[3].Value          # eventuale /percorso/in-piu
    $newFull = $newUrl + $suffix
    $newN    = $newFull.Length
    's:' + $newN + ':\\"' + $newFull + '\\"'
})

# --- 2. Sostituisci eventuali occorrenze non serializzate rimaste ---
$content = $content.Replace($oldUrl, $newUrl)

Write-Host "Scrittura del file di output..."
[System.IO.File]::WriteAllText($dstFile, $content, [System.Text.Encoding]::UTF8)

Write-Host ""
Write-Host "Fatto! File salvato in:"
Write-Host "  $dstFile"
Write-Host ""
Write-Host "Ora importa questo file via phpMyAdmin."
