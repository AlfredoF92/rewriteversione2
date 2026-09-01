<?php
/**
 * Checkpoint gioco frasi per ospiti (cookie anonimo + DB).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Guest_Story_Progress {

	const COOKIE_NAME = 'llm_guest_id';

	/** Durata cookie in secondi (1 anno). */
	const COOKIE_TTL = 31536000;

	/**
	 * Restituisce (e crea se manca) l'ID ospite dal cookie.
	 *
	 * @return string UUID o stringa vuota se cookie non inviabile.
	 */
	public static function get_or_create_id() {
		$existing = self::read_cookie();
		if ( '' !== $existing ) {
			return $existing;
		}

		$id = self::generate_id();
		self::set_cookie( $id );
		return $id;
	}

	/**
	 * Solo lettura cookie (senza crearne uno nuovo).
	 *
	 * @return string
	 */
	public static function read_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}
		$id = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		return self::is_valid_id( $id ) ? $id : '';
	}

	/**
	 * @param string $id ID ospite.
	 * @return bool
	 */
	public static function is_valid_id( $id ) {
		$id = (string) $id;
		return (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $id );
	}

	/**
	 * @return string
	 */
	private static function generate_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	/**
	 * @param string $id ID ospite.
	 */
	private static function set_cookie( $id ) {
		if ( headers_sent() ) {
			return;
		}
		$secure   = is_ssl();
		$expires  = time() + self::COOKIE_TTL;
		$path     = COOKIEPATH ? COOKIEPATH : '/';
		$domain   = COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( self::COOKIE_NAME, $id, $expires, $path, $domain, $secure, true );
		$_COOKIE[ self::COOKIE_NAME ] = $id;
	}

	/**
	 * @param string $guest_id ID ospite.
	 * @param int    $story_id ID storia.
	 * @param int    $total    Numero frasi.
	 * @return array{phrase_index:int, step:int, finished:bool, display_phrase_index:int}|null
	 */
	public static function resolve( $guest_id, $story_id, $total ) {
		$guest_id = (string) $guest_id;
		$story_id = absint( $story_id );
		$total    = max( 0, (int) $total );
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id || $total < 1 ) {
			return null;
		}

		$row     = self::get_row( $guest_id, $story_id );
		$display = LLM_Story_Game_Progress::display_from_row( $row, $total );
		if ( ! $row ) {
			return array(
				'phrase_index'         => 0,
				'step'                 => LLM_Story_Game_Progress::STEP_TRANSLATE,
				'finished'             => false,
				'display_phrase_index' => -1,
			);
		}

		$pi = (int) $row['phrase_index'];
		$st = (int) $row['step'];
		if ( LLM_Story_Game_Progress::STEP_TRANSLATE !== $st && LLM_Story_Game_Progress::STEP_REWRITE !== $st ) {
			$st = LLM_Story_Game_Progress::STEP_TRANSLATE;
		}

		if ( $pi >= $total ) {
			return array(
				'phrase_index'         => $total,
				'step'                 => LLM_Story_Game_Progress::STEP_TRANSLATE,
				'finished'             => true,
				'display_phrase_index' => $display,
			);
		}

		if ( $pi < 0 ) {
			$pi = 0;
		}

		return array(
			'phrase_index'         => $pi,
			'step'                 => $st,
			'finished'             => false,
			'display_phrase_index' => $display,
		);
	}

	/**
	 * @param string $guest_id ID ospite.
	 * @param int    $story_id ID storia.
	 * @return array{phrase_index:int, step:int, run_completions:int, display_phrase_index:int}|null
	 */
	public static function get_row( $guest_id, $story_id ) {
		global $wpdb;
		$guest_id = (string) $guest_id;
		$story_id = absint( $story_id );
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id ) {
			return null;
		}
		$t = LLM_Tabelle_Database::table( 'llm_guest_story_game_progress' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT phrase_index, step, run_completions, display_phrase_index FROM {$t} WHERE guest_id = %s AND story_id = %d",
				$guest_id,
				$story_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return array(
			'phrase_index'         => (int) $row['phrase_index'],
			'step'                 => (int) $row['step'],
			'run_completions'      => isset( $row['run_completions'] ) ? (int) $row['run_completions'] : 0,
			'display_phrase_index' => isset( $row['display_phrase_index'] ) ? (int) $row['display_phrase_index'] : -1,
		);
	}

	/**
	 * @param string   $guest_id              ID ospite.
	 * @param int      $story_id              ID storia.
	 * @param int      $phrase_index          Indice frase 0-based (o = total se finita).
	 * @param int      $step                  STEP_TRANSLATE o STEP_REWRITE.
	 * @param int|null $run_completions       Null = mantieni valore attuale.
	 * @param int|null $display_phrase_index  Salto temporaneo; null = mantieni valore attuale.
	 */
	public static function upsert( $guest_id, $story_id, $phrase_index, $step, $run_completions = null, $display_phrase_index = null ) {
		global $wpdb;
		$guest_id     = (string) $guest_id;
		$story_id     = absint( $story_id );
		$phrase_index = (int) $phrase_index;
		$step         = (int) $step;
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id ) {
			return;
		}
		$row = self::get_row( $guest_id, $story_id );
		if ( null === $run_completions ) {
			$run_completions = is_array( $row ) && isset( $row['run_completions'] ) ? (int) $row['run_completions'] : 0;
		}
		if ( null === $display_phrase_index ) {
			$display_phrase_index = is_array( $row ) && isset( $row['display_phrase_index'] ) ? (int) $row['display_phrase_index'] : -1;
		}
		$t = LLM_Tabelle_Database::table( 'llm_guest_story_game_progress' );
		$wpdb->replace(
			$t,
			array(
				'guest_id'             => $guest_id,
				'story_id'             => $story_id,
				'phrase_index'         => $phrase_index,
				'display_phrase_index' => (int) $display_phrase_index,
				'step'                 => $step,
				'run_completions'      => (int) $run_completions,
				'updated_gmt'          => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Imposta o azzera il salto di visualizzazione senza toccare il checkpoint.
	 *
	 * @param string $guest_id              ID ospite.
	 * @param int    $story_id              ID storia.
	 * @param int    $display_ix            Indice da mostrare, o -1 per azzerare.
	 * @param int    $fallback_phrase_index Checkpoint se la riga non esiste.
	 */
	public static function set_display_phrase_index( $guest_id, $story_id, $display_ix, $fallback_phrase_index = 0 ) {
		global $wpdb;
		$guest_id              = (string) $guest_id;
		$story_id              = absint( $story_id );
		$display_ix            = (int) $display_ix;
		$fallback_phrase_index = (int) $fallback_phrase_index;
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id ) {
			return;
		}
		if ( $display_ix < -1 ) {
			$display_ix = -1;
		}
		$row = self::get_row( $guest_id, $story_id );
		if ( ! $row ) {
			self::upsert( $guest_id, $story_id, $fallback_phrase_index, LLM_Story_Game_Progress::STEP_TRANSLATE, 0, $display_ix );
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_guest_story_game_progress' );
		$wpdb->update(
			$t,
			array(
				'display_phrase_index' => $display_ix,
				'updated_gmt'          => current_time( 'mysql', true ),
			),
			array(
				'guest_id' => $guest_id,
				'story_id' => $story_id,
			),
			array( '%d', '%s' ),
			array( '%s', '%d' )
		);
	}

	/**
	 * @param string $guest_id ID ospite.
	 * @param int    $story_id ID storia.
	 */
	public static function increment_run_completions( $guest_id, $story_id ) {
		global $wpdb;
		$guest_id = (string) $guest_id;
		$story_id = absint( $story_id );
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id ) {
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_guest_story_game_progress' );
		if ( ! self::get_row( $guest_id, $story_id ) ) {
			self::upsert( $guest_id, $story_id, 0, LLM_Story_Game_Progress::STEP_TRANSLATE, 0 );
		}
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET run_completions = run_completions + 1, updated_gmt = %s WHERE guest_id = %s AND story_id = %d",
				$now,
				$guest_id,
				$story_id
			)
		);
	}

	/**
	 * Completa una frase ospite: +1 run_completions e avanza al prossimo checkpoint.
	 *
	 * @param string $guest_id     ID ospite.
	 * @param int    $story_id     ID storia.
	 * @param int    $phrase_index Indice appena completato.
	 * @param int    $total        Totale frasi.
	 * @return int phrases_done per la barra.
	 */
	public static function record_phrase_completion( $guest_id, $story_id, $phrase_index, $total ) {
		$guest_id     = (string) $guest_id;
		$story_id     = absint( $story_id );
		$phrase_index = (int) $phrase_index;
		$total        = max( 0, (int) $total );
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id ) {
			return 0;
		}

		self::increment_run_completions( $guest_id, $story_id );

		$next = $phrase_index + 1;
		if ( $next < $total ) {
			self::upsert( $guest_id, $story_id, $next, LLM_Story_Game_Progress::STEP_TRANSLATE );
		} else {
			/* Storia finita: phrase_index = total. */
			self::upsert( $guest_id, $story_id, $total, LLM_Story_Game_Progress::STEP_TRANSLATE );
		}

		return self::bar_completed_count( $guest_id, $story_id, $total );
	}

	/**
	 * @param string $guest_id ID ospite.
	 * @param int    $story_id ID storia.
	 * @param int    $total    Totale frasi.
	 * @return int
	 */
	public static function bar_completed_count( $guest_id, $story_id, $total ) {
		$guest_id = (string) $guest_id;
		$story_id = absint( $story_id );
		$total    = max( 0, (int) $total );
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id || $total < 1 ) {
			return 0;
		}

		$row = self::get_row( $guest_id, $story_id );
		if ( ! $row ) {
			return 0;
		}

		$run = isset( $row['run_completions'] ) ? (int) $row['run_completions'] : 0;
		$pi  = (int) $row['phrase_index'];
		/* phrase_index è la prossima da fare ⇒ le precedenti sono complete. */
		$from_index = min( max( 0, $pi ), $total );

		return min( max( $run, $from_index ), $total );
	}

	/**
	 * Reset progresso ospite per una storia (ricomincia da capo).
	 *
	 * @param string $guest_id ID ospite.
	 * @param int    $story_id ID storia.
	 */
	public static function reset_story( $guest_id, $story_id ) {
		global $wpdb;
		$guest_id = (string) $guest_id;
		$story_id = absint( $story_id );
		if ( ! self::is_valid_id( $guest_id ) || ! $story_id ) {
			return;
		}
		$t = LLM_Tabelle_Database::table( 'llm_guest_story_game_progress' );
		$wpdb->delete( $t, array( 'guest_id' => $guest_id, 'story_id' => $story_id ), array( '%s', '%d' ) );
	}
}
