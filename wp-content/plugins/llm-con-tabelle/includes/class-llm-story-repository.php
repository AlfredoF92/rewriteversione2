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

	const GRAMMAR_SLOTS_MAX = 10;

	/**
	 * Nomi colonne slot 1..10.
	 *
	 * @return array<int,array{title:string,body:string}>
	 */
	public static function grammar_slot_columns() {
		$cols = array();
		for ( $i = 1; $i <= self::GRAMMAR_SLOTS_MAX; $i++ ) {
			$cols[ $i ] = array(
				'title' => 'grammar_title_' . $i,
				'body'  => 'grammar_body_' . $i,
			);
		}
		return $cols;
	}

	/**
	 * Lista colonne SQL per SELECT (slot).
	 *
	 * @return string
	 */
	private static function grammar_slot_select_sql() {
		$bits = array();
		foreach ( self::grammar_slot_columns() as $pair ) {
			$bits[] = $pair['title'];
			$bits[] = $pair['body'];
		}
		return implode( ', ', $bits );
	}

	/**
	 * @param array<string,mixed> $r Riga DB.
	 * @return array<int,array{title:string,body:string}>
	 */
	public static function extract_grammar_slots_from_row( $r ) {
		$slots = array();
		if ( ! is_array( $r ) ) {
			return $slots;
		}
		foreach ( self::grammar_slot_columns() as $i => $pair ) {
			$title = isset( $r[ $pair['title'] ] ) ? trim( (string) $r[ $pair['title'] ] ) : '';
			$body  = isset( $r[ $pair['body'] ] ) ? trim( (string) $r[ $pair['body'] ] ) : '';
			if ( '' === $title && '' === $body ) {
				continue;
			}
			$slots[] = array(
				'title' => html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'body'  => $body,
			);
		}
		return $slots;
	}

	/**
	 * Normalizza fino a 10 slot da input admin/array.
	 *
	 * @param mixed $raw Array di {title,body} o mappa 1..10.
	 * @return array<int,array{title:string,body:string}> indexed 1..10 (anche vuoti).
	 */
	public static function normalize_grammar_slots( $raw ) {
		$out = array();
		for ( $i = 1; $i <= self::GRAMMAR_SLOTS_MAX; $i++ ) {
			$out[ $i ] = array( 'title' => '', 'body' => '' );
		}
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		$sequential = array_values( $raw );
		$use_seq    = array_keys( $raw ) === range( 0, count( $raw ) - 1 );
		foreach ( $raw as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( $use_seq ) {
				$idx = (int) $key + 1;
			} else {
				$idx = is_numeric( $key ) ? (int) $key : 0;
			}
			if ( $idx < 1 || $idx > self::GRAMMAR_SLOTS_MAX ) {
				continue;
			}
			$title = isset( $row['title'] ) ? sanitize_text_field( html_entity_decode( (string) wp_unslash( $row['title'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';
			$body  = isset( $row['body'] ) ? self::sanitize_phrase_rich_text( (string) wp_unslash( $row['body'] ) ) : '';
			$out[ $idx ] = array(
				'title' => $title,
				'body'  => $body,
			);
		}
		unset( $sequential );
		return $out;
	}

	/**
	 * Costruisce HTML accordion-friendly dagli slot.
	 *
	 * @param array<int,array{title?:string,body?:string}> $slots Slot.
	 * @return string
	 */
	public static function grammar_slots_to_html( $slots ) {
		$html = '';
		if ( ! is_array( $slots ) ) {
			return '';
		}
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$title = isset( $slot['title'] ) ? trim( (string) $slot['title'] ) : '';
			$body  = isset( $slot['body'] ) ? trim( (string) $slot['body'] ) : '';
			$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' === $title && '' === $body ) {
				continue;
			}
			$html .= '<p><strong>' . esc_html( $title ) . '</strong>';
			if ( '' !== $body ) {
				$html .= '<br />' . "\n" . $body;
			}
			$html .= '</p>' . "\n";
		}
		return trim( $html );
	}

	/**
	 * Spezza HTML legacy in slot (per migrazione).
	 * Nuovo slot se:
	 * - <p> apre con <strong>…</strong> (coppia, coniugazione, note, …)
	 * - <p> apre con 📜 Curiosità etimologia / 📌 In breve / 📝 Note aggiuntive
	 * Inoltre, se dentro un body compaiono quei marcatori, vengono staccati in slot nuovi.
	 *
	 * @param string $html phrase_grammar.
	 * @return array<int,array{title:string,body:string}> 1..10
	 */
	public static function grammar_html_to_slots( $html ) {
		$out = array();
		for ( $i = 1; $i <= self::GRAMMAR_SLOTS_MAX; $i++ ) {
			$out[ $i ] = array( 'title' => '', 'body' => '' );
		}
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return $out;
		}

		$sections = array();
		if ( preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/isu', $html, $matches ) ) {
			$current = null;
			foreach ( $matches[1] as $inner ) {
				$inner = trim( (string) $inner );
				if ( '' === $inner ) {
					continue;
				}
				$opened = self::grammar_paragraph_as_new_section( $inner );
				if ( is_array( $opened ) ) {
					$sections[] = $opened;
					$current    = count( $sections ) - 1;
					continue;
				}
				if ( null !== $current ) {
					$extra = self::sanitize_phrase_rich_text( $inner );
					if ( '' !== trim( wp_strip_all_tags( $extra ) ) ) {
						$prev = $sections[ $current ]['body'];
						$sep  = ( '' === $prev ) ? '' : '<br />' . "\n";
						$sections[ $current ]['body'] = trim( $prev . $sep . $extra );
					}
				} else {
					$sections[] = array(
						'title' => '',
						'body'  => self::sanitize_phrase_rich_text( $inner ),
					);
					$current = count( $sections ) - 1;
				}
			}
		} else {
			$sections[] = array(
				'title' => '',
				'body'  => self::sanitize_phrase_rich_text( $html ),
			);
		}

		$sections = self::peel_inline_grammar_markers( $sections );

		$slot = 1;
		foreach ( $sections as $sec ) {
			if ( $slot > self::GRAMMAR_SLOTS_MAX ) {
				break;
			}
			$title = isset( $sec['title'] ) ? trim( (string) $sec['title'] ) : '';
			$body  = isset( $sec['body'] ) ? trim( (string) $sec['body'] ) : '';
			if ( '' === $title && '' === trim( wp_strip_all_tags( $body ) ) ) {
				continue;
			}
			$out[ $slot ] = array(
				'title' => $title,
				'body'  => $body,
			);
			++$slot;
		}
		return $out;
	}

	/**
	 * Se il paragrafo è un titolo di sezione, restituisce {title,body}; altrimenti null.
	 *
	 * @param string $inner HTML interno al <p>.
	 * @return array{title:string,body:string}|null
	 */
	private static function grammar_paragraph_as_new_section( $inner ) {
		$inner = trim( (string) $inner );
		if ( '' === $inner ) {
			return null;
		}

		// 1) <strong>…</strong> in testa (anche dopo emoji).
		if ( preg_match( '/^(?:[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\s]*)*<strong\b[^>]*>(.*?)<\/strong>(.*)$/isu', $inner, $m ) ) {
			$title = sanitize_text_field( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$body  = preg_replace( '/^(?:\s|<br\s*\/?>)*/iu', '', $m[2] );
			return array(
				'title' => $title,
				'body'  => self::sanitize_phrase_rich_text( trim( (string) $body ) ),
			);
		}

		$plain = trim( wp_strip_all_tags( $inner ) );

		// 2) Marcatori tipici senza <strong>.
		$marker_titles = array(
			'/^(?:📜\s*)?Curiosit[aà]\s*etimolog[^:]*(?::)?\s*/iu' => '📜 Curiosità etimologia',
			'/^(?:📌\s*)?In\s+breve\s*:?\s*/iu'                   => '📌 In breve',
			'/^(?:📝\s*)?Note\s+aggiuntive[^:]*(?::)?\s*/iu'      => '📝 Note aggiuntive su questo verso',
			'/^Ricorda\s*:?\s*/iu'                                 => 'Ricorda',
		);
		foreach ( $marker_titles as $re => $title ) {
			if ( ! preg_match( $re, $plain ) ) {
				continue;
			}
			$body = $inner;
			// Togli il prefisso titolo dal HTML lasciando il resto.
			$body = preg_replace(
				'/^(?:[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\s]*)?(?:Curiosit[aà]\s*etimolog[^:<]*:?|In\s+breve\s*:?|Note\s+aggiuntive[^:<]*:?|Ricorda\s*:?)\s*/iu',
				'',
				$body,
				1
			);
			$body = preg_replace( '/^(?:\s|<br\s*\/?>)*/iu', '', (string) $body );
			return array(
				'title' => $title,
				'body'  => self::sanitize_phrase_rich_text( trim( (string) $body ) ),
			);
		}

		return null;
	}

	/**
	 * Stacca da ogni body i pezzi che iniziano con marcatori di nuova sezione.
	 *
	 * @param array<int,array{title:string,body:string}> $sections Sezioni.
	 * @return array<int,array{title:string,body:string}>
	 */
	private static function peel_inline_grammar_markers( array $sections ) {
		$out = array();
		foreach ( $sections as $sec ) {
			$title = isset( $sec['title'] ) ? (string) $sec['title'] : '';
			$body  = isset( $sec['body'] ) ? (string) $sec['body'] : '';
			$parts = self::split_body_on_grammar_markers( $body );
			if ( empty( $parts ) ) {
				$out[] = array(
					'title' => $title,
					'body'  => $body,
				);
				continue;
			}
			$first = array_shift( $parts );
			$out[] = array(
				'title' => $title,
				'body'  => self::sanitize_phrase_rich_text( $first ),
			);
			foreach ( $parts as $chunk ) {
				$opened = self::grammar_paragraph_as_new_section( $chunk );
				if ( is_array( $opened ) ) {
					$out[] = $opened;
				} else {
					$out[] = array(
						'title' => '',
						'body'  => self::sanitize_phrase_rich_text( $chunk ),
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Spezza un body su 📜 Curiosità / 📌 In breve / 📝 Note / Ricorda:
	 *
	 * @param string $body HTML body.
	 * @return string[] Pezzi (il primo resta al titolo corrente).
	 */
	private static function split_body_on_grammar_markers( $body ) {
		$body = trim( (string) $body );
		if ( '' === $body ) {
			return array();
		}
		$parts = preg_split(
			'/(?:<br\s*\/?>|\n|\r)+\s*(?=(?:📜\s*)?Curiosit[aà]\s*etimolog|(?:📌\s*)?In\s+breve|(?:📝\s*)?Note\s+aggiuntive|\bRicorda\s*:)/iu',
			$body
		);
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			// Anche senza <br>: marker attaccato in coda (caso screenshot).
			if ( preg_match( '/^(.*?)((?:📜\s*)?Curiosit[aà]\s*etimolog.*)$/isu', $body, $m )
				&& '' !== trim( wp_strip_all_tags( $m[1] ) )
				&& strlen( $m[1] ) > 20
			) {
				return array( trim( $m[1] ), trim( $m[2] ) );
			}
			return array( $body );
		}
		$clean = array();
		foreach ( $parts as $p ) {
			$p = trim( (string) $p );
			if ( '' !== $p ) {
				$clean[] = $p;
			}
		}
		return $clean;
	}

	/**
	 * Scrive gli slot su una frase (per indice) e sincronizza phrase_grammar.
	 *
	 * @param int   $story_id ID storia.
	 * @param int   $index    Indice 0-based.
	 * @param array $slots    Slot 1..10 o lista.
	 * @return bool
	 */
	public static function update_phrase_grammar_slots( $story_id, $index, $slots ) {
		global $wpdb;
		$story_id = absint( $story_id );
		$index    = absint( $index );
		if ( ! $story_id ) {
			return false;
		}
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
		$norm = self::normalize_grammar_slots( $slots );
		$data = array(
			'phrase_grammar' => self::sanitize_phrase_rich_text( self::grammar_slots_to_html( $norm ) ),
		);
		$format = array( '%s' );
		foreach ( self::grammar_slot_columns() as $i => $pair ) {
			$data[ $pair['title'] ] = $norm[ $i ]['title'];
			$data[ $pair['body'] ]  = $norm[ $i ]['body'];
			$format[]               = '%s';
			$format[]               = '%s';
		}
		$result = $wpdb->update(
			$table,
			$data,
			array(
				'id'       => (int) $id,
				'story_id' => $story_id,
			),
			$format,
			array( '%d', '%d' )
		);
		if ( false !== $result ) {
			/**
			 * Contenti frase aggiornati (slot grammar).
			 *
			 * @param int $story_id ID storia.
			 */
			do_action( 'llm_story_content_changed', $story_id );
		}
		return false !== $result;
	}

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
		$slot_sql = self::grammar_slot_select_sql();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, phrase_interface, phrase_target, phrase_grammar, phrase_alt, phrase_notes, phrase_notes_target, phrase_remember, phrase_pronunciation, phrase_ipa, phrase_approx, {$slot_sql}, audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id, audio_notes_female_id FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC", $story_id ), ARRAY_A );
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
		$notes_f_id   = isset( $r['audio_notes_female_id'] ) ? (int) $r['audio_notes_female_id'] : 0;
		$slots        = self::extract_grammar_slots_from_row( $r );
		$grammar_html = isset( $r['phrase_grammar'] ) ? (string) $r['phrase_grammar'] : ( isset( $r['grammar'] ) ? (string) $r['grammar'] : '' );
		if ( ! empty( $slots ) ) {
			$grammar_html = self::grammar_slots_to_html( $slots );
		}
		// Slot indexed 1..10 for admin forms.
		$slots_form = array();
		for ( $i = 1; $i <= self::GRAMMAR_SLOTS_MAX; $i++ ) {
			$slots_form[ $i ] = array( 'title' => '', 'body' => '' );
		}
		$si = 1;
		foreach ( $slots as $slot ) {
			if ( $si > self::GRAMMAR_SLOTS_MAX ) {
				break;
			}
			$slots_form[ $si ] = $slot;
			++$si;
		}
		return array(
			'id'                     => isset( $r['id'] ) ? (int) $r['id'] : 0,
			'interface'              => isset( $r['phrase_interface'] ) ? (string) $r['phrase_interface'] : ( isset( $r['interface'] ) ? (string) $r['interface'] : '' ),
			'target'                 => isset( $r['phrase_target'] ) ? (string) $r['phrase_target'] : ( isset( $r['target'] ) ? (string) $r['target'] : '' ),
			'grammar'                => $grammar_html,
			'grammar_slots'          => $slots_form,
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
			'audio_notes_female_id'  => $notes_f_id,
			'audio_notes_female_url' => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $notes_f_id ) : '',
		);
	}

	/**
	 * Conteggi di studio per l’elenco in pagina: slot degli appunti, audio e minuti.
	 *
	 * minutes_min/max restano 40 e 60 secondi a slot. Il tempo in pagina usa 45 secondi.
	 *
	 * @param int    $story_id    ID storia.
	 * @param string $target_lang Lingua obiettivo, per le frasi audio degli appunti.
	 * @return array{phrases:int,slots:int,notes_audio:int,total_audio:int,minutes_min:int,minutes_max:int}
	 */
	public static function study_overview( $story_id, $target_lang = 'en' ) {
		$phrases  = self::get_phrases( $story_id );
		$phrase_n = count( $phrases );
		$slots    = 0;

		$phrase_audio = 0;
		foreach ( $phrases as $phrase ) {
			$grammar = isset( $phrase['grammar'] ) ? (string) $phrase['grammar'] : '';
			$slots  += self::count_note_slots( $grammar );
			if ( self::phrase_has_generated_audio( $phrase ) ) {
				++$phrase_audio;
			}
		}
		$notes_audio = self::count_generated_note_audios( $story_id );

		return array(
			'phrases'      => $phrase_n,
			'slots'        => $slots,
			'phrase_audio' => $phrase_audio,
			'notes_audio'  => $notes_audio,
			'total_audio'  => $notes_audio + $phrase_audio,
			'minutes_min'  => (int) round( $slots * 40 / 60 ),
			'minutes_max'  => $slots,
		);
	}

	/**
	 * True se la frase ha già un MP3 della traduzione (una voce basta).
	 *
	 * @param array<string,mixed> $phrase Frase mappata.
	 * @return bool
	 */
	private static function phrase_has_generated_audio( array $phrase ) {
		foreach ( array( 'audio_female_id', 'audio_male_id', 'audio_azure_female_id', 'audio_azure_male_id' ) as $key ) {
			if ( ! empty( $phrase[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clip degli appunti che hanno già un file audio. Una clip, anche con due voci, conta 1.
	 *
	 * @param int $story_id ID storia.
	 * @return int
	 */
	private static function count_generated_note_audios( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return 0;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE story_id = %d AND (audio_id > 0 OR audio_male_id > 0)",
				$story_id
			)
		);
	}

	/**
	 * Paragrafi degli appunti: un <p> è uno slot. Senza paragrafi, un blocco di testo conta 1.
	 *
	 * @param string $grammar HTML analisi grammaticale.
	 * @return int
	 */
	private static function count_note_slots( $grammar ) {
		$grammar = (string) $grammar;
		if ( preg_match_all( '/<p\b/i', $grammar, $matches ) ) {
			return count( $matches[0] );
		}
		$plain = trim( wp_strip_all_tags( $grammar ) );
		return '' === $plain ? 0 : 1;
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
		$slot_sql = self::grammar_slot_select_sql();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, phrase_interface, phrase_target, phrase_grammar, phrase_alt, phrase_notes, phrase_notes_target, phrase_remember, phrase_pronunciation, phrase_ipa, phrase_approx, {$slot_sql}, audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id, audio_notes_female_id FROM {$table} WHERE story_id = %d ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET %d",
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
		$clean = self::sanitize_phrase_rich_text( $value );
		// grammar: tieni allineati anche i 10 slot title/body (il frontend legge da lì).
		if ( 'grammar' === $field ) {
			$slots = self::grammar_html_to_slots( $clean );
			$ok    = self::update_phrase_grammar_slots( $story_id, $index, $slots );
			return $ok;
		}
		$result = $wpdb->update(
			$table,
			array(
				$col => $clean,
			),
			array(
				'id'       => (int) $id,
				'story_id' => $story_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);
		if ( false !== $result ) {
			do_action( 'llm_story_content_changed', $story_id );
		}
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
				"SELECT id, phrase_target, audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id, audio_notes_female_id FROM {$table} WHERE story_id = %d",
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
			$slots = self::normalize_grammar_slots( isset( $row['grammar_slots'] ) ? $row['grammar_slots'] : array() );
			$has_slot = false;
			foreach ( $slots as $slot ) {
				if ( '' !== $slot['title'] || '' !== trim( wp_strip_all_tags( $slot['body'] ) ) ) {
					$has_slot = true;
					break;
				}
			}
			foreach ( self::grammar_slot_columns() as $i => $pair ) {
				$data[ $pair['title'] ] = $slots[ $i ]['title'];
				$data[ $pair['body'] ]  = $slots[ $i ]['body'];
			}
			if ( $has_slot ) {
				$data['phrase_grammar'] = self::sanitize_phrase_rich_text( self::grammar_slots_to_html( $slots ) );
			}
			$format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
			for ( $i = 1; $i <= self::GRAMMAR_SLOTS_MAX; $i++ ) {
				$format[] = '%s';
				$format[] = '%s';
			}
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
					$format,
					array( '%d', '%d' )
				);
				unset( $unclaimed[ $match_id ] );
			} else {
				$data['audio_male_id']         = 0;
				$data['audio_female_id']       = 0;
				$data['audio_azure_male_id']   = 0;
				$data['audio_azure_female_id'] = 0;
				$data['audio_notes_female_id'] = 0;
				$insert_format = array_merge( $format, array( '%d', '%d', '%d', '%d', '%d' ) );
				$wpdb->insert(
					$table,
					$data,
					$insert_format
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
					isset( $old['audio_azure_female_id'] ) ? (int) $old['audio_azure_female_id'] : 0,
					isset( $old['audio_notes_female_id'] ) ? (int) $old['audio_notes_female_id'] : 0
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
		do_action( 'llm_story_content_changed', $story_id );
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
		if ( false !== $result ) {
			do_action( 'llm_story_content_changed', $story_id );
		}
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
		if ( false !== $result ) {
			do_action( 'llm_story_content_changed', $story_id );
		}
		return false !== $result;
	}

	/**
	 * @param int $story_id  ID storia.
	 * @param int $phrase_id ID riga.
	 * @return int
	 */
	public static function get_phrase_notes_audio_id( $story_id, $phrase_id ) {
		global $wpdb;
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		if ( ! $story_id || ! $phrase_id ) {
			return 0;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT audio_notes_female_id FROM {$table} WHERE id = %d AND story_id = %d LIMIT 1",
				$phrase_id,
				$story_id
			)
		);
		return absint( $val );
	}

	/**
	 * Audio Azure femminile: frase obiettivo + note nella lingua da imparare.
	 *
	 * @param int $story_id  ID storia.
	 * @param int $phrase_id ID riga.
	 * @param int $att_id    Allegato.
	 * @return bool
	 */
	public static function set_phrase_notes_audio_id( $story_id, $phrase_id, $att_id ) {
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
				'audio_notes_female_id' => absint( $att_id ),
			),
			array(
				'id'       => $phrase_id,
				'story_id' => $story_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
		if ( false !== $result ) {
			do_action( 'llm_story_content_changed', $story_id );
		}
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
		do_action( 'llm_story_content_changed', $story_id );
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
					"SELECT audio_male_id, audio_female_id, audio_azure_male_id, audio_azure_female_id, audio_notes_female_id FROM {$p} WHERE story_id = %d",
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
						isset( $ar['audio_azure_female_id'] ) ? (int) $ar['audio_azure_female_id'] : 0,
						isset( $ar['audio_notes_female_id'] ) ? (int) $ar['audio_notes_female_id'] : 0
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
				'grammar_slots' => self::normalize_grammar_slots( isset( $row['grammar_slots'] ) ? $row['grammar_slots'] : array() ),
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
