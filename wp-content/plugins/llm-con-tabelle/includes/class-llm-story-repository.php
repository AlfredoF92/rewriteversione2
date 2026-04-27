<?php
/**
 * Lettura/scrittura frasi e media da tabelle (nessun JSON).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Repository {

	/**
	 * @param int $story_id ID post storia.
	 * @return array<int, array{interface:string,target:string,grammar:string,alt:string}>
	 */
	public static function get_phrases( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT phrase_interface, phrase_target, phrase_grammar, phrase_alt FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC", $story_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'interface' => isset( $r['phrase_interface'] ) ? (string) $r['phrase_interface'] : '',
				'target'    => isset( $r['phrase_target'] ) ? (string) $r['phrase_target'] : '',
				'grammar'   => isset( $r['phrase_grammar'] ) ? (string) $r['phrase_grammar'] : '',
				'alt'       => isset( $r['phrase_alt'] ) ? (string) $r['phrase_alt'] : '',
			);
		}
		return $out;
	}

	/**
	 * Una sola frase per indice (stesso ordinamento di get_phrases).
	 *
	 * @param int $story_id ID storia.
	 * @param int $index    Indice 0-based.
	 * @return array{interface:string,target:string,grammar:string,alt:string}|null
	 */
	public static function get_phrase_at( $story_id, $index ) {
		global $wpdb;
		$story_id = absint( $story_id );
		$index    = absint( $index );
		if ( ! $story_id ) {
			return null;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT phrase_interface, phrase_target, phrase_grammar, phrase_alt FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET %d",
				$story_id,
				$index
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return array(
			'interface' => isset( $row['phrase_interface'] ) ? (string) $row['phrase_interface'] : '',
			'target'    => isset( $row['phrase_target'] ) ? (string) $row['phrase_target'] : '',
			'grammar'   => isset( $row['phrase_grammar'] ) ? (string) $row['phrase_grammar'] : '',
			'alt'       => isset( $row['phrase_alt'] ) ? (string) $row['phrase_alt'] : '',
		);
	}

	/**
	 * Sanifica contenuto frase conservando HTML consentito (come i post: `wp_kses_post`).
	 *
	 * @param mixed $value Valore grezzo.
	 * @return string
	 */
	public static function sanitize_phrase_rich_text( $value ) {
		if ( null === $value || false === $value ) {
			return '';
		}
		$s = is_string( $value ) ? $value : (string) $value;
		$s = str_replace( "\0", '', $s );

		return wp_kses_post( $s );
	}

	/**
	 * @param int   $story_id ID post.
	 * @param array $phrases  Array di righe con chiavi interface, target, grammar, alt (HTML consentito).
	 */
	public static function save_phrases( $story_id, array $phrases ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		$order = 0;
		foreach ( $phrases as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'story_id'         => $story_id,
					'sort_order'       => $order,
					'phrase_interface' => isset( $row['interface'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['interface'] ) ) : '',
					'phrase_target'    => isset( $row['target'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['target'] ) ) : '',
					'phrase_grammar'   => isset( $row['grammar'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['grammar'] ) ) : '',
					'phrase_alt'       => isset( $row['alt'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['alt'] ) ) : '',
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
			++$order;
		}
	}

	/**
	 * @param int $story_id ID post.
	 * @return array<int, array{attachment_id:int, after_phrase_index:int}>
	 */
	public static function get_media_blocks( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_media' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, after_phrase_index FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC", $story_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'attachment_id'      => isset( $r['attachment_id'] ) ? (int) $r['attachment_id'] : 0,
				'after_phrase_index' => isset( $r['after_phrase_index'] ) ? (int) $r['after_phrase_index'] : -1,
			);
		}
		return $out;
	}

	/**
	 * @param int   $story_id ID post.
	 * @param array $blocks   Righe attachment_id, after_phrase_index.
	 */
	public static function save_media_blocks( $story_id, array $blocks ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_media' );
		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		$order = 0;
		foreach ( $blocks as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$aid   = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$after = isset( $row['after_phrase_index'] ) ? (int) $row['after_phrase_index'] : -1;
			$after = max( -1, $after );
			if ( ! $aid || ! wp_attachment_is_image( $aid ) ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'story_id'           => $story_id,
					'sort_order'         => $order,
					'attachment_id'      => $aid,
					'after_phrase_index' => $after,
				),
				array( '%d', '%d', '%d', '%d' )
			);
			++$order;
		}
	}

	/**
	 * Elimina righe tabelle collegate (eliminazione post).
	 *
	 * @param int $story_id ID post.
	 */
	public static function delete_for_story( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}
		$p = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		$m = LLM_Tabelle_Database::table( 'llm_story_media' );
		$wpdb->delete( $p, array( 'story_id' => $story_id ), array( '%d' ) );
		$wpdb->delete( $m, array( 'story_id' => $story_id ), array( '%d' ) );
	}

	/**
	 * @param mixed $raw POST llm_phrases.
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize_phrases_from_post( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'interface' => isset( $row['interface'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['interface'] ) ) : '',
				'target'    => isset( $row['target'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['target'] ) ) : '',
				'grammar'   => isset( $row['grammar'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['grammar'] ) ) : '',
				'alt'       => isset( $row['alt'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['alt'] ) ) : '',
			);
		}
		return $out;
	}

	/**
	 * @param mixed $raw POST llm_media_blocks.
	 * @return array<int, array{attachment_id:int, after_phrase_index:int}>
	 */
	public static function sanitize_media_from_post( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$aid   = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$after = isset( $row['after_phrase_index'] ) ? (int) $row['after_phrase_index'] : -1;
			if ( ! $aid || ! wp_attachment_is_image( $aid ) ) {
				continue;
			}
			$out[] = array(
				'attachment_id'      => $aid,
				'after_phrase_index' => max( -1, $after ),
			);
		}
		return $out;
	}
}
