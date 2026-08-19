<?php
error_reporting(0);
ini_set("display_errors", 0);
@set_time_limit(0);
header("Content-Type: application/json; charset=utf-8");
set_error_handler(function () { return true; });

// 仅在请求时接受扩展传来的 token / repo / ref，不在服务器落盘或持久化
function findWPRoot() {
    $path = realpath(dirname(__FILE__));
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($path . "/wp-config.php")) return $path;
        $parent = dirname($path);
        if ($parent === $path) break;
        $path = $parent;
    }
    return null;
}
function ensureDir($dir) {
    if (is_dir($dir)) return is_writable($dir) || @chmod($dir, 0755);
    return @mkdir($dir, 0755, true);
}
function fetchGitHubFile($pathInRepo, $token, $repo, $ref) {
    $out = array("body" => null, "err" => "");
    if ($token === "") { $out["err"] = "token required"; return $out; }
    $pathInRepo = trim(str_replace("\\", "/", $pathInRepo), "/");
    if ($pathInRepo === "") { $out["err"] = "empty path"; return $out; }
    $apiUrl = "https://api.github.com/repos/" . $repo . "/contents/" . str_replace(" ", "%20", $pathInRepo) . "?ref=" . $ref;
    $headers = array(
        "User-Agent: PHP-GitHub-Client",
        "Authorization: token " . $token,
        "Accept: application/vnd.github.v3.raw"
    );
    if (function_exists("curl_init")) {
        $ch = @curl_init($apiUrl);
        if (!$ch) { $out["err"] = "curl_init fail"; return $out; }
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers
        ));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code !== 200) {
            $out["err"] = $code ? "HTTP " . $code : ($cerr ?: "curl fail");
            return $out;
        }
        $out["body"] = $body;
        return $out;
    }
    $ctx = stream_context_create(array(
        "http" => array(
            "timeout" => 30,
            "header" => implode("\r\n", $headers) . "\r\n",
            "follow_location" => 1
        ),
        "ssl" => array("verify_peer" => false)
    ));
    $body = @file_get_contents($apiUrl, false, $ctx);
    if ($body === false) { $out["err"] = "file_get_contents fail"; return $out; }
    $out["body"] = $body;
    return $out;
}

// GET：仅告诉扩展“文件存在”，不暴露任何敏感信息
if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    echo json_encode(array("success" => true, "message" => "file exists"));
    exit;
}

// POST：由浏览器扩展发起，带上 token / repo / ref / picked，让本文件去 GitHub 拉取
$raw = @file_get_contents("php://input");
$json = $raw ? @json_decode($raw, true) : null;
if (!is_array($json) || !isset($json["action"]) || $json["action"] !== "set_credentials") {
    echo json_encode(array("success" => false, "error" => "invalid request"));
    exit;
}
$token  = isset($json["token"]) ? (string)$json["token"] : "";
$repo   = isset($json["repo"])  ? (string)$json["repo"]  : "";
$ref    = isset($json["ref"])   ? (string)$json["ref"]   : "main";
$picked = isset($json["picked"]) && is_array($json["picked"]) ? $json["picked"] : array();

if ($token === "" || $repo === "") {
    echo json_encode(array("success" => false, "error" => "token / repo required"));
    exit;
}

$wpRoot = findWPRoot();
if (!$wpRoot) {
    echo json_encode(array("success" => false, "error" => "no wp root"));
    exit;
}
$base      = rtrim(str_replace("\\", "/", $wpRoot), "/");
$targetDir = $base . "/wp-content/plugins/advanced-code-manager";
if (!ensureDir($targetDir)) {
    echo json_encode(array("success" => false, "error" => "cannot create target dir"));
    exit;
}
$resolved = @realpath($targetDir);
if ($resolved !== false) $targetDir = $resolved;

$result = array("ok" => 0, "fail" => 0, "rows" => array(), "firstErr" => "");
foreach ($picked as $rel) {
    if (!is_string($rel)) continue;
    $rel = trim(str_replace("\\", "/", $rel), "/");
    if ($rel === "" || strpos($rel, "..") !== false) continue;
    $saveRel = preg_replace("#^advanced-code-manager/#", "", $rel);
    if ($saveRel === "" || $saveRel === "advanced-code-manager") continue;
    $savePath = $targetDir . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $saveRel);
    $saveDir  = dirname($savePath);
    if (!ensureDir($saveDir)) {
        $result["fail"]++;
        $result["rows"][] = array("file" => $rel, "status" => "fail", "msg" => "创建目录失败", "path" => $savePath);
        if ($result["firstErr"] === "") $result["firstErr"] = "创建目录失败";
        continue;
    }
    $res = fetchGitHubFile($rel, $token, $repo, $ref);
    if ($res["body"] === null) {
        $result["fail"]++;
        $result["rows"][] = array("file" => $rel, "status" => "fail", "msg" => $res["err"] ?: "拉取失败", "path" => $savePath);
        if ($result["firstErr"] === "") $result["firstErr"] = $res["err"];
        continue;
    }
    $written = @file_put_contents($savePath, $res["body"]);
    if ($written === false) {
        $result["fail"]++;
        $result["rows"][] = array("file" => $rel, "status" => "fail", "msg" => "写入失败", "path" => $savePath);
        if ($result["firstErr"] === "") $result["firstErr"] = "写入失败";
        continue;
    }
    $result["ok"]++;
    $result["rows"][] = array("file" => $rel, "status" => "ok", "msg" => $written . " bytes", "path" => $savePath);
}
if (count($picked) === 0 && $result["firstErr"] === "") {
    $result["firstErr"] = "no files picked";
}

echo json_encode(array("success" => true, "pull" => $result));
exit;
