<?php
/**
 * Frasi da ascoltare negli appunti (estrazione + audio).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Notes_Listen {

	/**
	 * @param int $phrase_id ID frase.
	 * @return array<int, array{id:int,text:string,audio_id:int,url:string}>
	 */
	public static function get_for_phrase( $phrase_id ) {
		global $wpdb;
		$phrase_id = absint( $phrase_id );
		if ( $phrase_id < 1 ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, listen_text, audio_id FROM {$table} WHERE phrase_id = %d ORDER BY sort_order ASC, id ASC",
				$phrase_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$aid = isset( $r['audio_id'] ) ? (int) $r['audio_id'] : 0;
			$out[] = array(
				'id'       => isset( $r['id'] ) ? (int) $r['id'] : 0,
				'text'     => isset( $r['listen_text'] ) ? (string) $r['listen_text'] : '',
				'audio_id' => $aid,
				'url'      => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $aid ) : '',
			);
		}
		return $out;
	}

	/**
	 * Audio degli appunti per tutte le frasi di una storia (solo voci con MP3).
	 *
	 * @param int $story_id ID storia.
	 * @return array<int, array<int, array{text:string,url:string}>>
	 */
	public static function get_map_for_story( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( $story_id < 1 ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT phrase_id, listen_text, audio_id FROM {$table} WHERE story_id = %d ORDER BY phrase_id ASC, sort_order ASC, id ASC",
				$story_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$pid  = isset( $r['phrase_id'] ) ? (int) $r['phrase_id'] : 0;
			$text = isset( $r['listen_text'] ) ? (string) $r['listen_text'] : '';
			$aid  = isset( $r['audio_id'] ) ? (int) $r['audio_id'] : 0;
			$url  = ( $aid > 0 && class_exists( 'LLM_Phrase_TTS' ) ) ? LLM_Phrase_TTS::url( $aid ) : '';
			if ( $pid < 1 || '' === $text || '' === $url ) {
				continue;
			}
			if ( ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = array();
			}
			$out[ $pid ][] = array(
				'text' => $text,
				'url'  => $url,
			);
		}
		return $out;
	}

	/**
	 * Sostituisce l’elenco di una frase (testi in ordine). Audio già noti si riusano.
	 *
	 * @param int      $story_id  ID storia.
	 * @param int      $phrase_id ID frase.
	 * @param string[] $texts     Testi.
	 */
	public static function replace_texts( $story_id, $phrase_id, array $texts ) {
		global $wpdb;
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		if ( $story_id < 1 || $phrase_id < 1 ) {
			return;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
		$old   = self::get_for_phrase( $phrase_id );
		$by    = array();
		foreach ( $old as $row ) {
			$key = self::norm_key( $row['text'] );
			if ( '' !== $key && empty( $by[ $key ] ) && $row['audio_id'] > 0 ) {
				$by[ $key ] = (int) $row['audio_id'];
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $table, array( 'phrase_id' => $phrase_id ), array( '%d' ) );
		$i = 0;
		foreach ( $texts as $text ) {
			$text = self::clean_text( $text );
			if ( '' === $text ) {
				continue;
			}
			$key = self::norm_key( $text );
			$aid = isset( $by[ $key ] ) ? (int) $by[ $key ] : 0;
			$wpdb->insert(
				$table,
				array(
					'story_id'    => $story_id,
					'phrase_id'   => $phrase_id,
					'sort_order'  => $i,
					'listen_text' => $text,
					'audio_id'    => $aid,
				),
				array( '%d', '%d', '%d', '%s', '%d' )
			);
			++$i;
		}
	}

	/**
	 * @param string $html HTML appunti.
	 * @return string[]
	 */
	public static function extract_from_html( $html ) {
		$html = (string) $html;
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array();
		}
		$out = array();
		if ( preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $blocks ) ) {
			foreach ( $blocks[1] as $inner ) {
				self::extract_from_block( $inner, $out );
			}
		} else {
			self::extract_from_block( $html, $out );
		}
		return $out;
	}

	/**
	 * @param string   $inner HTML interno.
	 * @param string[] $out   Accumulo.
	 */
	private static function extract_from_block( $inner, array &$out ) {
		$warn_pos = false;
		foreach ( array( '⚠️', '❌' ) as $mark ) {
			$p = function_exists( 'mb_strpos' ) ? mb_strpos( $inner, $mark ) : strpos( $inner, $mark );
			if ( false !== $p && ( false === $warn_pos || $p < $warn_pos ) ) {
				$warn_pos = $p;
			}
		}
		$safe = ( false === $warn_pos ) ? $inner : ( function_exists( 'mb_substr' ) ? mb_substr( $inner, 0, $warn_pos ) : substr( $inner, 0, $warn_pos ) );

		if ( preg_match( '/<strong>[\s\S]*?→\s*"([^"]+)"/u', $safe, $m ) ) {
			$right = self::clean_text( $m[1] );
			if ( self::word_count( $right ) >= 3 && self::looks_target( $right ) ) {
				$out[] = $right;
			}
		}

		$for_lines = preg_replace( '/<br\s*\/?>/i', "\n", $safe );
		$for_lines = html_entity_decode( wp_strip_all_tags( (string) $for_lines ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( preg_split( '/\n+/', (string) $for_lines ) as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( preg_match( '/^(I|You|He\/She\/It|We|They)\s+(.+?)\s*\(/u', $line, $cm ) ) {
				$form = self::clean_text( $cm[1] . ' ' . $cm[2] );
				if ( '' !== $form ) {
					$out[] = $form;
				}
			}
		}

		if ( preg_match_all( '/(?:💬\s*)?(?:Esempio|Ex\.|Es\.):\s*"([^"]+)"/u', $safe, $em ) ) {
			foreach ( $em[1] as $q ) {
				$q = self::clean_text( wp_strip_all_tags( $q ) );
				if ( self::word_count( $q ) >= 3 && self::looks_target( $q ) ) {
					$out[] = $q;
				}
			}
		}
	}

	/**
	 * Genera gli MP3 mancanti di una frase.
	 *
	 * @param int    $story_id  ID storia.
	 * @param int    $phrase_id ID frase.
	 * @param string $locale    Locale.
	 * @return array{ok:int,skip:int,err:int}
	 */
	public static function generate_missing_audio( $story_id, $phrase_id, $locale ) {
		global $wpdb;
		$stats = array(
			'ok'   => 0,
			'skip' => 0,
			'err'  => 0,
		);
		$rows  = self::get_for_phrase( $phrase_id );
		$table = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
		$cache = array();
		foreach ( $rows as $row ) {
			$key     = self::norm_key( $row['text'] );
			$spoken  = self::speak_text( $row['text'] );
			$has_url = $row['audio_id'] > 0 && LLM_Phrase_TTS::url( $row['audio_id'] );
			$speak_meta = $has_url ? (string) get_post_meta( $row['audio_id'], '_llm_notes_listen_speak', true ) : '';
			$slash_stale = ( false !== strpos( $row['text'], '/' ) ) && $speak_meta !== $spoken;
			if ( $has_url && ! $slash_stale ) {
				$cache[ $key ] = $row['audio_id'];
				++$stats['skip'];
				continue;
			}
			if ( isset( $cache[ $key ] ) ) {
				$wpdb->update( $table, array( 'audio_id' => $cache[ $key ] ), array( 'id' => $row['id'] ), array( '%d' ), array( '%d' ) );
				++$stats['skip'];
				continue;
			}
			$tag = 'nw-' . substr( md5( $key . '|' . $spoken ), 0, 10 );
			$aid = LLM_Phrase_TTS::azure_female_attachment( $story_id, $phrase_id, $spoken, $locale, $tag );
			if ( is_wp_error( $aid ) || (int) $aid < 1 ) {
				++$stats['err'];
				continue;
			}
			update_post_meta( (int) $aid, '_llm_notes_listen_speak', $spoken );
			$cache[ $key ] = (int) $aid;
			$wpdb->update( $table, array( 'audio_id' => (int) $aid ), array( 'id' => $row['id'] ), array( '%d' ), array( '%d' ) );
			++$stats['ok'];
		}
		return $stats;
	}

	/**
	 * @param string $text Testo.
	 * @return string
	 */
	public static function clean_text( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';
		$text = trim( $text, " \t\n\r\"“”«»" );
		return $text;
	}

	/**
	 * Testo da far pronunciare: niente slash (He/She/It → He She It).
	 *
	 * @param string $text Testo visibile.
	 * @return string
	 */
	public static function speak_text( $text ) {
		$t = self::clean_text( $text );
		$t = str_replace( '/', ' ', $t );
		$t = preg_replace( '/\s+/u', ' ', $t );
		return is_string( $t ) ? trim( $t ) : '';
	}

	/**
	 * @param string $text Testo.
	 * @return int
	 */
	public static function word_count( $text ) {
		$text = self::clean_text( $text );
		if ( '' === $text ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * @param string $text Testo.
	 * @return string
	 */
	private static function norm_key( $text ) {
		$t = self::clean_text( $text );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $t, 'UTF-8' ) : strtolower( $t );
	}

	/**
	 * @param string $text Testo.
	 * @return bool
	 */
	private static function looks_target( $text ) {
		if ( preg_match( '/[àèéìòù]/u', $text ) ) {
			return false;
		}
		if ( preg_match( '/\b(nella|viveva|borsa|pelle|durava|corallo|piccolo|tutto)\b/ui', $text ) ) {
			return false;
		}
		return true;
	}
}
