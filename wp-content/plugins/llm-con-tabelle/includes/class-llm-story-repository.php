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
	 * @return array<int, array{interface:string,target:string,grammar:string,alt:string,notes:string,pronunciation:string}>
	 */
	public static function get_phrases( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, phrase_interface, phrase_target, phrase_grammar, phrase_alt, phrase_notes, phrase_notes_target, phrase_remember, phrase_pronunciation, phrase_ipa, phrase_approx, audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC", $story_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = self::map_phrase_row( $r );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $r Riga DB o array già mappato.
	 * @return array{interface:string,target:string,grammar:string,alt:string,notes:string,pronunciation:string}
	 */
	private static function map_phrase_row( $r ) {
		if ( ! is_array( $r ) ) {
			$r = array();
		}
		$male_id      = isset( $r['audio_male_id'] ) ? (int) $r['audio_male_id'] : 0;
		$female_id    = isset( $r['audio_female_id'] ) ? (int) $r['audio_female_id'] : 0;
		$az_male_id   = isset( $r['audio_azure_male_id'] ) ? (int) $r['audio_azure_male_id'] : 0;
		$az_female_id = isset( $r['audio_azure_female_id'] ) ? (int) $r['audio_azure_female_id'] : 0;
		return array(
			'id'                     => isset( $r['id'] ) ? (int) $r['id'] : 0,
			'interface'              => isset( $r['phrase_interface'] ) ? (string) $r['phrase_interface'] : ( isset( $r['interface'] ) ? (string) $r['interface'] : '' ),
			'target'                 => isset( $r['phrase_target'] ) ? (string) $r['phrase_target'] : ( isset( $r['target'] ) ? (string) $r['target'] : '' ),
			'grammar'                => isset( $r['phrase_grammar'] ) ? (string) $r['phrase_grammar'] : ( isset( $r['grammar'] ) ? (string) $r['grammar'] : '' ),
			'alt'                    => isset( $r['phrase_alt'] ) ? (string) $r['phrase_alt'] : ( isset( $r['alt'] ) ? (string) $r['alt'] : '' ),
			'notes'                  => isset( $r['phrase_notes'] ) ? (string) $r['phrase_notes'] : ( isset( $r['notes'] ) ? (string) $r['notes'] : '' ),
			'notes_target'           => isset( $r['phrase_notes_target'] ) ? (string) $r['phrase_notes_target'] : ( isset( $r['notes_target'] ) ? (string) $r['notes_target'] : '' ),
			'remember'               => isset( $r['phrase_remember'] ) ? (string) $r['phrase_remember'] : ( isset( $r['remember'] ) ? (string) $r['remember'] : '' ),
			'pronunciation'          => isset( $r['phrase_pronunciation'] ) ? (string) $r['phrase_pronunciation'] : ( isset( $r['pronunciation'] ) ? (string) $r['pronunciation'] : '' ),
			'ipa'                    => isset( $r['phrase_ipa'] ) ? (string) $r['phrase_ipa'] : ( isset( $r['ipa'] ) ? (string) $r['ipa'] : '' ),
			'approx'                 => isset( $r['phrase_approx'] ) ? (string) $r['phrase_approx'] : ( isset( $r['approx'] ) ? (string) $r['approx'] : '' ),
			'audio_male_id'          => $male_id,
			'audio_female_id'        => $female_id,
			'audio_male_url'         => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $male_id ) : '',
			'audio_female_url'       => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $female_id ) : '',
			'audio_azure_male_id'    => $az_male_id,
			'audio_azure_female_id'  => $az_female_id,
			'audio_azure_male_url'   => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $az_male_id ) : '',
			'audio_azure_female_url' => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $az_female_id ) : '',
		);
	}

	/**
	 * Prime N frasi: presenza appunti e media parole dell’analisi grammaticale.
	 *
	 * @param int $story_id ID post storia.
	 * @param int $limit    Quante frasi controllare (default 5).
	 * @return array{status:string,avg_words:int} status: yes|no|none.
	 */
	public static function analyze_first_phrases_notes( $story_id, $limit = 5 ) {
		global $wpdb;
		$story_id = absint( $story_id );
		$limit    = max( 1, absint( $limit ) );
		if ( ! $story_id ) {
			return array(
				'status'    => 'none',
				'avg_words' => 0,
			);
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT phrase_grammar FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC LIMIT %d",
				$story_id,
				$limit
			)
		);
		if ( ! is_array( $rows ) || array() === $rows ) {
			return array(
				'status'    => 'none',
				'avg_words' => 0,
			);
		}

		$has_all     = true;
		$word_counts = array();
		foreach ( $rows as $grammar ) {
			$n = self::count_note_words( $grammar );
			if ( 0 === $n ) {
				$has_all = false;
			}
			$word_counts[] = $n;
		}

		$avg = 0;
		if ( $has_all && ! empty( $word_counts ) ) {
			$avg = (int) round( array_sum( $word_counts ) / count( $word_counts ) );
		}

		return array(
			'status'    => $has_all ? 'yes' : 'no',
			'avg_words' => $avg,
		);
	}

	/**
	 * @param mixed $text Analisi grammaticale.
	 * @return int
	 */
	public static function count_note_words( $text ) {
		$plain = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$plain = preg_replace( '/\s+/u', ' ', $plain );
		$plain = is_string( $plain ) ? trim( $plain ) : '';
		if ( '' === $plain ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * Prime N frasi hanno tutte l’analisi grammaticale (appunti) non vuota.
	 *
	 * @param int $story_id ID post storia.
	 * @param int $limit    Quante frasi controllare (default 5).
	 * @return bool|null true = sì, false = no, null = nessuna frase.
	 */
	public static function first_phrases_have_grammar( $story_id, $limit = 5 ) {
		$stats = self::analyze_first_phrases_notes( $story_id, $limit );
		if ( 'none' === $stats['status'] ) {
			return null;
		}
		return 'yes' === $stats['status'];
	}

	/**
	 * Una sola frase per indice (stesso ordinamento di get_phrases).
	 *
	 * @param int $story_id ID storia.
	 * @param int $index    Indice 0-based.
	 * @return array{interface:string,target:string,grammar:string,alt:string,notes:string,pronunciation:string}|null
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
				"SELECT id, phrase_interface, phrase_target, phrase_grammar, phrase_alt, phrase_notes, phrase_notes_target, phrase_remember, phrase_pronunciation, phrase_ipa, phrase_approx, audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET %d",
				$story_id,
				$index
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return self::map_phrase_row( $row );
	}

	/**
	 * Aggiorna un campo rich-text di una frase (indice 0-based).
	 *
	 * @param int    $story_id ID storia.
	 * @param int    $index    Indice frase.
	 * @param string $field    notes|grammar|alt|pronunciation.
	 * @param string $value    HTML consentito.
	 * @return bool
	 */
	public static function update_phrase_rich_field( $story_id, $index, $field, $value ) {
		global $wpdb;
		$story_id = absint( $story_id );
		$index    = absint( $index );
		$map      = array(
			'notes'          => 'phrase_notes',
			'notes_target'   => 'phrase_notes_target',
			'remember'       => 'phrase_remember',
			'grammar'        => 'phrase_grammar',
			'alt'            => 'phrase_alt',
			'pronunciation'  => 'phrase_pronunciation',
			'ipa'            => 'phrase_ipa',
			'approx'         => 'phrase_approx',
		);
		if ( ! $story_id || ! isset( $map[ $field ] ) ) {
			return false;
		}
		$col   = $map[ $field ];
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET %d",
				$story_id,
				$index
			)
		);
		if ( ! $id ) {
			return false;
		}
		$result = $wpdb->update(
			$table,
			array(
				$col => self::sanitize_phrase_rich_text( $value ),
			),
			array(
				'id'       => (int) $id,
				'story_id' => $story_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);
		return false !== $result;
	}

	/**
	 * @param int    $story_id ID storia.
	 * @param int    $index    Indice frase.
	 * @param string $grammar  HTML consentito.
	 * @return bool
	 */
	public static function update_phrase_grammar( $story_id, $index, $grammar ) {
		return self::update_phrase_rich_field( $story_id, $index, 'grammar', $grammar );
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
	 * Chiave di confronto testo obiettivo (senza HTML).
	 *
	 * @param string $html Testo.
	 * @return string
	 */
	private static function phrase_target_key( $html ) {
		$t = wp_strip_all_tags( (string) $html );
		$t = preg_replace( '/\s+/u', ' ', $t );
		$t = is_string( $t ) ? $t : '';
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $t ) ) : strtolower( trim( $t ) );
	}

	/**
	 * @param int   $story_id ID post.
	 * @param array $phrases  Array di righe con chiavi interface, target, grammar, alt, notes, pronunciation (HTML consentito).
	 */
	public static function save_phrases( $story_id, array $phrases ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, phrase_target, audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id FROM {$table} WHERE story_id = %d",
				$story_id
			),
			ARRAY_A
		);
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$unclaimed = array();
		foreach ( $existing as $old ) {
			$unclaimed[ (int) $old['id'] ] = $old;
		}

		$order = 0;
		foreach ( $phrases as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$data = array(
				'story_id'             => $story_id,
				'sort_order'           => $order,
				'phrase_interface'     => isset( $row['interface'] ) ? self::sanitize_phrase_rich_text( $row['interface'] ) : '',
				'phrase_target'        => isset( $row['target'] ) ? self::sanitize_phrase_rich_text( $row['target'] ) : '',
				'phrase_grammar'       => isset( $row['grammar'] ) ? self::sanitize_phrase_rich_text( $row['grammar'] ) : '',
				'phrase_alt'           => isset( $row['alt'] ) ? self::sanitize_phrase_rich_text( $row['alt'] ) : '',
				'phrase_notes'         => isset( $row['notes'] ) ? self::sanitize_phrase_rich_text( $row['notes'] ) : '',
				'phrase_notes_target'  => isset( $row['notes_target'] ) ? self::sanitize_phrase_rich_text( $row['notes_target'] ) : '',
				'phrase_remember'      => isset( $row['remember'] ) ? self::sanitize_phrase_rich_text( $row['remember'] ) : '',
				'phrase_pronunciation' => isset( $row['pronunciation'] ) ? self::sanitize_phrase_rich_text( $row['pronunciation'] ) : '',
				'phrase_ipa'           => isset( $row['ipa'] ) ? self::sanitize_phrase_rich_text( $row['ipa'] ) : '',
				'phrase_approx'        => isset( $row['approx'] ) ? self::sanitize_phrase_rich_text( $row['approx'] ) : '',
			);
			$match_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( ! $match_id || ! isset( $unclaimed[ $match_id ] ) ) {
				$match_id = 0;
				$key      = self::phrase_target_key( $data['phrase_target'] );
				if ( '' !== $key ) {
					foreach ( $unclaimed as $cid => $old ) {
						if ( self::phrase_target_key( $old['phrase_target'] ) === $key ) {
							$match_id = (int) $cid;
							break;
						}
					}
				}
			}
			if ( $match_id && isset( $unclaimed[ $match_id ] ) ) {
				$wpdb->update(
					$table,
					$data,
					array(
						'id'       => $match_id,
						'story_id' => $story_id,
					),
					array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
					array( '%d', '%d' )
				);
				unset( $unclaimed[ $match_id ] );
			} else {
				$data['audio_male_id']         = 0;
				$data['audio_female_id']       = 0;
				$data['audio_azure_male_id']   = 0;
				$data['audio_azure_female_id'] = 0;
				$wpdb->insert(
					$table,
					$data,
					array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
				);
			}
			++$order;
		}

		foreach ( $unclaimed as $old ) {
			if ( class_exists( 'LLM_Phrase_TTS' ) ) {
				LLM_Phrase_TTS::delete_attachments(
					isset( $old['audio_male_id'] ) ? (int) $old['audio_male_id'] : 0,
					isset( $old['audio_female_id'] ) ? (int) $old['audio_female_id'] : 0,
					isset( $old['audio_azure_male_id'] ) ? (int) $old['audio_azure_male_id'] : 0,
					isset( $old['audio_azure_female_id'] ) ? (int) $old['audio_azure_female_id'] : 0
				);
			}
			$wpdb->delete(
				$table,
				array(
					'id'       => (int) $old['id'],
					'story_id' => $story_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * @param int    $story_id  ID storia.
	 * @param int    $phrase_id ID riga.
	 * @param string $gender    male|female.
	 * @return int
	 */
	public static function get_phrase_audio_id( $story_id, $phrase_id, $gender ) {
		global $wpdb;
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		$col       = 'female' === $gender ? 'audio_female_id' : 'audio_male_id';
		if ( ! $story_id || ! $phrase_id ) {
			return 0;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT {$col} FROM {$table} WHERE id = %d AND story_id = %d LIMIT 1",
				$phrase_id,
				$story_id
			)
		);
		return absint( $val );
	}

	/**
	 * @param int $story_id  ID storia.
	 * @param int $phrase_id ID riga.
	 * @param int $male_id   Allegato maschile.
	 * @param int $female_id Allegato femminile.
	 * @return bool
	 */
	public static function set_phrase_audio_ids( $story_id, $phrase_id, $male_id, $female_id ) {
		global $wpdb;
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		if ( ! $story_id || ! $phrase_id ) {
			return false;
		}
		$table  = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		$result = $wpdb->update(
			$table,
			array(
				'audio_male_id'   => absint( $male_id ),
				'audio_female_id' => absint( $female_id ),
			),
			array(
				'id'       => $phrase_id,
				'story_id' => $story_id,
			),
			array( '%d', '%d' ),
			array( '%d', '%d' )
		);
		return false !== $result;
	}

	/**
	 * @param int    $story_id  ID storia.
	 * @param int    $phrase_id ID riga.
	 * @param string $gender    male|female.
	 * @return int
	 */
	public static function get_phrase_azure_audio_id( $story_id, $phrase_id, $gender ) {
		global $wpdb;
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		$col       = 'female' === $gender ? 'audio_azure_female_id' : 'audio_azure_male_id';
		if ( ! $story_id || ! $phrase_id ) {
			return 0;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT {$col} FROM {$table} WHERE id = %d AND story_id = %d LIMIT 1",
				$phrase_id,
				$story_id
			)
		);
		return absint( $val );
	}

	/**
	 * Azure Neural: solo wp-admin, non usato nel gioco pubblico.
	 *
	 * @param int $story_id  ID storia.
	 * @param int $phrase_id ID riga.
	 * @param int $male_id   Allegato maschile Azure.
	 * @param int $female_id Allegato femminile Azure.
	 * @return bool
	 */
	public static function set_phrase_azure_audio_ids( $story_id, $phrase_id, $male_id, $female_id ) {
		global $wpdb;
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		if ( ! $story_id || ! $phrase_id ) {
			return false;
		}
		$table  = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		$result = $wpdb->update(
			$table,
			array(
				'audio_azure_male_id'   => absint( $male_id ),
				'audio_azure_female_id' => absint( $female_id ),
			),
			array(
				'id'       => $phrase_id,
				'story_id' => $story_id,
			),
			array( '%d', '%d' ),
			array( '%d', '%d' )
		);
		return false !== $result;
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
		$c = LLM_Tabelle_Database::table( 'llm_story_cast' );
		if ( class_exists( 'LLM_Phrase_TTS' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$audio_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id FROM {$p} WHERE story_id = %d",
					$story_id
				),
				ARRAY_A
			);
			if ( is_array( $audio_rows ) ) {
				foreach ( $audio_rows as $ar ) {
					LLM_Phrase_TTS::delete_attachments(
						isset( $ar['audio_male_id'] ) ? (int) $ar['audio_male_id'] : 0,
						isset( $ar['audio_female_id'] ) ? (int) $ar['audio_female_id'] : 0,
						isset( $ar['audio_azure_male_id'] ) ? (int) $ar['audio_azure_male_id'] : 0,
						isset( $ar['audio_azure_female_id'] ) ? (int) $ar['audio_azure_female_id'] : 0
					);
				}
			}
		}
		$wpdb->delete( $p, array( 'story_id' => $story_id ), array( '%d' ) );
		$wpdb->delete( $m, array( 'story_id' => $story_id ), array( '%d' ) );
		$wpdb->delete( $c, array( 'story_id' => $story_id ), array( '%d' ) );
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
				'id'            => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
				'interface' => isset( $row['interface'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['interface'] ) ) : '',
				'target'    => isset( $row['target'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['target'] ) ) : '',
				'grammar'   => isset( $row['grammar'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['grammar'] ) ) : '',
				'alt'       => isset( $row['alt'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['alt'] ) ) : '',
				'notes'          => isset( $row['notes'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['notes'] ) ) : '',
				'notes_target'   => isset( $row['notes_target'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['notes_target'] ) ) : '',
				'remember'       => isset( $row['remember'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['remember'] ) ) : '',
				'pronunciation'  => isset( $row['pronunciation'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['pronunciation'] ) ) : '',
				'ipa'            => isset( $row['ipa'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['ipa'] ) ) : '',
				'approx'         => isset( $row['approx'] ) ? self::sanitize_phrase_rich_text( wp_unslash( $row['approx'] ) ) : '',
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
