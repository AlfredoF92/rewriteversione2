<?php
/**
 * Checkpoint gioco frasi per utente/storia (DB).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Game_Progress {

	/** Dopo “Continua” sulla traduzione: mostra analisi + riscrittura. */
	const STEP_TRANSLATE = 1;

	/** Fase riscrittura della stessa frase. */
	const STEP_REWRITE = 2;

	/**
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 * @param int $total    Numero frasi nella storia.
	 * @return array{phrase_index:int, step:int, finished:bool}|null Null se ospite.
	 */
	public static function resolve_for_user( $user_id, $story_id, $total ) {
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		$total    = max( 0, (int) $total );
		if ( ! $user_id || ! $story_id ) {
			return null;
		}

		// Se esiste un checkpoint valido lo rispettiamo anche a storia già completata:
		// serve per il pulsante "Ricomincia storia" senza azzerare statistiche.
		$row = self::get_row( $user_id, $story_id );
		if ( $row ) {
			$pi = (int) $row['phrase_index'];
			$st = (int) $row['step'];
			if ( self::STEP_TRANSLATE !== $st && self::STEP_REWRITE !== $st ) {
				$st = self::STEP_TRANSLATE;
			}
			if ( $pi >= 0 && $pi < $total ) {
				return array(
					'phrase_index' => $pi,
					'step'         => $st,
					'finished'     => false,
				);
			}
		}

		$map = LLM_User_Stats::get_phrase_map( $user_id );
		$k   = (string) $story_id;
		$done_indices = isset( $map[ $k ] ) && is_array( $map[ $k ] ) ? array_map( 'intval', $map[ $k ] ) : array();
		sort( $done_indices );
		$done_set = array_flip( $done_indices );

		$first_incomplete = null;
		for ( $i = 0; $i < $total; $i++ ) {
			if ( ! isset( $done_set[ $i ] ) ) {
				$first_incomplete = $i;
				break;
			}
		}

		if ( null === $first_incomplete ) {
			self::delete( $user_id, $story_id );
			return array(
				'phrase_index' => $total,
				'step'         => self::STEP_TRANSLATE,
				'finished'     => true,
			);
		}

		return array(
			'phrase_index' => $first_incomplete,
			'step'         => self::STEP_TRANSLATE,
			'finished'     => false,
		);
	}

	/**
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 * @return array{phrase_index:int, step:int}|null
	 */
	public static function get_row( $user_id, $story_id ) {
		global $wpdb;
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		if ( ! $user_id || ! $story_id ) {
			return null;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT phrase_index, step FROM {$t} WHERE user_id = %d AND story_id = %d",
				$user_id,
				$story_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return array(
			'phrase_index' => (int) $row['phrase_index'],
			'step'         => (int) $row['step'],
		);
	}

	/**
	 * @param int $user_id      ID utente.
	 * @param int $story_id     ID storia.
	 * @param int $phrase_index Indice frase 0-based.
	 * @param int $step         STEP_TRANSLATE o STEP_REWRITE.
	 */
	public static function upsert( $user_id, $story_id, $phrase_index, $step ) {
		global $wpdb;
		$user_id      = absint( $user_id );
		$story_id     = absint( $story_id );
		$phrase_index = (int) $phrase_index;
		$step         = (int) $step;
		if ( ! $user_id || ! $story_id ) {
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		$wpdb->replace(
			$t,
			array(
				'user_id'       => $user_id,
				'story_id'      => $story_id,
				'phrase_index'  => $phrase_index,
				'step'          => $step,
				'updated_gmt'   => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 */
	public static function delete( $user_id, $story_id ) {
		global $wpdb;
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		if ( ! $user_id || ! $story_id ) {
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		$wpdb->delete( $t, array( 'user_id' => $user_id, 'story_id' => $story_id ), array( '%d', '%d' ) );
	}

	/**
	 * @param int $user_id ID utente.
	 */
	public static function delete_all_for_user( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		$wpdb->delete( $t, array( 'user_id' => $user_id ), array( '%d' ) );
	}
}
