<?php
/**
 * Plugin Name:       LLM CON TABELLE
 * Description:       Storie, utenti e community in tabelle MySQL (no JSON strutturato). Parallelo a LLS, senza migrazione.
 * Version:           2.0.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            LLM CON TABELLE
 * Text Domain:       llm-con-tabelle
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LLM_TABELLE_VERSION', '2.0.48' );
define( 'LLM_TABELLE_FILE', __FILE__ );
define( 'LLM_TABELLE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLM_TABELLE_URL', plugin_dir_url( __FILE__ ) );
define( 'LLM_STORY_CPT', 'llm_story' );
define( 'LLM_ACTIVITY_CPT', 'llm_activity' );

require_once LLM_TABELLE_DIR . 'includes/class-llm-tabelle-database.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-languages.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-story-meta.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-post-type.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-activity-cpt.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-user-meta.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-story-repository.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-community.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-user-stats.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-admin-story.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-admin-users.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-admin-community.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-admin-design-system.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-category-translations.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-hero-translations.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-demo-stories.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-demo-users.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-demo-community.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-story-template-vars.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-elementor-dynamic-tags.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-phrase-game-i18n.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-story-game-progress.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-story-phrase-game.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-story-progress-bar-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-header-ui-icons.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-header-user-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-user-stat-shortcodes.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-user-profile-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-learning-lang-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-elementor-group-control-related-unlocked.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-elementor-homepage-stories-loop.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-story-loop-filters-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-storie-filtri-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-continua-storie-loop.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-continua-filtri-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-elementor-unlocked-stories-loop.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-area-personale-loop-filters-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-user-activity-feed-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-community-feed-i18n.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-community-feed-shortcode.php';
require_once LLM_TABELLE_DIR . 'includes/class-llm-bravo-balance-shortcode.php';

/**
 * Aggiorna schema DB se la versione salvata è inferiore (es. da 1.1 → 2.0).
 */
function llm_tabelle_maybe_upgrade_db() {
	if ( version_compare( (string) get_option( LLM_Tabelle_Database::OPT_VERSION, '0' ), LLM_Tabelle_Database::DB_VERSION, '<' ) ) {
		LLM_Tabelle_Database::install();
	}
}
add_action( 'plugins_loaded', 'llm_tabelle_maybe_upgrade_db', 2 );

/**
 * Avvio.
 */
function llm_tabelle_boot() {
	load_plugin_textdomain( 'llm-con-tabelle', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	LLM_Post_Type::init();
	LLM_Activity_CPT::init();
	LLM_Story_Meta::init();
	LLM_User_Meta::init();
	LLM_Community::init();
	LLM_User_Stats::init();
	LLM_Admin_Story::init();
	LLM_Admin_Users::init();
	LLM_Admin_Community::init();
	LLM_Admin_Design_System::init();
	LLM_Category_Translations::init();
	LLM_Hero_Translations::init();
	LLM_Demo_Stories::init();
	LLM_Demo_Users::init();
	LLM_Demo_Community::init();

	add_shortcode( 'llm_story_field', array( 'LLM_Story_Template_Vars', 'shortcode_field' ) );
	LLM_Story_Loop_Filters_Shortcode::init();
	LLM_Storie_Filtri_Shortcode::init();
	LLM_Continua_Storie_Loop::init();
	LLM_Continua_Filtri_Shortcode::init();
	LLM_Header_User_Shortcode::init();
	LLM_User_Stat_Shortcodes::init();
	LLM_User_Profile_Shortcode::init();
	LLM_Learning_Lang_Shortcode::init();
	LLM_Elementor_Homepage_Stories_Loop::init();
	LLM_Elementor_Unlocked_Stories_Loop::init();
	LLM_Area_Personale_Loop_Filters_Shortcode::init();
	LLM_User_Activity_Feed_Shortcode::init();
	LLM_Community_Feed_Shortcode::init();
	LLM_Bravo_Balance_Shortcode::init();
	LLM_Story_Phrase_Game::init();
	LLM_Story_Progress_Bar_Shortcode::init();
}
add_action( 'plugins_loaded', 'llm_tabelle_boot', 5 );

/**
 * Registra llm-ui + font Manrope (frontend e admin).
 * Altri shortcode: wp_enqueue_style( 'llm-ui' ).
 */
function llm_tabelle_register_shared_style_handles() {
	wp_register_style(
		'llm-font-manrope',
		'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap',
		array(),
		null
	);
	wp_register_style(
		'llm-ui',
		LLM_TABELLE_URL . 'assets/llm-ui.css',
		array( 'llm-font-manrope' ),
		LLM_TABELLE_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'llm_tabelle_register_shared_style_handles', 1 );
add_action( 'admin_init', 'llm_tabelle_register_shared_style_handles', 1 );

/**
 * Script filtri loop storie (AJAX).
 */
function llm_tabelle_register_loop_filters_script() {
	wp_register_script(
		'llm-loop-stories-filters',
		LLM_TABELLE_URL . 'assets/llm-loop-stories-filters.js',
		array(),
		LLM_TABELLE_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'llm_tabelle_register_loop_filters_script', 3 );

/**
 * Elementor carica prima (ordine alfabetico): l’hook elementor/loaded è già scattato
 * quando questo file viene letto. Registriamo i Dynamic Tag su plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			LLM_Elementor_Dynamic_Tags::init();
		}
	},
	20
);

add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once LLM_TABELLE_DIR . 'includes/elementor/class-llm-elementor-widget-loop-stories-filters.php';
		if ( class_exists( 'LLM_Elementor_Widget_Loop_Stories_Filters' ) ) {
			$widgets_manager->register( new LLM_Elementor_Widget_Loop_Stories_Filters() );
		}
	},
	10
);

/**
 * Attivazione: CPT + tabelle + storie demo + rewrite.
 */
function llm_tabelle_activate() {
	LLM_Post_Type::register();
	LLM_Activity_CPT::register();
	LLM_Tabelle_Database::install();
	LLM_Demo_Stories::seed_on_activate();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'llm_tabelle_activate' );

/**
 * Elimina tutti i post attività LLM (pulisce relazioni in tabelle via hook).
 */
function llm_tabelle_delete_all_activities() {
	$ids = get_posts(
		array(
			'post_type'      => LLM_ACTIVITY_CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}

/**
 * Reset opzioni demo per poter ripopolare al riavvio.
 */
function llm_tabelle_reset_demo_options() {
	delete_option( 'llm_demo_wp_users_v1' );
	delete_option( 'llm_demo_wp_users_v2' );
	delete_option( 'llm_demo_wp_users_v3' );
	delete_option( 'llm_demo_community_v1' );
	delete_option( 'llm_demo_community_v2' );
	$users = get_users(
		array(
			'login__in' => array( 'llm_learn_1', 'llm_learn_2', 'llm_learn_3' ),
			'fields'    => 'ID',
		)
	);
	foreach ( $users as $uid ) {
		delete_user_meta( (int) $uid, LLM_Demo_Users::USER_SEEDED );
	}
}

/**
 * Disattivazione: attività, post demo storie, DROP tabelle, reset demo.
 */
function llm_tabelle_deactivate() {
	llm_tabelle_delete_all_activities();
	LLM_Demo_Stories::delete_demo_posts();
	llm_tabelle_reset_demo_options();
	LLM_Tabelle_Database::uninstall();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'llm_tabelle_deactivate' );
