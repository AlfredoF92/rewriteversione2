<?php
/**
 * Progresso cruciverba per utente loggato (griglia + completato).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_User_Crossword_Progress {

	const TABLE = 'llm_user_crossword_progress';

	/**
	 * @param int $user_id      ID utente.
	 * @param int $crossword_id ID CPT cruciverba.
	 * @return array{story_id:int,cells:array<int,string>,filled:int,total:int,solved:bool,solved_at_gmt:?string}|null
	 */
	public static function get( $user_id, $crossword_id ) {
		global $wpdb;
		$user_id      = absint( $user_id );
		$crossword_id = absint( $crossword_id );
		if ( ! $user_id || ! $crossword_id ) {
			return null;
		}
		$t = LLM_Tabelle_Database::table( self::TABLE );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT story_id, cells, filled, total, solved, solved_at_gmt FROM {$t} WHERE user_id = %d AND crossword_id = %d",
				$user_id,
				$crossword_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return array(
			'story_id'      => isset( $row['story_id'] ) ? absint( $row['story_id'] ) : 0,
			'cells'         => self::decode_cells( isset( $row['cells'] ) ? (string) $row['cells'] : '' ),
			'filled'        => isset( $row['filled'] ) ? (int) $row['filled'] : 0,
			'total'         => isset( $row['total'] ) ? (int) $row['total'] : 0,
			'solved'        => ! empty( $row['solved'] ),
			'solved_at_gmt' => ! empty( $row['solved_at_gmt'] ) ? (string) $row['solved_at_gmt'] : null,
		);
	}

	/**
	 * Salva o aggiorna. `solved=1` resta sticky: non torna a 0.
	 *
	 * @param int                  $user_id      ID utente.
	 * @param int                  $crossword_id ID CPT.
	 * @param int                  $story_id     Storia (0 se stand-alone).
	 * @param array<int,string>    $cells        Righe griglia (. vuoto, # nero, A–Z lettera).
	 * @param int                  $filled       Celle con lettera.
	 * @param int                  $total        Celle giocabili.
	 * @param bool                 $solved       Completato in questo salvataggio.
	 * @return bool
	 */
	public static function upsert( $user_id, $crossword_id, $story_id, array $cells, $filled, $total, $solved ) {
		global $wpdb;
		$user_id      = absint( $user_id );
		$crossword_id = absint( $crossword_id );
		$story_id     = absint( $story_id );
		$filled       = max( 0, (int) $filled );
		$total        = max( 0, (int) $total );
		$solved_now   = (bool) $solved;
		if ( ! $user_id || ! $crossword_id ) {
			return false;
		}

		$existing    = self::get( $user_id, $crossword_id );
		$was_solved  = $existing && ! empty( $existing['solved'] );
		$keep_solved = $was_solved || $solved_now;
		$now         = current_time( 'mysql', true );
		$cells_json  = wp_json_encode( array_values( $cells ) );
		$t           = LLM_Tabelle_Database::table( self::TABLE );

		if ( $keep_solved ) {
			$solved_at = $existing && ! empty( $existing['solved_at_gmt'] )
				? $existing['solved_at_gmt']
				: $now;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$t} (user_id, crossword_id, story_id, cells, filled, total, solved, solved_at_gmt, updated_gmt)
					VALUES (%d, %d, %d, %s, %d, %d, 1, %s, %s)
					ON DUPLICATE KEY UPDATE
						story_id = VALUES(story_id),
						cells = VALUES(cells),
						filled = VALUES(filled),
						total = VALUES(total),
						solved = 1,
						solved_at_gmt = IFNULL(solved_at_gmt, VALUES(solved_at_gmt)),
						updated_gmt = VALUES(updated_gmt)",
					$user_id,
					$crossword_id,
					$story_id,
					$cells_json,
					$filled,
					$total,
					$solved_at,
					$now
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$t} (user_id, crossword_id, story_id, cells, filled, total, solved, solved_at_gmt, updated_gmt)
					VALUES (%d, %d, %d, %s, %d, %d, 0, NULL, %s)
					ON DUPLICATE KEY UPDATE
						story_id = VALUES(story_id),
						cells = VALUES(cells),
						filled = VALUES(filled),
						total = VALUES(total),
						updated_gmt = VALUES(updated_gmt)",
					$user_id,
					$crossword_id,
					$story_id,
					$cells_json,
					$filled,
					$total,
					$now
				)
			);
		}
		return false !== $result;
	}

	/**
	 * Griglia vuota: se mai risolto tiene solved=1, altrimenti cancella la riga.
	 *
	 * @param int               $user_id      ID utente.
	 * @param int               $crossword_id ID CPT.
	 * @param int               $story_id     Storia.
	 * @param array<int,string> $cells        Griglia svuotata.
	 * @param int               $total        Celle giocabili.
	 * @return bool
	 */
	public static function clear_grid( $user_id, $crossword_id, $story_id, array $cells, $total ) {
		$user_id      = absint( $user_id );
		$crossword_id = absint( $crossword_id );
		if ( ! $user_id || ! $crossword_id ) {
			return false;
		}
		$existing = self::get( $user_id, $crossword_id );
		if ( ! $existing || empty( $existing['solved'] ) ) {
			return self::delete( $user_id, $crossword_id );
		}
		return self::upsert( $user_id, $crossword_id, $story_id, $cells, 0, $total, true );
	}

	/**
	 * @param int $user_id      ID utente.
	 * @param int $crossword_id ID CPT.
	 * @return bool
	 */
	public static function delete( $user_id, $crossword_id ) {
		global $wpdb;
		$user_id      = absint( $user_id );
		$crossword_id = absint( $crossword_id );
		if ( ! $user_id || ! $crossword_id ) {
			return false;
		}
		$t = LLM_Tabelle_Database::table( self::TABLE );
		$n = $wpdb->delete(
			$t,
			array(
				'user_id'      => $user_id,
				'crossword_id' => $crossword_id,
			),
			array( '%d', '%d' )
		);
		return false !== $n;
	}

	/**
	 * Cruciverba distinti con solved=1.
	 *
	 * @param int $user_id ID utente.
	 * @return int
	 */
	public static function count_solved( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		$t = LLM_Tabelle_Database::table( self::TABLE );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE user_id = %d AND solved = 1",
				$user_id
			)
		);
		return (int) $n;
	}

	/**
	 * @param string $json JSON array di stringhe.
	 * @return array<int,string>
	 */
	public static function decode_cells( $json ) {
		$raw = json_decode( (string) $json, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $line ) {
			$out[] = is_string( $line ) ? $line : (string) $line;
		}
		return $out;
	}

	/**
	 * Accetta solo A–Z, punto e cancelletto; max lato MAX_SIDE.
	 *
	 * @param mixed $raw JSON o array.
	 * @return array<int,string>
	 */
	public static function sanitize_cells( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$max = class_exists( 'LLM_Crossword' ) ? (int) LLM_Crossword::MAX_SIDE : 40;
		$out = array();
		$n   = 0;
		foreach ( $raw as $line ) {
			if ( $n >= $max ) {
				break;
			}
			$s = strtoupper( preg_replace( '/[^A-Z.#]/', '.', (string) $line ) );
			if ( strlen( $s ) > $max ) {
				$s = substr( $s, 0, $max );
			}
			$out[] = $s;
			++$n;
		}
		return $out;
	}
}
