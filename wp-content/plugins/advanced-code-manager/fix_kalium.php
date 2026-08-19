<?php
/**
 * CVE-2025-49926 Kalium 漏洞修复（就地补丁，不整文件覆盖）
 *
 * GET ?ping=1    → 探活
 * GET ?restore=1 → 从最新 .bak 恢复主题文件
 * GET            → 对站点现有 core-hook-functions.php 打安全补丁
 */

@ini_set('display_errors', '0');
@set_time_limit(120);
header('Content-Type: application/json; charset=utf-8');

define('ACM_KALIUM_THEME_SLUG', 'kalium');
define('ACM_KALIUM_PATCH_MARKER', '_kalium_is_allowed_endless_callback');
define('ACM_KALIUM_HANDLER_FN', '_kalium_endless_pagination_get_paged_items');

function acm_json_exit($ok, $data = array()) {
    echo json_encode(array_merge(array('success' => $ok), $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['ping']) && (string) $_GET['ping'] === '1') {
    acm_json_exit(true, array(
        'ping'   => true,
        'script' => 'fix_kalium.php',
        'mode'   => 'in_place_patch',
    ));
}

function acm_find_wp_load() {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (is_file($dir . '/wp-load.php')) {
            return $dir . '/wp-load.php';
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    return null;
}

function acm_is_patched_content($content) {
    return is_string($content) && strpos($content, ACM_KALIUM_PATCH_MARKER) !== false;
}

function acm_resolve_kalium_target() {
    $candidates = array();

    if (defined('WP_CONTENT_DIR')) {
        $candidates[] = WP_CONTENT_DIR . '/themes/' . ACM_KALIUM_THEME_SLUG . '/includes/functions/core-hook-functions.php';
    }

    if (function_exists('get_theme_root')) {
        $candidates[] = get_theme_root() . '/' . ACM_KALIUM_THEME_SLUG . '/includes/functions/core-hook-functions.php';
    }

    if (function_exists('get_template_directory')) {
        $tpl = get_template_directory();
        if ($tpl) {
            $candidates[] = $tpl . '/includes/functions/core-hook-functions.php';
        }
    }

    $seen = array();
    foreach ($candidates as $path) {
        if (!$path || isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;
        if (is_file($path)) {
            return $path;
        }
    }

    foreach ($candidates as $path) {
        if (!$path || isset($seen[$path])) {
            continue;
        }
        $dir = dirname($path);
        if (is_dir($dir)) {
            return $path;
        }
    }

    return null;
}

function acm_find_latest_backup($target) {
    $dir = dirname($target);
    $base = basename($target);
    $pattern = $dir . '/' . $base . '.bak.*';
    $files = glob($pattern);
    if (!$files) {
        return null;
    }
    rsort($files);
    return $files[0];
}

function acm_restore_from_backup($target) {
    $backup = acm_find_latest_backup($target);
    if (!$backup || !is_readable($backup)) {
        return array(false, 'no_backup_found');
    }
    if (!@copy($backup, $target)) {
        return array(false, 'restore_failed');
    }
    return array(true, $backup);
}

function acm_whitelist_function_snippet() {
    return <<<'PHP'
/**
 * Validate endless pagination callbacks (CVE-2025-49926).
 *
 * @param mixed $callback
 *
 * @return bool
 */
function _kalium_is_allowed_endless_callback( $callback ) {
	$allowed = apply_filters(
		'kalium_endless_pagination_allowed_callbacks',
		array(
			'_kalium_woocommerce_infinite_scroll_pagination_handler',
			'kalium_blog_loop_post_template',
		)
	);

	return is_string( $callback )
		&& in_array( $callback, $allowed, true )
		&& is_callable( $callback );
}

PHP;
}

function acm_apply_security_patch($content) {
    if (!is_string($content) || $content === '') {
        return array(false, 'empty_source');
    }

    if (acm_is_patched_content($content)) {
        return array(true, 'already_patched', $content);
    }

    if (strpos($content, 'function ' . ACM_KALIUM_HANDLER_FN) === false) {
        return array(false, 'handler_not_found', $content);
    }

    $patched = $content;

    if (strpos($patched, ACM_KALIUM_PATCH_MARKER) === false) {
        $needle = 'function ' . ACM_KALIUM_HANDLER_FN . '()';
        $pos = strpos($patched, $needle);
        if ($pos === false) {
            return array(false, 'handler_not_found', $content);
        }
        $patched = substr_replace($patched, acm_whitelist_function_snippet(), $pos, 0);
    }

    $patched = str_replace(
        array(
            "array_map( 'absint', \$pagination['fetchedItems'] )",
            'array_map( \'absint\', $pagination[\'fetchedItems\'] )',
        ),
        "array_map( 'absint', (array) ( \$pagination['fetchedItems'] ?? [] ) )",
        $patched
    );

    $loop_handler_old = <<<'PHP'
	if ( $loop_handler ) {
		wp_send_json_success( call_user_func( $loop_handler, $posts_per_page, $total_items, $fetched_ids, $wp_query_args ) );
	}
PHP;
    $loop_handler_new = <<<'PHP'
	if ( $loop_handler ) {
		if ( ! _kalium_is_allowed_endless_callback( $loop_handler ) ) {
			wp_send_json_error( array( 'message' => 'Invalid loop handler.', 'code' => 'invalid_endless_callback' ) );
		}

		wp_send_json_success( call_user_func( $loop_handler, $posts_per_page, $total_items, $fetched_ids, $wp_query_args ) );
	}
PHP;
    if (strpos($patched, $loop_handler_old) !== false) {
        $patched = str_replace($loop_handler_old, $loop_handler_new, $patched);
    } else {
        $patched = preg_replace(
            '/if\s*\(\s*\$loop_handler\s*\)\s*\{\s*wp_send_json_success\(\s*call_user_func\(\s*\$loop_handler\s*,/s',
            "if ( \$loop_handler ) {\n\t\tif ( ! _kalium_is_allowed_endless_callback( \$loop_handler ) ) {\n\t\t\twp_send_json_error( array( 'message' => 'Invalid loop handler.', 'code' => 'invalid_endless_callback' ) );\n\t\t}\n\n\t\twp_send_json_success( call_user_func( \$loop_handler,",
            $patched,
            1,
            $count
        );
        if ($count < 1 && !acm_is_patched_content($patched)) {
            return array(false, 'loop_handler_pattern_not_found', $content);
        }
    }

    $template_old = <<<'PHP'
			if ( function_exists( $loop_template ) ) {
				call_user_func( $loop_template );
			}
PHP;
    $template_new = <<<'PHP'
			if ( $loop_template && _kalium_is_allowed_endless_callback( $loop_template ) ) {
				call_user_func( $loop_template );
			}
PHP;
    if (strpos($patched, $template_old) !== false) {
        $patched = str_replace($template_old, $template_new, $patched);
    } else {
        $patched = preg_replace(
            '/if\s*\(\s*function_exists\(\s*\$loop_template\s*\)\s*\)\s*\{\s*call_user_func\(\s*\$loop_template\s*\)\s*;\s*\}/s',
            $template_new,
            $patched,
            1
        );
    }

    if (!acm_is_patched_content($patched)) {
        return array(false, 'patch_verify_failed', $content);
    }

    return array(true, 'patched', $patched);
}

$wp_load = acm_find_wp_load();
if (!$wp_load) {
    acm_json_exit(false, array('error' => 'wp_load_not_found'));
}

require_once $wp_load;

$target = acm_resolve_kalium_target();
if (!$target) {
    acm_json_exit(false, array('error' => 'kalium_theme_not_found'));
}

if (isset($_GET['restore']) && (string) $_GET['restore'] === '1') {
    list($ok, $detail) = acm_restore_from_backup($target);
    if (!$ok) {
        acm_json_exit(false, array('error' => $detail, 'target' => $target));
    }
    acm_json_exit(true, array(
        'message' => 'restored',
        'target'  => $target,
        'backup'  => $detail,
    ));
}

$current = file_get_contents($target);
if ($current === false || $current === '') {
    acm_json_exit(false, array('error' => 'read_target_failed', 'target' => $target));
}

if (acm_is_patched_content($current)) {
    acm_json_exit(true, array(
        'message' => 'already_patched',
        'target'  => $target,
        'mode'    => 'in_place_patch',
    ));
}

list($ok, $msg, $patched_content) = acm_apply_security_patch($current);
if (!$ok) {
    acm_json_exit(false, array(
        'error'  => $msg,
        'target' => $target,
        'hint'   => '若站点已白屏，请访问 fix_kalium.php?restore=1 或手动恢复 .bak 备份',
    ));
}

if ($msg === 'already_patched') {
    acm_json_exit(true, array('message' => $msg, 'target' => $target));
}

$backup = '';
if (is_file($target)) {
    $backup = $target . '.bak.' . gmdate('YmdHis');
    if (!@copy($target, $backup)) {
        $backup = '';
    }
}

$target_dir = dirname($target);
if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true)) {
    acm_json_exit(false, array('error' => 'target_dir_missing', 'target' => $target));
}

if (@file_put_contents($target, $patched_content, LOCK_EX) === false) {
    acm_json_exit(false, array('error' => 'write_failed', 'target' => $target));
}

$written = file_get_contents($target);
if ($written === false || !acm_is_patched_content($written)) {
    if ($backup && is_file($backup)) {
        @copy($backup, $target);
    }
    acm_json_exit(false, array('error' => 'verify_failed', 'target' => $target));
}

acm_json_exit(true, array(
    'message' => 'patched',
    'target'  => $target,
    'backup'  => $backup ?: null,
    'mode'    => 'in_place_patch',
    'bytes'   => strlen($patched_content),
));
