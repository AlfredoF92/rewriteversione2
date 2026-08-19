<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$rootPath = isset($_REQUEST['path']) ? trim($_REQUEST['path']) : '/home1/chnjoimy/public_html';
$ajaxRoot = isset($_REQUEST['root']) ? trim($_REQUEST['root']) : '';

/** 参考 code.php scan_manual：收集根路径下所有 WP 站点根目录 */
function scanWpSites($path) {
    $sites = [];
    if (!is_dir($path)) return $sites;
    if (file_exists($path . '/wp-config.php') && file_exists($path . '/wp-load.php')) {
        $sites[] = ['name' => basename($path), 'root' => realpath($path) ?: $path];
    }
    $dirs = @glob(rtrim($path, '/\\') . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($dirs as $dir) {
        $pub = $dir . '/public_html';
        if (is_dir($pub) && file_exists($pub . '/wp-load.php')) {
            $sites[] = ['name' => basename($dir), 'root' => realpath($pub) ?: $pub];
        } elseif (file_exists($dir . '/wp-load.php')) {
            $sites[] = ['name' => basename($dir), 'root' => realpath($dir) ?: $dir];
        }
    }
    return $sites;
}

/** 通过 wp-load.php 加载 WP，用 get_option 取 URL（参考 login_admin.php）— 单次请求只加载一个站点 */
function getUrlByWpLoad($wpRoot) {
    $load = rtrim($wpRoot, '/\\') . '/wp-load.php';
    if (!file_exists($load)) return ['home' => null, 'siteurl' => null, 'err' => '无 wp-load.php'];
    $oldErr = error_reporting(0);
    $oldHandler = set_error_handler(function ($n, $msg) { return true; });
    ob_start();
    if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);
    $err = null; $home = $siteurl = null;
    try {
        require $load;
        $home = function_exists('get_option') ? get_option('home') : null;
        $siteurl = function_exists('get_option') ? get_option('siteurl') : null;
        if (!$home && !$siteurl) $err = 'get_option 未返回 home/siteurl';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    restore_error_handler();
    error_reporting($oldErr);
    $out = ob_get_clean();
    if ($out && !$err) $err = 'WP 输出: ' . trim(preg_replace('/\s+/', ' ', strip_tags(substr($out, 0, 200))));
    return ['home' => $home, 'siteurl' => $siteurl, 'err' => $err];
}

/** 从 wp-config 解析 DB 并查询 home/siteurl（优先用，避免 wp-load 输出 HTML 错误页） */
function getUrlByDbConfig($wpRoot) {
    $cfg = @file_get_contents(rtrim($wpRoot, '/\\') . '/wp-config.php');
    if (!$cfg) return ['home' => null, 'siteurl' => null, 'err' => '无法读取 wp-config'];
    $g = function($n) use ($cfg) {
        if (preg_match("/define\s*\(\s*['\"]{$n}['\"]\s*,\s*['\"](.+?)['\"]\s*\)/i", $cfg, $m)) return trim($m[1]);
        return null;
    };
    $db = $g('DB_NAME'); $user = $g('DB_USER'); $pass = $g('DB_PASSWORD'); $host = $g('DB_HOST') ?: 'localhost';
    $pfx = 'wp_';
    if (preg_match('/\$table_prefix\s*=\s*[\'"](.+?)[\'"]\s*;/', $cfg, $m)) $pfx = $m[1];
    if (!class_exists('mysqli')) return ['home' => null, 'siteurl' => null, 'err' => '未安装 mysqli'];
    if (!$db || !$user) return ['home' => null, 'siteurl' => null, 'err' => 'wp-config 缺少 DB 配置'];
    set_error_handler(function() { return true; });
    $mysqli = @new mysqli($host, $user, $pass, $db);
    restore_error_handler();
    if ($mysqli->connect_error) return ['home' => null, 'siteurl' => null, 'err' => '数据库连接失败'];
    $t = '`' . str_replace('`', '``', $pfx . 'options') . '`';
    $res = $mysqli->query("SELECT option_name, option_value FROM $t WHERE option_name IN ('home','siteurl')");
    $mysqli->close();
    if (!$res) return ['home' => null, 'siteurl' => null, 'err' => '数据库查询失败'];
    $home = $siteurl = null;
    while ($row = $res->fetch_assoc()) {
        if ($row['option_name'] === 'home') $home = $row['option_value'];
        if ($row['option_name'] === 'siteurl') $siteurl = $row['option_value'];
    }
    $url = $home ?: $siteurl;
    return $url ? ['home' => $home, 'siteurl' => $siteurl, 'err' => null] : ['home' => null, 'siteurl' => null, 'err' => '表中无 home/siteurl'];
}

/** 判断是否为主域名：主域、www.主域=需要，其他二级及以上=暂不需要 */
function isPrimaryDomain($host) {
    if (!$host) return false;
    $h = strtolower(preg_replace('/:\d+$/', '', $host));
    if (substr($h, 0, 4) === 'www.') $h = substr($h, 4);
    $parts = explode('.', $h);
    $n = count($parts);
    if ($n <= 2) return true;
    $twoPartTlds = ['co.uk','com.au','co.jp','org.uk','com.cn','co.za','com.br','co.nz','com.mx','co.in','com.tw'];
    if ($n === 3 && in_array($parts[1].'.'.$parts[2], $twoPartTlds)) return true;
    return false;
}

/** 创建 1.txt 并访问验证：本服务器/非本服务器/未查到，验证后删除 1.txt */
function verifyServer($wpRoot, $url) {
    $file = rtrim($wpRoot, '/\\') . '/1.txt';
    @file_put_contents($file, '123');
    if (!$url) { @unlink($file); return '未查到'; }
    $u = rtrim($url, '/') . '/1.txt';
    $ch = @curl_init($u);
    if (!$ch) { @unlink($file); return '未查到'; }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WP-Scanner/1.0)',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($file);
    if ($body !== false && trim($body) === '123') return '本服务器';
    if ($code === 404 || ($code >= 200 && $code < 300 && $body !== false && trim($body) !== '123')) return '非本服务器';
    return '未查到';
}

