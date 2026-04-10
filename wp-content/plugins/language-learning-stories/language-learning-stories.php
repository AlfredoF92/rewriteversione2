<?php
/**
 * Plugin Name:       Language Learning Stories
 * Description:       Storie didattiche multilingua: tipo di contenuto, tassonomie, meta (frasi, immagini, coin).
 * Version:             0.1.3
 * Requires at least:   6.0
 * Requires PHP:        7.4
 * Author:              Language Learning Stories
 * Text Domain:         language-learning-stories
 * Domain Path:         /languages
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LLS_VERSION', '0.1.3' );
define( 'LLS_PLUGIN_FILE', __FILE__ );
define( 'LLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LLS_CPT', 'lls_story' );
define( 'LLS_ACTIVITY_CPT', 'lls_activity' );

require_once LLS_PLUGIN_DIR . 'includes/class-lls-languages.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-post-type.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-taxonomies.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-story-meta.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-admin-story.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-demo-content.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-activity-cpt.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-user-meta.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-community.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-user-stats.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-admin-users.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-admin-community.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-demo-users.php';
require_once LLS_PLUGIN_DIR . 'includes/class-lls-demo-community.php';

/**
 * Avvio plugin.
 */
function lls_boot() {
	load_plugin_textdomain( 'language-learning-stories', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	LLS_Post_Type::init();
	LLS_Taxonomies::init();
	LLS_Activity_CPT::init();
	LLS_Story_Meta::init();
	LLS_User_Meta::init();
	LLS_Community::init();
	LLS_Admin_Story::init();
	LLS_Admin_Users::init();
	LLS_Admin_Community::init();
	LLS_Demo_Content::init();
	LLS_Demo_Users::init();
	LLS_Demo_Community::init();
}
add_action( 'plugins_loaded', 'lls_boot' );

/**
 * Attivazione: flush rewrite rules.
 */
function lls_activate() {
	LLS_Post_Type::register();
	LLS_Taxonomies::register();
	LLS_Activity_CPT::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'lls_activate' );

/**
 * Disattivazione: flush rewrite rules.
 */
function lls_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'lls_deactivate' );
