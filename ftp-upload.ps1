# FTP Upload - carica il sito WordPress su alfredofiorillo.it
# Esegui con: powershell -ExecutionPolicy Bypass -File ftp-upload.ps1

$ftpHost    = "ftp.alfredofiorillo.it"
$ftpPort    = 21
$ftpUser    = "alfred.fiorillo@alfredofiorillo.it"
$ftpPass    = "Z9VmkHvY1@;.(;i."
$localRoot  = "C:\xampp\htdocs\rewrite"
$remoteRoot = ""

# Cartelle e file da ESCLUDERE dall upload
$excludeDirs = @(
    ".git",
    "wp-content\backups",
    "database",
    "node_modules"
)
$excludeFiles = @(
    "ftp-upload.ps1",
    "ftp-upload.log",
    ".gitignore",
    ".gitattributes"
)

$cred      = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
$uploaded  = 0
$errors    = 0
$startTime = Get-Date

function Should-Exclude($path) {
    $rel = $path.Substring($localRoot.Length).TrimStart([char]'\', [char]'/')
    foreach ($d in $excludeDirs) {
        if ($rel -eq $d -or $rel.StartsWith($d + "\") -or $rel.StartsWith($d + "/")) {
            return $true
        }
    }
    foreach ($f in $excludeFiles) {
        if ($rel -eq $f) { return $true }
    }
    return $false
}

function Ftp-CreateDir($uri) {
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method      = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $req.Credentials = $cred
        $req.EnableSsl   = $false
        $req.UsePassive  = $true
        $req.UseBinary   = $true
        $req.KeepAlive   = $false
        $resp = $req.GetResponse()
        $resp.Close()
    } catch { }
}

function Ftp-UploadFile($localPath, $remotePath) {
    $uri = "ftp://${ftpHost}${remotePath}"
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method      = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.Credentials = $cred
        $req.EnableSsl   = $false
        $req.UsePassive  = $true
        $req.UseBinary   = $true
        $req.KeepAlive   = $false

        $fileBytes = [System.IO.File]::ReadAllBytes($localPath)
        $req.ContentLength = $fileBytes.Length

        $stream = $req.GetRequestStream()
        $stream.Write($fileBytes, 0, $fileBytes.Length)
        $stream.Close()

        $resp = $req.GetResponse()
        $resp.Close()
        return $true
    } catch {
        Write-Host ("  ERRORE: " + $remotePath + " - " + $_.Exception.Message) -ForegroundColor Red
        return $false
    }
}

Write-Host "======================================================" -ForegroundColor Cyan
Write-Host "  FTP Upload: $ftpHost" -ForegroundColor Cyan
Write-Host "======================================================" -ForegroundColor Cyan
Write-Host ""

$allFiles = Get-ChildItem -Path $localRoot -Recurse -File | Where-Object {
    -not (Should-Exclude $_.FullName)
}

$total = $allFiles.Count
Write-Host "File da caricare: $total" -ForegroundColor Yellow
Write-Host ""

$i = 0
foreach ($file in $allFiles) {
    $i++
    $rel        = $file.FullName.Substring($localRoot.Length).Replace('\', '/')
    $remotePath = $remoteRoot + $rel

    $remoteDir = $remotePath.Substring(0, $remotePath.LastIndexOf('/'))
    if ($remoteDir -ne "") {
        Ftp-CreateDir ("ftp://${ftpHost}${remoteDir}")
    }

    $pct = [math]::Round(($i / $total) * 100)
    Write-Progress -Activity "Upload FTP" -Status "$i/$total - $rel" -PercentComplete $pct

    $ok = Ftp-UploadFile $file.FullName $remotePath
    if ($ok) {
        $uploaded++
        if ($i % 50 -eq 0) {
            Write-Host ("  [" + $i + "/" + $total + "] " + $rel) -ForegroundColor Green
        }
    } else {
        $errors++
    }
}

Write-Progress -Completed -Activity "Upload FTP"

$elapsed = [math]::Round(((Get-Date) - $startTime).TotalMinutes, 1)
Write-Host ""
Write-Host "======================================================" -ForegroundColor Cyan
Write-Host ("  Completato in " + $elapsed + " minuti") -ForegroundColor Cyan
if ($errors -gt 0) {
    Write-Host ("  Caricati: " + $uploaded + "  |  Errori: " + $errors) -ForegroundColor Yellow
} else {
    Write-Host ("  Caricati: " + $uploaded + "  |  Errori: " + $errors) -ForegroundColor Green
}
Write-Host "======================================================" -ForegroundColor Cyan