/** 流量 API 固定密钥（与 sitedata 扩展一致） */
define('SITEDATA_TRAFFIC_SECRET', '2@3&^8d4$%H9,M');
define('SITEDATA_TRAFFIC_BASE', 'https://traffic.sitedata.dev');
/** 流量 API clientId（sitedata 登录后的 uuid，写死） */
define('SITEDATA_TRAFFIC_CLIENT_ID', '0f92e7db1db40be1f499b11d858dbdd49d95616311fd8ddadc79384e6032a164___');

/** 调用 sitedata traffic 接口获取 Monthly Visits，返回数值或 null，错误时返回 ['err' => msg] */
function fetchTrafficMonthlyVisits($host, $clientId) {
    $host = trim($host);
    $clientId = trim($clientId);
    if ($host === '' || $clientId === '') return ['err' => '缺少 host 或 clientId'];
    $timestamp = (string)(int)(microtime(true) * 1000);
    $signStr = $clientId . $timestamp . SITEDATA_TRAFFIC_SECRET;
    $sign = substr(hash('sha256', $signStr, false), 0, 32);
    $url = SITEDATA_TRAFFIC_BASE . '/?domain=' . rawurlencode($host)
        . '&source=extension&clientId=' . rawurlencode($clientId)
        . '&timestamp=' . rawurlencode($timestamp)
        . '&sign=' . rawurlencode($sign);
    $ch = @curl_init($url);
    if (!$ch) return ['err' => 'curl 初始化失败'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WP-Scanner/1.0)',
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 403) return ['err' => 'API 403'];
    if ($code === 429) return ['err' => '请求过于频繁'];
    if ($body === false || $code < 200 || $code >= 300) return ['err' => 'HTTP ' . $code];
    $body = ltrim($body, "\xEF\xBB\xBF");
    $body = trim($body);
    if ($body === '') return ['err' => '响应为空'];
    // 先做轻量提取，避免对十几万字符的 JSON 做完整 decode（接口返回体很大）
    if (preg_match('/"Engagments"\s*:\s*\{[^}]*"Visits"\s*:\s*"(\d+)"/', $body, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/"Engagments"\s*:\s*\{[^}]*"Visits"\s*:\s*(\d+)/', $body, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/"Visits"\s*:\s*"(\d+)"/', $body, $m)) {
        return (int)$m[1];
    }
    if (strpos($body, '"code":403') !== false || strpos($body, '"code": 403') !== false) {
        return ['err' => 'API 403'];
    }
    $data = @json_decode($body, true);
    if (!is_array($data)) return ['err' => '响应非 JSON'];
    if (!empty($data['code']) && $data['code'] === 403) return ['err' => 'API 403'];
    if (isset($data['Engagments']['Visits']) && $data['Engagments']['Visits'] !== '' && $data['Engagments']['Visits'] !== null) {
        return (int)$data['Engagments']['Visits'];
    }
    if (!empty($data['EstimatedMonthlyVisits']) && is_numeric($data['EstimatedMonthlyVisits'])) {
        return (int)$data['EstimatedMonthlyVisits'];
    }
    if (is_array($data['EstimatedMonthlyVisits']) && !empty($data['EstimatedMonthlyVisits'])) {
        $keys = array_keys($data['EstimatedMonthlyVisits']);
        rsort($keys, SORT_STRING);
        $v = $data['EstimatedMonthlyVisits'][$keys[0]];
        return is_numeric($v) ? (int)$v : null;
    }
    return null;
}

