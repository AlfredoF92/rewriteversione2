$ftpHost = "ftp.alfredofiorillo.it"
$ftpUser = "alfred.fiorillo@alfredofiorillo.it"
$ftpPass = "Z9VmkHvY1@;.(;i."
$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)

function Ftp-UploadFile {
    param($localPath, $remotePath)
    $uri = "ftp://" + $ftpHost + $remotePath
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.Credentials = $cred
        $req.EnableSsl = $false
        $req.UsePassive = $true
        $req.UseBinary = $true
        $req.KeepAlive = $false
        $fileBytes = [System.IO.File]::ReadAllBytes($localPath)
        $req.ContentLength = $fileBytes.Length
        $stream = $req.GetRequestStream()
        $stream.Write($fileBytes, 0, $fileBytes.Length)
        $stream.Close()
        $resp = $req.GetResponse()
        $resp.Close()
        Write-Host ("  OK: " + $remotePath) -ForegroundColor Green
        return $true
    } catch {
        Write-Host ("  ERRORE: " + $remotePath + " - " + $_.Exception.Message) -ForegroundColor Red
        return $false
    }
}

$files = @(
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-story-phrase-game.php",
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-frontend-auth.php",
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-login-form-shortcode.php",
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-logout-shortcode.php",
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-hero-translations.php",
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-user-stat-shortcodes.php",
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-header-user-shortcode.php",
    "wp-content/plugins/llm-con-tabelle/includes/class-llm-guest-home-redirect-shortcode.php",
    "wp-content/plugins/llm-con-tabelle/assets/llm-story-phrase-game.css",
    "wp-content/plugins/llm-con-tabelle/assets/llm-story-phrase-game.js",
    "wp-content/plugins/llm-con-tabelle/llm-con-tabelle.php"
)

foreach ($rel in $files) {
    $local = "C:\xampp\htdocs\rewrite\" + $rel.Replace("/", "\")
    $remote = "/" + $rel
    Ftp-UploadFile $local $remote
}
Write-Host "Done." -ForegroundColor Cyan
