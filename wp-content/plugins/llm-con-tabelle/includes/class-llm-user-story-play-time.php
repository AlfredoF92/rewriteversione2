<?php
/**
 * Tempo di gioco per utente loggato e storia (secondi in DB, minuti in UI).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_User_Story_Play_Time {

	const TABLE = 'llm_user_story_play_time';

	const AJAX = 'llm_story_play_time_tick';

	const NONCE = 'llm_story_play_time';

	/** Un battito non può aggiungere più di 40 secondi. */
	const MAX_TICK = 40;

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax_tick' ) );
	}

	/**
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 * @return int
	 */
	public static function get_seconds( $user_id, $story_id ) {
		global $wpdb;
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		if ( ! $user_id || ! $story_id ) {
			return 0;
		}
		$t = LLM_Tabelle_Database::table( self::TABLE );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT seconds FROM {$t} WHERE user_id = %d AND story_id = %d",
				$user_id,
				$story_id
			)
		);
		return max( 0, (int) $n );
	}

	/**
	 * @param int $user_id ID utente.
	 * @return int
	 */
	public static function sum_seconds( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		$t = LLM_Tabelle_Database::table( self::TABLE );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(seconds), 0) FROM {$t} WHERE user_id = %d",
				$user_id
			)
		);
		return max( 0, (int) $n );
	}

	/**
	 * @param int $seconds Secondi.
	 * @return int
	 */
	public static function seconds_to_minutes( $seconds ) {
		return (int) floor( max( 0, (int) $seconds ) / 60 );
	}

	/**
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 * @param int $delta    Secondi da aggiungere.
	 * @return int Totale secondi dopo l'aggiornamento.
	 */
	public static function add_seconds( $user_id, $story_id, $delta ) {
		global $wpdb;
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		$delta    = max( 0, min( self::MAX_TICK, (int) $delta ) );
		if ( ! $user_id || ! $story_id || ! $delta ) {
			return self::get_seconds( $user_id, $story_id );
		}
		$t   = LLM_Tabelle_Database::table( self::TABLE );
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$t} (user_id, story_id, seconds, updated_gmt)
				VALUES (%d, %d, %d, %s)
				ON DUPLICATE KEY UPDATE
					seconds = seconds + VALUES(seconds),
					updated_gmt = VALUES(updated_gmt)",
				$user_id,
				$story_id,
				$delta,
				$now
			)
		);
		return self::get_seconds( $user_id, $story_id );
	}

	public static function ajax_tick() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'guest' ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		$user_id  = get_current_user_id();
		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		$delta    = isset( $_POST['seconds'] ) ? absint( wp_unslash( $_POST['seconds'] ) ) : 0;
		if ( ! $story_id || get_post_type( $story_id ) !== ( defined( 'LLM_STORY_CPT' ) ? LLM_STORY_CPT : 'llm_story' ) ) {
			wp_send_json_error( array( 'message' => 'story' ), 400 );
		}
		$total = self::add_seconds( $user_id, $story_id, $delta );
		wp_send_json_success(
			array(
				'seconds' => $total,
				'minutes' => self::seconds_to_minutes( $total ),
			)
		);
	}
}