// AJAX：流量接口 action=traffic
if (!empty($_REQUEST['ajax']) && isset($_REQUEST['action']) && $_REQUEST['action'] === 'traffic') {
    @ini_set('display_errors', 0);
    @ini_set('log_errors', 1);
    error_reporting(0);
    header('Content-Type: application/json; charset=utf-8');
    $host = isset($_REQUEST['host']) ? trim($_REQUEST['host']) : '';
    $clientId = SITEDATA_TRAFFIC_CLIENT_ID;
    if ($host === '') {
        echo json_encode(['ok' => false, 'err' => '缺少 host']);
        exit;
    }
    $result = fetchTrafficMonthlyVisits($host, $clientId);
    if (is_array($result) && isset($result['err'])) {
        echo json_encode(['ok' => false, 'err' => $result['err']]);
    } else {
        echo json_encode(['ok' => true, 'monthlyVisits' => $result]);
    }
    exit;
}

// AJAX：只取单个站点 URL + 创建 1.txt 验证（ajax=1 时必须返回 JSON，避免输出 HTML）
if (!empty($_REQUEST['ajax'])) {
    @ini_set('display_errors', 0);
    @ini_set('log_errors', 1);
    error_reporting(0);
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
    if ($ajaxRoot === '' || !is_dir($ajaxRoot)) {
        $out = json_encode(['home' => null, 'siteurl' => null, 'err' => '路径无效或不存在', 'server' => '未查到', 'need' => '-']);
    } else {
        try {
            $r = getUrlByDbConfig($ajaxRoot);
            $url = $r['home'] ?? $r['siteurl'] ?? '';
            if (!$url && empty($r['err'])) $r = getUrlByWpLoad($ajaxRoot);
            $url = $r['home'] ?? $r['siteurl'] ?? '';
            $r['server'] = ($url ? verifyServer($ajaxRoot, $url) : '未查到');
            $host = $url ? preg_replace('/^https?:\/\//i', '', parse_url($url, PHP_URL_HOST) ?: '') : '';
            $r['need'] = $host ? (isPrimaryDomain($host) ? '需要' : '暂不需要') : '-';
            $out = json_encode($r);
        } catch (Throwable $e) {
            $out = json_encode(['home' => null, 'siteurl' => null, 'err' => $e->getMessage(), 'server' => '未查到', 'need' => '-']);
        }
    }
    ob_end_clean();
    echo $out;
    exit;
}

