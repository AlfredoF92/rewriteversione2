<?php
/**
 * Tabelle MySQL (nessun JSON): storie, utenti, ledger, community / Bravi.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Tabelle_Database {

	const DB_VERSION = '2.1.1';

	const OPT_VERSION = 'llm_tabelle_db_version';

	/**
	 * Ordine DROP: dipendenze prima.
	 *
	 * @return string[]
	 */
	public static function table_suffixes() {
		return array(
			'llm_activity_kudos',
			'llm_user_bravo_given',
			'llm_user_coin_ledger',
			'llm_user_story_game_progress',
			'llm_user_story_completed',
			'llm_user_unlocked_story',
			'llm_user_phrase_done',
			'llm_user_coin_balance',
			'llm_story_media',
			'llm_story_phrases',
		);
	}

	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;

		$sql_phrases = "CREATE TABLE {$p}llm_story_phrases (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_id bigint(20) unsigned NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			phrase_interface longtext NOT NULL,
			phrase_target longtext NOT NULL,
			phrase_grammar longtext NOT NULL,
			phrase_alt longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY story_sort (story_id, sort_order)
		) $charset_collate;";

		$sql_media = "CREATE TABLE {$p}llm_story_media (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_id bigint(20) unsigned NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			after_phrase_index int(11) NOT NULL DEFAULT -1,
			PRIMARY KEY  (id),
			KEY story_sort (story_id, sort_order)
		) $charset_collate;";

		$sql_balance = "CREATE TABLE {$p}llm_user_coin_balance (
			user_id bigint(20) unsigned NOT NULL,
			balance int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (user_id)
		) $charset_collate;";

		$sql_phrase_done = "CREATE TABLE {$p}llm_user_phrase_done (
			user_id bigint(20) unsigned NOT NULL,
			story_id bigint(20) unsigned NOT NULL,
			phrase_index int(11) NOT NULL,
			PRIMARY KEY  (user_id, story_id, phrase_index),
			KEY story_id (story_id)
		) $charset_collate;";

		$sql_unlocked = "CREATE TABLE {$p}llm_user_unlocked_story (
			user_id bigint(20) unsigned NOT NULL,
			story_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (user_id, story_id),
			KEY story_id (story_id)
		) $charset_collate;";

		$sql_completed = "CREATE TABLE {$p}llm_user_story_completed (
			user_id bigint(20) unsigned NOT NULL,
			story_id bigint(20) unsigned NOT NULL,
			completed_at_gmt datetime NOT NULL,
			PRIMARY KEY  (user_id, story_id),
			KEY story_id (story_id)
		) $charset_collate;";

		$sql_game_progress = "CREATE TABLE {$p}llm_user_story_game_progress (
			user_id bigint(20) unsigned NOT NULL,
			story_id bigint(20) unsigned NOT NULL,
			phrase_index int(11) NOT NULL DEFAULT 0,
			step tinyint(4) unsigned NOT NULL DEFAULT 1,
			run_completions int(11) unsigned NOT NULL DEFAULT 0,
			updated_gmt datetime NOT NULL,
			PRIMARY KEY  (user_id, story_id),
			KEY story_id (story_id)
		) $charset_collate;";

		$sql_ledger = "CREATE TABLE {$p}llm_user_coin_ledger (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			entry_key varchar(128) NOT NULL DEFAULT '',
			entry_type varchar(64) NOT NULL DEFAULT '',
			amount int(11) NOT NULL DEFAULT 0,
			balance_after int(11) NOT NULL DEFAULT 0,
			story_id bigint(20) unsigned NOT NULL DEFAULT 0,
			phrase_index int(11) DEFAULT NULL,
			ts_gmt datetime NOT NULL,
			label text NOT NULL,
			PRIMARY KEY  (id),
			KEY user_ts (user_id, ts_gmt),
			KEY entry_key (entry_key(64))
		) $charset_collate;";

		$sql_kudos = "CREATE TABLE {$p}llm_activity_kudos (
			activity_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			created_gmt datetime NOT NULL,
			PRIMARY KEY  (activity_id, user_id),
			KEY user_id (user_id)
		) $charset_collate;";

		$sql_bravo = "CREATE TABLE {$p}llm_user_bravo_given (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			activity_id bigint(20) unsigned NOT NULL,
			ts_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_activity (user_id, activity_id),
			KEY user_ts (user_id, ts_gmt),
			KEY activity_id (activity_id)
		) $charset_collate;";

		dbDelta( $sql_phrases );
		dbDelta( $sql_media );
		dbDelta( $sql_balance );
		dbDelta( $sql_phrase_done );
		dbDelta( $sql_unlocked );
		dbDelta( $sql_completed );
		dbDelta( $sql_game_progress );
		dbDelta( $sql_ledger );
		dbDelta( $sql_kudos );
		dbDelta( $sql_bravo );

		update_option( self::OPT_VERSION, self::DB_VERSION );
	}

	public static function uninstall() {
		global $wpdb;

		foreach ( array_reverse( self::table_suffixes() ) as $suffix ) {
			$table = self::table( $suffix );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::OPT_VERSION );
	}
}
