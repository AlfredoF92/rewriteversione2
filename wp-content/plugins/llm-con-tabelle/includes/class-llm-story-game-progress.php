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
	 * @return array{phrase_index:int, step:int, finished:bool, display_phrase_index:int}|null Null se ospite.
	 */
	public static function resolve_for_user( $user_id, $story_id, $total ) {
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		$total    = max( 0, (int) $total );
		if ( ! $user_id || ! $story_id ) {
			return null;
		}

		$map          = LLM_User_Stats::get_phrase_map( $user_id );
		$k            = (string) $story_id;
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

		$row     = self::get_row( $user_id, $story_id );
		$display = self::display_from_row( $row, $total );
		if ( $row ) {
			$pi = (int) $row['phrase_index'];
			$st = (int) $row['step'];
			if ( self::STEP_TRANSLATE !== $st && self::STEP_REWRITE !== $st ) {
				$st = self::STEP_TRANSLATE;
			}
			if ( $pi >= 0 && $pi < $total ) {
				// Mappa piena ma checkpoint valido ⇒ “Ricomincia storia”: si gioca di nuovo da quel punto.
				if ( null === $first_incomplete ) {
					return self::pack_resolve( $pi, $st, false, $display );
				}
				if ( $pi === $first_incomplete ) {
					return self::pack_resolve( $pi, $st, false, $display );
				}
				self::upsert( $user_id, $story_id, $first_incomplete, self::STEP_TRANSLATE );
				return self::pack_resolve( $first_incomplete, self::STEP_TRANSLATE, false, $display );
			}
		}

		if ( null === $first_incomplete ) {
			if ( $display < 0 ) {
				self::delete( $user_id, $story_id );
			}
			return self::pack_resolve( $total, self::STEP_TRANSLATE, true, $display );
		}

		return self::pack_resolve( $first_incomplete, self::STEP_TRANSLATE, false, $display );
	}

	/**
	 * @param int  $phrase_index         Checkpoint.
	 * @param int  $step                 STEP_TRANSLATE o STEP_REWRITE.
	 * @param bool $finished             Storia finita.
	 * @param int  $display_phrase_index Salto temporaneo, o -1.
	 * @return array{phrase_index:int, step:int, finished:bool, display_phrase_index:int}
	 */
	private static function pack_resolve( $phrase_index, $step, $finished, $display_phrase_index ) {
		return array(
			'phrase_index'          => (int) $phrase_index,
			'step'                  => (int) $step,
			'finished'              => (bool) $finished,
			'display_phrase_index'  => (int) $display_phrase_index,
		);
	}

	/**
	 * @param array<string,mixed>|null $row   Riga progresso.
	 * @param int                      $total Numero frasi.
	 * @return int
	 */
	public static function display_from_row( $row, $total = 0 ) {
		if ( ! is_array( $row ) || ! isset( $row['display_phrase_index'] ) ) {
			return -1;
		}
		$d = (int) $row['display_phrase_index'];
		if ( $d < 0 ) {
			return -1;
		}
		if ( $total > 0 && $d >= $total ) {
			return -1;
		}
		return $d;
	}

	/**
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 * @return array{phrase_index:int, step:int, run_completions:int, display_phrase_index:int}|null
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
				"SELECT phrase_index, step, run_completions, display_phrase_index FROM {$t} WHERE user_id = %d AND story_id = %d",
				$user_id,
				$story_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return array(
			'phrase_index'          => (int) $row['phrase_index'],
			'step'                  => (int) $row['step'],
			'run_completions'       => isset( $row['run_completions'] ) ? (int) $row['run_completions'] : 0,
			'display_phrase_index'  => isset( $row['display_phrase_index'] ) ? (int) $row['display_phrase_index'] : -1,
		);
	}

	/**
	 * @param int      $user_id               ID utente.
	 * @param int      $story_id              ID storia.
	 * @param int      $phrase_index          Indice frase 0-based.
	 * @param int      $step                  STEP_TRANSLATE o STEP_REWRITE.
	 * @param int|null $run_completions       Conteggio frasi chiuse in questa «corsa»; null = mantieni valore attuale.
	 * @param int|null $display_phrase_index  Salto temporaneo; null = mantieni valore attuale.
	 */
	public static function upsert( $user_id, $story_id, $phrase_index, $step, $run_completions = null, $display_phrase_index = null ) {
		global $wpdb;
		$user_id      = absint( $user_id );
		$story_id     = absint( $story_id );
		$phrase_index = (int) $phrase_index;
		$step         = (int) $step;
		if ( ! $user_id || ! $story_id ) {
			return;
		}
		$row = self::get_row( $user_id, $story_id );
		if ( null === $run_completions ) {
			$run_completions = is_array( $row ) && isset( $row['run_completions'] ) ? (int) $row['run_completions'] : 0;
		}
		if ( null === $display_phrase_index ) {
			$display_phrase_index = is_array( $row ) && isset( $row['display_phrase_index'] ) ? (int) $row['display_phrase_index'] : -1;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		$wpdb->replace(
			$t,
			array(
				'user_id'              => $user_id,
				'story_id'             => $story_id,
				'phrase_index'         => $phrase_index,
				'display_phrase_index' => (int) $display_phrase_index,
				'step'                 => $step,
				'run_completions'      => (int) $run_completions,
				'updated_gmt'          => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Imposta o azzera il salto di visualizzazione senza toccare il checkpoint.
	 *
	 * @param int $user_id               ID utente.
	 * @param int $story_id              ID storia.
	 * @param int $display_ix            Indice da mostrare, o -1 per azzerare.
	 * @param int $fallback_phrase_index Checkpoint se la riga non esiste.
	 */
	public static function set_display_phrase_index( $user_id, $story_id, $display_ix, $fallback_phrase_index = 0 ) {
		global $wpdb;
		$user_id               = absint( $user_id );
		$story_id              = absint( $story_id );
		$display_ix            = (int) $display_ix;
		$fallback_phrase_index = (int) $fallback_phrase_index;
		if ( ! $user_id || ! $story_id ) {
			return;
		}
		if ( $display_ix < -1 ) {
			$display_ix = -1;
		}
		$row = self::get_row( $user_id, $story_id );
		if ( ! $row ) {
			self::upsert( $user_id, $story_id, $fallback_phrase_index, self::STEP_TRANSLATE, 0, $display_ix );
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		$wpdb->update(
			$t,
			array(
				'display_phrase_index' => $display_ix,
				'updated_gmt'          => current_time( 'mysql', true ),
			),
			array(
				'user_id'  => $user_id,
				'story_id' => $story_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * +1 al contatore «frasi completate in questa corsa» (barra coerente anche ripetendo la stessa frase).
	 *
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 */
	public static function increment_run_completions( $user_id, $story_id ) {
		global $wpdb;
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		if ( ! $user_id || ! $story_id ) {
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		if ( ! self::get_row( $user_id, $story_id ) ) {
			self::upsert( $user_id, $story_id, 0, self::STEP_TRANSLATE, 0 );
		}
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET run_completions = run_completions + 1, updated_gmt = %s WHERE user_id = %d AND story_id = %d",
				$now,
				$user_id,
				$story_id
			)
		);
	}

	/**
	 * Numeratore barra (0…total): `run_completions` se esiste il checkpoint; se la riga è stata cancellata
	 * dopo l’ultima frase ma la mappa phrase_done contiene tutte le frasi ⇒ storia completata ⇒ total.
	 *
	 * @param int $user_id  ID utente.
	 * @param int $story_id ID storia.
	 * @param int $total    Numero frasi nella storia.
	 * @return int
	 */
	public static function bar_completed_count( $user_id, $story_id, $total ) {
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		$total    = max( 0, (int) $total );
		if ( ! $user_id || ! $story_id || $total < 1 ) {
			return 0;
		}

		// Conta sequenziale da phrase_done: quante frasi consecutive partendo da 0 sono completate.
		$map      = LLM_User_Stats::get_phrase_map( $user_id );
		$key      = (string) $story_id;
		$done_set = array();
		if ( isset( $map[ $key ] ) && is_array( $map[ $key ] ) ) {
			$done_set = array_flip( array_map( 'intval', $map[ $key ] ) );
		}
		$sequential_done = 0;
		for ( $i = 0; $i < $total; $i++ ) {
			if ( isset( $done_set[ $i ] ) ) {
				$sequential_done++;
			} else {
				break;
			}
		}

		$row = self::get_row( $user_id, $story_id );
		$run = ( is_array( $row ) && isset( $row['run_completions'] ) ) ? (int) $row['run_completions'] : 0;

		// La barra non scende mai sotto le frasi sequenzialmente completate in phrase_done.
		return min( max( $run, $sequential_done ), $total );
	}

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