$sites = scanWpSites($rootPath);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress 站点扫描</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        .wrap { max-width: 100%; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; overflow-x: auto; }
        h1 { margin-top: 0; font-size: 1.3rem; }
        form { margin-bottom: 16px; }
        input[type=text] { padding: 8px 12px; width: 400px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 16px; background: #0073aa; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        .url-cell a { color: #0073aa; text-decoration: none; }
        .url-cell a:hover { text-decoration: underline; }
        .err { color: #c00; }
        .cpy { padding: 4px 10px; font-size: 12px; cursor: pointer; margin-right:6px; }
        .server-ok { color: #0a7b0a; }
        .server-no { color: #c00; }
        .server-unk { color: #999; }
        .need-ok { color: #0a7b0a; }
        .need-no { color: #999; }
        .traffic-loading { display: inline-flex; align-items: center; gap: 6px; color: #666; }
        .traffic-loading::before { content: ""; width: 14px; height: 14px; border: 2px solid #ddd; border-top-color: #0073aa; border-radius: 50%; animation: traffic-spin 0.7s linear infinite; }
        @keyframes traffic-spin { to { transform: rotate(360deg); } }
        .traffic-value { color: #0a7b0a; font-weight: 500; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🔍 WordPress 站点扫描</h1>
    <form method="get">
        <label>根目录：</label>
        <input type="text" name="path" value="<?php echo htmlspecialchars($rootPath); ?>" placeholder="/home1/chnjoimy/public_html">
        <button type="submit">扫描</button>
    </form>
    <p>共 <strong><?php echo count($sites); ?></strong> 个站点
        <button type="button" class="cpy-all">一键复制 host 列</button>
        <button type="button" class="cpy-sel">复制选中 host</button>
        <button type="button" class="btn-traffic">流量侦测</button>
    </p>
    <table>
        <thead><tr><th><input type="checkbox" class="chk-all" title="全选"></th><th>名称</th><th>路径</th><th>网址</th><th>host</th><th>分类</th><th>服务器</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($sites as $s): ?>
        <tr data-root="<?php echo htmlspecialchars($s['root']); ?>">
            <td><input type="checkbox" class="chk-host"></td>
            <td><?php echo htmlspecialchars($s['name']); ?></td>
            <td style="word-break:break-all;font-size:12px;color:#666"><?php echo htmlspecialchars($s['root']); ?></td>
            <td class="url-cell">加载中…</td>
            <td class="host-cell"></td>
            <td class="need-cell"></td>
            <td class="server-cell"></td>
            <td class="act-cell"></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
document.querySelectorAll('tbody tr[data-root]').forEach(function(tr){
    var root = tr.getAttribute('data-root');
    var urlCell = tr.querySelector('.url-cell');
    var hostCell = tr.querySelector('.host-cell');
    var needCell = tr.querySelector('.need-cell');
    var serverCell = tr.querySelector('.server-cell');
    var actCell = tr.querySelector('.act-cell');
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?ajax=1&root=' + encodeURIComponent(root));
    xhr.onload = function(){
        var txt = xhr.responseText || '';
        var parseJson = function(s){
            try { return JSON.parse(s); } catch(_) { return null; }
        };
        var r = parseJson(txt);
        if (!r && txt.indexOf('{') >= 0) {
            var i = txt.indexOf('{'), depth = 0, j = i;
            for (; j < txt.length; j++) {
                if (txt[j] === '{') depth++;
                else if (txt[j] === '}') { depth--; if (depth === 0) break; }
            }
            if (depth === 0) r = parseJson(txt.substring(i, j + 1));
        }
        try {
            if (!r) throw new Error('no json');
            var url = r.home || r.siteurl || '';
            var host = url ? url.replace(/^https?:\/\//i, '') : '';
            if (r.err) { urlCell.className = 'url-cell err'; urlCell.textContent = '❌ ' + r.err; }
            else {
                var esc = function(s){ return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); };
                if (url) {
                    urlCell.innerHTML = '<a href="' + esc(url) + '" target="_blank" rel="noopener"></a>';
                    urlCell.querySelector('a').textContent = url;
                    hostCell.textContent = host;
                    hostCell.dataset.host = host;
                    tr.querySelector('.chk-host').dataset.host = host;
                    var need = r.need || '-';
                    needCell.textContent = need;
                    needCell.className = 'need-cell ' + (need === '需要' ? 'need-ok' : 'need-no');
                    var s = r.server || '未查到';
                    serverCell.textContent = s;
                    serverCell.className = 'server-cell ' + (s === '本服务器' ? 'server-ok' : s === '非本服务器' ? 'server-no' : 'server-unk');
                    actCell.innerHTML = '<button class="cpy" type="button" data-url="">复制链接</button>';
                    actCell.querySelector('.cpy').dataset.url = url;
                } else { urlCell.textContent = '-'; }
            }
        } catch(e) {
            urlCell.className = 'url-cell err';
            var msg = (txt || xhr.statusText || '未知').replace(/<[^>]+>/g,'').replace(/\s+/g,' ').trim().substring(0, 150);
            urlCell.textContent = '解析失败: ' + (msg || '响应非 JSON');
        }
    };
    xhr.onerror = function(){ urlCell.className = 'url-cell err'; urlCell.textContent = '请求失败: ' + (xhr.statusText || '网络错误'); };
    xhr.send();
});
function copyText(s){ if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(s); else { var t = document.createElement('textarea'); t.value = s; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); } }
document.addEventListener('click', function(e){
    if (e.target.classList.contains('chk-all')) {
        document.querySelectorAll('.chk-host').forEach(function(c){ c.checked = e.target.checked; });
        return;
    }
    if (e.target.classList.contains('cpy-all')) {
        var list = [].map.call(document.querySelectorAll('.host-cell[data-host]'), function(el){ return el.dataset.host; });
        copyText(list.join('\n')); e.target.textContent = '已复制 ' + list.length + ' 个'; setTimeout(function(){ e.target.textContent = '一键复制 host 列'; }, 1500);
        return;
    }
    if (e.target.classList.contains('cpy-sel')) {
        var list = [].map.call(document.querySelectorAll('.chk-host:checked[data-host]'), function(el){ return el.dataset.host; });
        copyText(list.join('\n')); e.target.textContent = '已复制 ' + list.length + ' 个'; setTimeout(function(){ e.target.textContent = '复制选中 host'; }, 1500);
        return;
    }
    if (e.target.classList.contains('cpy') && e.target.dataset.url) {
        copyText(e.target.dataset.url); e.target.textContent = '已复制';
    }
    if (e.target.classList.contains('btn-traffic')) {
        var rows = [];
        document.querySelectorAll('tbody tr[data-root]').forEach(function(tr){
            var sc = tr.querySelector('.server-cell');
            var hc = tr.querySelector('.host-cell');
            if (sc && hc && sc.textContent.trim() === '本服务器' && hc.dataset.host) {
                rows.push({ tr: tr, host: hc.dataset.host, cell: sc });
            }
        });
        if (rows.length === 0) { alert('当前没有「本服务器」的站点'); return; }
        rows.forEach(function(r){ r.cell.innerHTML = '<span class="traffic-loading">检测中…</span>'; r.cell.className = 'server-cell'; });
        var queue = rows.slice();
        var delayMs = 2000;
        function formatVisits(v) {
            if (v == null) return '—';
            v = Number(v);
            if (v >= 1e9) return (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
            if (v >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
            if (v >= 1e3) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
            return String(Math.floor(v));
        }
        function runNext() {
            if (queue.length === 0) return;
            var item = queue.shift();
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '?ajax=1&action=traffic&host=' + encodeURIComponent(item.host));
            xhr.onload = function() {
                var cell = item.cell;
                cell.classList.remove('server-ok', 'server-no', 'server-unk', 'traffic-value');
                try {
                    var data = JSON.parse(xhr.responseText || '{}');
                    if (data.ok && data.monthlyVisits != null) {
                        var raw = data.monthlyVisits;
                        cell.textContent = formatVisits(raw);
                        cell.title = '月访问量: ' + String(Math.floor(Number(raw)));
                        cell.className = 'server-cell traffic-value';
                    } else {
                        cell.textContent = data.err || '无数据';
                        cell.className = 'server-cell server-unk';
                    }
                } catch(_) {
                    cell.textContent = '解析失败';
                    cell.className = 'server-cell server-unk';
                }
                if (queue.length > 0) setTimeout(runNext, delayMs);
            };
            xhr.onerror = function() {
                item.cell.innerHTML = '';
                item.cell.textContent = '请求失败';
                item.cell.className = 'server-cell server-unk';
                if (queue.length > 0) setTimeout(runNext, delayMs);
            };
            xhr.send();
        }
        runNext();
    }
});
</script>
</body>
</html>
