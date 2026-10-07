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
	 * @return array<int, array{id:int,text:string,audio_id:int,audio_male_id:int,url:string,urlMale:string}>
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
				"SELECT id, listen_text, audio_id, audio_male_id FROM {$table} WHERE phrase_id = %d ORDER BY sort_order ASC, id ASC",
				$phrase_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$aid  = isset( $r['audio_id'] ) ? (int) $r['audio_id'] : 0;
			$maid = isset( $r['audio_male_id'] ) ? (int) $r['audio_male_id'] : 0;
			$out[] = array(
				'id'            => isset( $r['id'] ) ? (int) $r['id'] : 0,
				'text'          => isset( $r['listen_text'] ) ? (string) $r['listen_text'] : '',
				'audio_id'      => $aid,
				'audio_male_id' => $maid,
				'url'           => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $aid ) : '',
				'urlMale'       => class_exists( 'LLM_Phrase_TTS' ) ? LLM_Phrase_TTS::url( $maid ) : '',
			);
		}
		return $out;
	}

	/**
	 * Audio degli appunti per tutte le frasi di una storia (solo voci con MP3).
	 *
	 * @param int $story_id ID storia.
	 * @return array<int, array<int, array{text:string,url:string,urlMale:string}>>
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
				"SELECT phrase_id, listen_text, audio_id, audio_male_id FROM {$table} WHERE story_id = %d ORDER BY phrase_id ASC, sort_order ASC, id ASC",
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
			$maid = isset( $r['audio_male_id'] ) ? (int) $r['audio_male_id'] : 0;
			$url  = ( $aid > 0 && class_exists( 'LLM_Phrase_TTS' ) ) ? LLM_Phrase_TTS::url( $aid ) : '';
			$url_m = ( $maid > 0 && class_exists( 'LLM_Phrase_TTS' ) ) ? LLM_Phrase_TTS::url( $maid ) : '';
			if ( $pid < 1 || '' === $text || '' === $url ) {
				continue;
			}
			if ( ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = array();
			}
			$out[ $pid ][] = array(
				'text'    => $text,
				'url'     => $url,
				'urlMale' => $url_m,
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
		$by_f  = array();
		$by_m  = array();
		foreach ( $old as $row ) {
			$key = self::norm_key( $row['text'] );
			if ( '' === $key ) {
				continue;
			}
			if ( empty( $by_f[ $key ] ) && $row['audio_id'] > 0 ) {
				$by_f[ $key ] = (int) $row['audio_id'];
			}
			if ( empty( $by_m[ $key ] ) && ! empty( $row['audio_male_id'] ) ) {
				$by_m[ $key ] = (int) $row['audio_male_id'];
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
			$key  = self::norm_key( $text );
			$aid  = isset( $by_f[ $key ] ) ? (int) $by_f[ $key ] : 0;
			$maid = isset( $by_m[ $key ] ) ? (int) $by_m[ $key ] : 0;
			$wpdb->insert(
				$table,
				array(
					'story_id'      => $story_id,
					'phrase_id'     => $phrase_id,
					'sort_order'    => $i,
					'listen_text'   => $text,
					'audio_id'      => $aid,
					'audio_male_id' => $maid,
				),
				array( '%d', '%d', '%d', '%s', '%d', '%d' )
			);
			++$i;
		}
		do_action( 'llm_story_content_changed', $story_id );
	}

	/**
	 * Estrae testi da ascoltare: coniugazioni + 💬 Esempio + quote in 💡 Approfondimento.
	 *
	 * @param string $html        HTML appunti.
	 * @param string $target_lang Codice lingua obiettivo.
	 * @return string[] Elenco piatto (coniugazioni prima).
	 */
	public static function extract_from_html( $html, $target_lang = 'en' ) {
		$grouped = self::extract_grouped( $html, $target_lang );
		return array_merge( $grouped['conjugations'], $grouped['others'] );
	}

	/**
	 * @param string $html        HTML appunti.
	 * @param string $target_lang Codice lingua obiettivo.
	 * @return array{conjugations:string[],others:string[]}
	 */
	public static function extract_grouped( $html, $target_lang = 'en' ) {
		$html        = (string) $html;
		$target_lang = sanitize_key( (string) $target_lang );
		if ( '' === $target_lang ) {
			$target_lang = 'en';
		}
		$grouped = array(
			'conjugations' => array(),
			'others'       => array(),
		);
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return $grouped;
		}
		if ( preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $blocks ) ) {
			foreach ( $blocks[1] as $inner ) {
				self::extract_from_block( $inner, $grouped, $target_lang );
			}
		} else {
			self::extract_from_block( $html, $grouped, $target_lang );
		}
		// Dopo tutti i paragrafi: niente prune tra citazioni corte/liste e frasi esempio
		// (es. "me gusta" non deve sparire per "no me gusta" / "Me encanta …").
		return $grouped;
	}

	/**
	 * @param string                              $inner       HTML interno.
	 * @param array{conjugations:string[],others:string[]} $grouped Accumulo.
	 * @param string                              $target_lang Codice lingua obiettivo.
	 */
	private static function extract_from_block( $inner, array &$grouped, $target_lang = 'en' ) {
		$head        = trim( wp_strip_all_tags( (string) $inner ) );
		$skip_quotes = (bool) preg_match( '/^(Remember|Ricorda|Etymology|Etimologia|Curiosità|📜)/iu', $head );

		// 1) Coniugazioni: tutte le righe persona (forma prima di "(").
		$for_lines = preg_replace( '/<br\s*\/?>/i', "\n", (string) $inner );
		$for_lines = html_entity_decode( wp_strip_all_tags( (string) $for_lines ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$conj      = self::conjugation_line_regex( $target_lang );
		foreach ( preg_split( '/\n+/', (string) $for_lines ) as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( preg_match( $conj, $line, $cm ) ) {
				$form = self::clean_text( $cm[1] . ' ' . $cm[2] );
				if ( '' !== $form ) {
					self::push_text( $grouped['conjugations'], $form );
				}
			}
		}

		if ( $skip_quotes ) {
			return;
		}

		/*
		 * 2) Virgolette solo su righe 💬 Esempio e nel blocco 💡 Approfondimento.
		 * Esclusi: ❌/➡️, titoli <strong> "nota" → "obiettivo".
		 */
		$html_lines         = preg_split( '/<br\s*\/?>/i', (string) $inner );
		$in_approfondimento = false;
		foreach ( $html_lines as $hline ) {
			$plain = html_entity_decode( wp_strip_all_tags( (string) $hline ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$plain = trim( preg_replace( '/\s+/u', ' ', $plain ) );
			if ( '' === $plain ) {
				continue;
			}
			if ( preg_match( '/❌|➡️/u', $plain ) ) {
				continue;
			}

			$is_esempio             = (bool) preg_match( '/💬\s*Esempio|(?:^|\s)Esempio\s*:/iu', $plain );
			$starts_approfondimento = self::is_approfondimento_heading( $plain );

			if ( $is_esempio ) {
				$in_approfondimento = false;
			} elseif ( $starts_approfondimento ) {
				$in_approfondimento = true;
			}

			// Righe intro Approfondimento con citazioni meta (non da ascoltare).
			if ( $in_approfondimento && preg_match( '/\b(si usano con|funzionano come)\b/ui', $plain ) ) {
				continue;
			}

			if ( ! $is_esempio && ! $in_approfondimento ) {
				continue;
			}

			$for_ex = preg_replace( '/<strong\b[^>]*>[\s\S]*?<\/strong>/iu', ' ', (string) $hline );
			$for_ex = preg_replace( '/"[^"]+"\s*(?:→|&rarr;)\s*"[^"]+"/u', ' ', (string) $for_ex );
			$for_ex = html_entity_decode( wp_strip_all_tags( (string) $for_ex ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( ! preg_match_all( '/["“”«»]([^"“”«»]+)["“”«»]/u', (string) $for_ex, $em ) ) {
				continue;
			}
			foreach ( $em[1] as $q ) {
				$q  = self::clean_text( $q );
				$wc = self::word_count( $q );
				if ( $wc < 1 || $wc > 14 ) {
					continue;
				}
				if ( preg_match( '/^(Nos|Les)\s+complace$/ui', $q ) ) {
					continue;
				}
				// Esempio: prendi sempre la citazione (es. "¡Disfruta tu café!").
				if ( $is_esempio ) {
					self::push_text( $grouped['others'], $q );
					continue;
				}
				// "dire «dovere»" è il gloss della lingua nota, non una parola da ascoltare.
				if ( self::is_source_gloss_quote( $plain, $q ) ) {
					continue;
				}
				// Nel riquadro Approfondimento tieni anche le parole corte (my, Then, isn't).
				if ( self::looks_target( $q, $target_lang ) || self::keep_approfondimento_token( $q, $target_lang ) ) {
					self::push_text( $grouped['others'], $q );
				}
			}
		}
	}

	/**
	 * Regex riga coniugazione (persona + forma prima della parentesi).
	 *
	 * @param string $target_lang Codice lingua obiettivo.
	 * @return string
	 */
	private static function conjugation_line_regex( $target_lang ) {
		$target_lang = sanitize_key( (string) $target_lang );
		if ( 'it' === $target_lang ) {
			return '/^(io|tu|lui\/lei|noi|voi|loro)\s+(.+?)\s*\(/ui';
		}
		if ( 'pl' === $target_lang ) {
			return '/^(ja|ty|on\/ona\/ono|my|wy|oni\/one)\s+(.+?)\s*\(/ui';
		}
		if ( in_array( $target_lang, array( 'es', 'ca', 'gl', 'pt' ), true ) ) {
			return '/^(yo|tú|tu|él\/ella\/usted|nosotros\/nosotras|vosotros\/vosotras|ellos\/ellas\/ustedes)\s+(.+?)\s*\(/ui';
		}
		return '/^(I|You|He\s*\/\s*She\s*\/\s*It|We|They)\s+(.+?)\s*\(/ui';
	}

	/**
	 * True se $text è già coperto da un testo precedente (uguale o contenuto a parole).
	 *
	 * @param string                $text Testo candidato.
	 * @param array<string,bool>    $seen Chiavi già usate (lowercase).
	 * @return bool
	 */
	public static function text_covered_by_seen( $text, array $seen ) {
		$text = self::clean_text( $text );
		if ( '' === $text || ! $seen ) {
			return false;
		}
		$wc = self::word_count( $text );
		foreach ( array_keys( $seen ) as $prev ) {
			$wp = self::word_count( $prev );
			// Pronomi/parole singole vs frase d'esempio: non si escludono a vicenda.
			if ( 1 === $wc && $wp > 1 ) {
				continue;
			}
			if ( $wc > 1 && 1 === $wp ) {
				continue;
			}
			if ( self::contains_as_words( $prev, $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rimuove testi più corti già contenuti (a parole intere) in testi più lunghi.
	 *
	 * @param string[] $texts Testi.
	 * @return string[]
	 */
	private static function prune_contained_phrases( array $texts ) {
		$texts = array_values( $texts );
		usort(
			$texts,
			static function ( $a, $b ) {
				return self::word_count( $b ) <=> self::word_count( $a )
					?: ( function_exists( 'mb_strlen' ) ? mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' ) : strlen( $b ) - strlen( $a ) );
			}
		);
		$kept = array();
		foreach ( $texts as $t ) {
			$skip = false;
			$wc_t = self::word_count( $t );
			foreach ( $kept as $longer ) {
				// Mantieni pronomi/parole singole anche se compaiono dentro una frase d'esempio.
				if ( 1 === $wc_t && self::word_count( $longer ) > 1 ) {
					continue;
				}
				if ( self::contains_as_words( $longer, $t ) ) {
					$skip = true;
					break;
				}
			}
			if ( ! $skip ) {
				$kept[] = $t;
			}
		}
		return $kept;
	}

	/**
	 * @param string $haystack Frase lunga.
	 * @param string $needle   Frase corta.
	 * @return bool
	 */
	private static function contains_as_words( $haystack, $needle ) {
		$h = self::norm_key( $haystack );
		$n = self::norm_key( $needle );
		if ( '' === $n || $h === $n ) {
			return false;
		}
		$h = ' ' . preg_replace( '/\s+/u', ' ', $h ) . ' ';
		$n = ' ' . preg_replace( '/\s+/u', ' ', $n ) . ' ';
		return false !== ( function_exists( 'mb_strpos' ) ? mb_strpos( $h, $n ) : strpos( $h, $n ) );
	}

	/**
	 * Anteprima admin: grammar formattato + icona (non cliccabile) sulla prima occorrenza di ogni testo selezionato.
	 *
	 * @param string                                                                 $html  HTML consigli.
	 * @param array<int, array{text?:string,url?:string,audio_id?:int}> $items Voci listen.
	 * @return string HTML.
	 */
	public static function admin_preview_html( $html, array $items ) {
		$html = (string) $html;
		if ( '' === trim( wp_strip_all_tags( $html ) ) || ! $items ) {
			return $html;
		}
		$needles = array();
		foreach ( $items as $item ) {
			$text = isset( $item['text'] ) ? self::clean_text( $item['text'] ) : '';
			if ( '' === $text ) {
				continue;
			}
			$key = self::norm_key( $text );
			if ( isset( $needles[ $key ] ) ) {
				continue;
			}
			$needles[ $key ] = $text;
		}
		uasort(
			$needles,
			static function ( $a, $b ) {
				return self::word_count( $b ) <=> self::word_count( $a )
					?: ( function_exists( 'mb_strlen' ) ? mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' ) : strlen( $b ) - strlen( $a ) );
			}
		);
		foreach ( $needles as $needle ) {
			$conj = self::looks_conjugation_form( $needle );
			if ( $conj ) {
				for ( $n = 0; $n < 8; $n++ ) {
					$next = self::wrap_first_plain_occurrence( $html, $needle, true );
					if ( $next === $html ) {
						break;
					}
					$html = $next;
				}
			} else {
				// Una icona per paragrafo/slot (stesso testo può ripetersi in più appunti).
				$html = self::wrap_once_per_paragraph( $html, $needle, false );
			}
		}
		return $html;
	}

	/**
	 * Una evidenziazione per ogni <p> (slot appunti).
	 *
	 * @param string $html      HTML.
	 * @param string $needle    Testo.
	 * @param bool   $conj_only Solo coniugazioni.
	 * @return string
	 */
	private static function wrap_once_per_paragraph( $html, $needle, $conj_only = false ) {
		if ( ! preg_match_all( '/<p\b[^>]*>[\s\S]*?<\/p>/iu', (string) $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return self::wrap_first_plain_occurrence( $html, $needle, $conj_only );
		}
		for ( $i = count( $matches[0] ) - 1; $i >= 0; $i-- ) {
			$chunk   = $matches[0][ $i ][0];
			$off     = (int) $matches[0][ $i ][1];
			$wrapped = self::wrap_first_plain_occurrence( $chunk, $needle, $conj_only );
			if ( $wrapped !== $chunk ) {
				$html = substr( (string) $html, 0, $off ) . $wrapped . substr( (string) $html, $off + strlen( $chunk ) );
			}
		}
		return $html;
	}

	/**
	 * Forma di coniugazione tipo "I lived" / "You live" / "He/She/It lives".
	 *
	 * @param string $text Testo.
	 * @return bool
	 */
	private static function looks_conjugation_form( $text ) {
		$text = self::clean_text( $text );
		// Solo "We waited" / "He/She/It lives" (pronome + 1 token), non "We waited for the pizza".
		return (bool) preg_match(
			'/^(I|You|He\s*\/\s*She\s*\/\s*It|We|They|io|tu|lui\/lei|noi|voi|loro|ja|ty|on\/ona\/ono|my|wy|oni\/one|yo|tú|tu|él\/ella\/usted|nosotros\/nosotras|vosotros\/vosotras|ellos\/ellas\/ustedes)\s+\S+$/ui',
			$text
		);
	}

	/**
	 * Avvolge la prima occorrenza di $needle (parole intere) solo in zone ammesse:
	 * 💬 Esempio, 💡 Approfondimento, oppure riga coniugazione (se $conj_only).
	 *
	 * @param string $html      HTML.
	 * @param string $needle    Testo.
	 * @param bool   $conj_only Se true, solo occorrenze seguite da " (" (riga coniugazione).
	 * @return string
	 */
	private static function wrap_first_plain_occurrence( $html, $needle, $conj_only = false ) {
		$needle = (string) $needle;
		$nlen   = function_exists( 'mb_strlen' ) ? mb_strlen( $needle, 'UTF-8' ) : strlen( $needle );
		if ( $nlen < 1 ) {
			return $html;
		}

		$allowed = self::listen_highlight_allowed_ranges( $html, $conj_only );

		// Zone da non evidenziare: titoli; già marcati.
		$blocked = array();
		if ( preg_match_all( '/<strong\b[^>]*>[\s\S]*?<\/strong>/iu', $html, $sm, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $sm[0] as $m ) {
				$blocked[] = array( $m[1], $m[1] + strlen( $m[0] ) );
			}
		}
		if ( preg_match_all( '/llm-notes-listen-admin__(?:mark|sel)\b[^>]*>[\s\S]*?<\/span>/iu', $html, $mm, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $mm[0] as $m ) {
				$blocked[] = array( $m[1], $m[1] + strlen( $m[0] ) );
			}
		}

		$in_ranges = static function ( $byte_pos, array $ranges ) {
			foreach ( $ranges as $b ) {
				if ( $byte_pos >= $b[0] && $byte_pos < $b[1] ) {
					return true;
				}
			}
			return false;
		};

		$hlen   = function_exists( 'mb_strlen' ) ? mb_strlen( $html, 'UTF-8' ) : strlen( $html );
		$in_tag = false;
		$buf    = '';
		for ( $i = 0; $i < $hlen; $i++ ) {
			$ch = function_exists( 'mb_substr' ) ? mb_substr( $html, $i, 1, 'UTF-8' ) : $html[ $i ];
			if ( '<' === $ch ) {
				$in_tag = true;
				$buf    = '';
			} elseif ( '>' === $ch ) {
				$in_tag = false;
				$buf    = '';
			} elseif ( ! $in_tag ) {
				$buf .= $ch;
				$blen = function_exists( 'mb_strlen' ) ? mb_strlen( $buf, 'UTF-8' ) : strlen( $buf );
				if ( $blen > $nlen ) {
					$buf  = function_exists( 'mb_substr' ) ? mb_substr( $buf, -$nlen, null, 'UTF-8' ) : substr( $buf, -$nlen );
					$blen = $nlen;
				}
				if ( $blen === $nlen && $buf === $needle ) {
					$start_char = $i + 1 - $nlen;
					$start_byte = strlen(
						function_exists( 'mb_substr' ) ? mb_substr( $html, 0, $start_char, 'UTF-8' ) : substr( $html, 0, $start_char )
					);
					if ( $in_ranges( $start_byte, $blocked ) || ! $in_ranges( $start_byte, $allowed ) ) {
						$buf = '';
						continue;
					}
					// Confini di parola: niente "te" dentro "amarte".
					$before_ch = $start_char > 0
						? ( function_exists( 'mb_substr' ) ? mb_substr( $html, $start_char - 1, 1, 'UTF-8' ) : $html[ $start_char - 1 ] )
						: '';
					$after_ch  = ( $i + 1 ) < $hlen
						? ( function_exists( 'mb_substr' ) ? mb_substr( $html, $i + 1, 1, 'UTF-8' ) : $html[ $i + 1 ] )
						: '';
					if ( self::is_word_char( $before_ch ) || self::is_word_char( $after_ch ) ) {
						$buf = '';
						continue;
					}
					// Non-coniugazioni: solo citazioni tra virgolette (niente "te" nel gloss italiano).
					if ( ! $conj_only && ( ! self::is_quote_char( $before_ch ) || ! self::is_quote_char( $after_ch ) ) ) {
						$buf = '';
						continue;
					}
					// Coniugazioni: solo se dopo c'è " (" (es. You waited (tu…)), non dentro "We waited for the pizza".
					if ( $conj_only && ! self::next_is_conj_paren( $html, $i + 1 ) ) {
						$buf = '';
						continue;
					}
					$mark = self::admin_mark_html( $needle );
					$head = function_exists( 'mb_substr' ) ? mb_substr( $html, 0, $start_char, 'UTF-8' ) : substr( $html, 0, $start_char );
					$tail = function_exists( 'mb_substr' ) ? mb_substr( $html, $i + 1, null, 'UTF-8' ) : substr( $html, $i + 1 );
					return $head . $mark . $tail;
				}
			}
		}
		return $html;
	}

	/**
	 * Range byte ammessi per l’icona ascolto: Esempio, Approfondimento, (opz.) coniugazioni.
	 *
	 * @param string $html      HTML appunti.
	 * @param bool   $conj_only Se true, solo righe coniugazione.
	 * @return array<int, array{0:int,1:int}>
	 */
	private static function listen_highlight_allowed_ranges( $html, $conj_only = false ) {
		$ranges = array();
		$parts  = preg_split( '/(<p\b[^>]*>|<\/p>|<br\s*\/?>)/iu', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE );
		if ( ! is_array( $parts ) || ! $parts ) {
			return $ranges;
		}
		$in_p               = false;
		$in_approfondimento = false;
		$line_start         = 0;
		$line_html          = '';
		$flush_line         = static function () use ( &$ranges, &$line_start, &$line_html, &$in_approfondimento, $conj_only ) {
			if ( '' === $line_html ) {
				return;
			}
			$plain = html_entity_decode( wp_strip_all_tags( $line_html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$plain = trim( preg_replace( '/\s+/u', ' ', $plain ) );
			$end   = $line_start + strlen( $line_html );
			$allow = false;
			if ( $plain && ! preg_match( '/❌|➡️|⚠️/u', $plain ) ) {
				$is_esempio = (bool) preg_match( '/💬\s*Esempio|(?:^|\s)Esempio\s*:/iu', $plain );
				$is_app     = self::is_approfondimento_heading( $plain );
				if ( $is_esempio ) {
					$in_approfondimento = false;
					$allow              = ! $conj_only;
				} elseif ( $is_app ) {
					$in_approfondimento = true;
					$allow              = ! $conj_only;
				} elseif ( $in_approfondimento ) {
					$allow = ! $conj_only;
				} elseif ( $conj_only && preg_match( '/\(/u', $plain ) ) {
					$allow = true;
				}
			} else {
				$in_approfondimento = false;
			}
			if ( $allow ) {
				$ranges[] = array( $line_start, $end );
			}
			$line_html = '';
		};
		foreach ( $parts as $part ) {
			$tok = isset( $part[0] ) ? (string) $part[0] : '';
			$off = isset( $part[1] ) ? (int) $part[1] : 0;
			if ( preg_match( '/^<p\b/i', $tok ) ) {
				$flush_line();
				$in_p               = true;
				$in_approfondimento = false;
				$line_start         = $off + strlen( $tok );
				$line_html          = '';
				continue;
			}
			if ( preg_match( '/^<\/p>/i', $tok ) ) {
				$flush_line();
				$in_p               = false;
				$in_approfondimento = false;
				continue;
			}
			if ( preg_match( '/^<br\b/i', $tok ) ) {
				if ( $in_p ) {
					$flush_line();
					$line_start = $off + strlen( $tok );
				}
				continue;
			}
			if ( $in_p ) {
				if ( '' === $line_html ) {
					$line_start = $off;
				}
				$line_html .= $tok;
			}
		}
		$flush_line();
		return $ranges;
	}

	/**
	 * @param string $html       HTML.
	 * @param int    $start_char Indice carattere dopo il match.
	 * @return bool
	 */
	private static function next_is_conj_paren( $html, $start_char ) {
		$hlen = function_exists( 'mb_strlen' ) ? mb_strlen( $html, 'UTF-8' ) : strlen( $html );
		$in_tag = false;
		for ( $j = $start_char; $j < $hlen; $j++ ) {
			$ch = function_exists( 'mb_substr' ) ? mb_substr( $html, $j, 1, 'UTF-8' ) : $html[ $j ];
			if ( '<' === $ch ) {
				$in_tag = true;
				continue;
			}
			if ( '>' === $ch ) {
				$in_tag = false;
				continue;
			}
			if ( $in_tag ) {
				continue;
			}
			if ( preg_match( '/\s/u', $ch ) ) {
				continue;
			}
			return '(' === $ch;
		}
		return false;
	}

	/**
	 * @param string $ch Carattere.
	 * @return bool
	 */
	private static function is_word_char( $ch ) {
		if ( '' === $ch || '<' === $ch || '>' === $ch ) {
			return false;
		}
		return (bool) preg_match( '/^[\p{L}\p{N}_]$/u', $ch );
	}

	/**
	 * @param string $ch Carattere.
	 * @return bool
	 */
	private static function is_quote_char( $ch ) {
		return (bool) preg_match( '/^["“”«»]$/u', (string) $ch );
	}

	/**
	 * @param string $needle Testo selezionato.
	 * @return string
	 */
	private static function admin_mark_html( $needle ) {
		$icon = '<span class="llm-notes-listen-admin__mark dashicons dashicons-controls-volumeon" aria-hidden="true" title="'
			. esc_attr__( 'Parola/frase selezionata per l’audio', 'llm-con-tabelle' )
			. '"></span>';
		return $icon . '<span class="llm-notes-listen-admin__sel">' . esc_html( $needle ) . '</span>';
	}

	/**
	 * @param string[] $out  Elenco.
	 * @param string   $text Testo.
	 */
	private static function push_text( array &$out, $text ) {
		$text = self::clean_text( $text );
		if ( '' === $text ) {
			return;
		}
		$key = self::norm_key( $text );
		foreach ( $out as $existing ) {
			if ( self::norm_key( $existing ) === $key ) {
				return;
			}
		}
		$out[] = $text;
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
		$cache_f = array();
		$cache_m = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$known = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listen_text, audio_id, audio_male_id FROM {$table} WHERE story_id = %d",
				(int) $story_id
			),
			ARRAY_A
		);
		if ( is_array( $known ) ) {
			foreach ( $known as $kr ) {
				$kk = self::norm_key( isset( $kr['listen_text'] ) ? (string) $kr['listen_text'] : '' );
				if ( '' === $kk ) {
					continue;
				}
				$ka = isset( $kr['audio_id'] ) ? (int) $kr['audio_id'] : 0;
				$km = isset( $kr['audio_male_id'] ) ? (int) $kr['audio_male_id'] : 0;
				if ( $ka > 0 && empty( $cache_f[ $kk ] ) ) {
					$cache_f[ $kk ] = $ka;
				}
				if ( $km > 0 && empty( $cache_m[ $kk ] ) ) {
					$cache_m[ $kk ] = $km;
				}
			}
		}
		foreach ( $rows as $row ) {
			self::fill_listen_audio( $row, $story_id, $phrase_id, $locale, 'female', $cache_f, $stats );
			self::fill_listen_audio( $row, $story_id, $phrase_id, $locale, 'male', $cache_m, $stats );
		}
		if ( $stats['ok'] > 0 ) {
			do_action( 'llm_story_content_changed', absint( $story_id ) );
		}
		return $stats;
	}

	/**
	 * @param array{id:int,text:string,audio_id:int,audio_male_id:int} $row       Riga.
	 * @param int                                                     $story_id  ID storia.
	 * @param int                                                     $phrase_id ID frase.
	 * @param string                                                  $locale    Locale.
	 * @param string                                                  $gender    female|male.
	 * @param array<string,int>                                       $cache     Cache per chiave.
	 * @param array{ok:int,skip:int,err:int}                          $stats     Contatori.
	 */
	private static function fill_listen_audio( $row, $story_id, $phrase_id, $locale, $gender, array &$cache, array &$stats ) {
		global $wpdb;
		$col     = 'male' === $gender ? 'audio_male_id' : 'audio_id';
		$aid     = isset( $row[ $col ] ) ? (int) $row[ $col ] : 0;
		$key     = self::norm_key( $row['text'] );
		$spoken  = self::speak_text( $row['text'] );
		$has_url = $aid > 0 && LLM_Phrase_TTS::url( $aid );
		$speak_meta  = $has_url ? (string) get_post_meta( $aid, '_llm_notes_listen_speak', true ) : '';
		$slash_stale = ( false !== strpos( $row['text'], '/' ) ) && $speak_meta !== $spoken;
		if ( $has_url && ! $slash_stale ) {
			$cache[ $key ] = $aid;
			++$stats['skip'];
			return;
		}
		if ( isset( $cache[ $key ] ) ) {
			$table = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
			$wpdb->update( $table, array( $col => $cache[ $key ] ), array( 'id' => $row['id'] ), array( '%d' ), array( '%d' ) );
			++$stats['skip'];
			return;
		}
		$prefix = 'male' === $gender ? 'nwm-' : 'nw-';
		$tag    = $prefix . substr( md5( $key . '|' . $spoken ), 0, 10 );
		$new_id = 'male' === $gender
			? LLM_Phrase_TTS::azure_male_attachment( $story_id, $phrase_id, $spoken, $locale, $tag )
			: LLM_Phrase_TTS::azure_female_attachment( $story_id, $phrase_id, $spoken, $locale, $tag );
		if ( is_wp_error( $new_id ) || (int) $new_id < 1 ) {
			++$stats['err'];
			return;
		}
		update_post_meta( (int) $new_id, '_llm_notes_listen_speak', $spoken );
		$cache[ $key ] = (int) $new_id;
		$table         = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
		$wpdb->update( $table, array( $col => (int) $new_id ), array( 'id' => $row['id'] ), array( '%d' ), array( '%d' ) );
		++$stats['ok'];
	}

	/**
	 * @param string $text Testo.
	 * @return string
	 */
	public static function clean_text( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';
		// Non usare trim(..., "«»“”"): in PHP la mask è byte-oriented e spezza ¡ ¿ (UTF-8).
		$text = preg_replace( '/^[\s\"“”«»]+|[\s\"“”«»]+$/u', '', $text );
		return is_string( $text ) ? $text : '';
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
	 * @param string $lang Codice lingua obiettivo.
	 * @return bool
	 */
	private static function looks_example_sentence( $text, $lang = 'en' ) {
		$text = self::clean_text( $text );
		if ( '' === $text ) {
			return false;
		}
		$lang = sanitize_key( (string) $lang );
		if ( 'it' === $lang ) {
			return (bool) preg_match( '/\b(io|tu|lui|lei|noi|voi|loro)\b/ui', $text )
				|| (bool) preg_match( '/[?¿]/u', $text )
				|| (bool) preg_match( '/\b(ciao|buongiorno|buonasera|sto|stai|voglio|vuoi|posso|puoi|mi|ti|piace|andiamo|vado|non|sì|va|cosa|come|saluto|scusi|vediamo)\b/ui', $text );
		}
		if ( 'pl' === $lang ) {
			return (bool) preg_match( '/\b(ja|ty|on|ona|ono|my|wy|oni|one)\b/ui', $text )
				|| (bool) preg_match( '/[?¿]/u', $text )
				|| (bool) preg_match( '/\b(cześć|dzień|dobry|jak|się|masz|dobrze|dziękuję|też|mam|imię|miło|dokąd|jadę|jedziesz|wszystkim)\b/ui', $text );
		}
		return (bool) preg_match( '/\b(I|you|he|she|it|we|they|is|are|was|were|am)\b/i', $text );
	}

	/**
	 * Intestazione del riquadro Approfondimento (lampadina o puntina).
	 *
	 * @param string $plain Riga di testo.
	 * @return bool
	 */
	private static function is_approfondimento_heading( $plain ) {
		return (bool) preg_match(
			'/(?:📌|💡)\s*(?:Approfondimento|Deep dive)|(?:^|\s)(?:Approfondimento|Deep dive)\s*:/iu',
			(string) $plain
		);
	}

	/**
	 * Citazione della lingua nota introdotta da "dire/significa", non da ascoltare.
	 *
	 * @param string $plain Riga.
	 * @param string $quote Citazione.
	 * @return bool
	 */
	private static function is_source_gloss_quote( $plain, $quote ) {
		$quote = self::clean_text( $quote );
		if ( '' === $quote ) {
			return false;
		}
		$q = preg_quote( $quote, '/' );
		return (bool) preg_match(
			'/\b(?:dire|significa|si traduce(?:\s+con)?|si dice)\s+["“”«»]' . $q . '["“”«»]/ui',
			(string) $plain
		);
	}

	/**
	 * Parola corta del riquadro Approfondimento (my, Then, isn't) che looks_target scarta.
	 *
	 * @param string $text Testo.
	 * @param string $lang Codice lingua obiettivo.
	 * @return bool
	 */
	private static function keep_approfondimento_token( $text, $lang ) {
		$lang = sanitize_key( (string) $lang );
		if ( 'en' !== $lang ) {
			return false;
		}
		$text = self::clean_text( $text );
		if ( '' === $text || preg_match( '/[àèéìòù]/u', $text ) ) {
			return false;
		}
		if ( preg_match( '/^(il|lo|la|gli|le|un|uno|una)\s+/iu', $text ) ) {
			return false;
		}
		if ( preg_match( '/\b(il|lo|la|gli|le|un|uno|una|del|della|dei|degli|delle|nel|nella|significa|dovere|obbligo|possesso)\b/ui', $text )
			&& ! preg_match( '/\b(I|you|he|she|it|we|they|the|a|an|to|of|in|on|is|are|my|your|his|her|our|their|its|not)\b/i', $text ) ) {
			return false;
		}
		$wc = self::word_count( $text );
		return $wc >= 1 && $wc <= 14;
	}

	/**
	 * @param string $text Testo.
	 * @param string $lang Codice lingua obiettivo.
	 * @return bool
	 */
	private static function looks_target( $text, $lang = 'en' ) {
		$text = self::clean_text( $text );
		if ( '' === $text ) {
			return false;
		}
		$lang = sanitize_key( (string) $lang );
		if ( 'it' === $lang ) {
			if ( preg_match( '/\b(in English|present of|without|do you|you want|want me to|that I come|here in|this stamp|six months|we see|each other|there is|there are|no extra)\b/i', $text ) ) {
				return false;
			}
			if ( preg_match( '/\b(the|are|and|without|forms|present|english|means)\b/i', $text )
				&& preg_match( '/\b(io|tu|lui|lei|noi|voi|loro)\b/ui', $text ) ) {
				return false;
			}
			if ( preg_match( '/^(to|the|this|that|how|what|when|where|I|I\'m|I’m|you|we|they|here|do|did|does|can|could|would|should|will|we’ll|we\'ll)\b/iu', $text ) ) {
				return false;
			}
			$it_strong = (bool) preg_match( '/[àèéìòù]/u', $text )
				|| (bool) preg_match( '/\b(io|tu|lui|lei|noi|voi|loro|ciao|buongiorno|buonasera|sto|stai|sta|stiamo|state|stanno|mi|ti|si|ci|chiamo|chiami|chiama|piace|piacciono|voglio|vuoi|vuole|posso|puoi|devo|devi|deve|andare|casa|oggi|grazie|piacere|come|cosa|dove|quando|perché|non|bene|male|qui|anche|anch’io|anch\'io|saluto|vado|vai|bevo|parlo|capisco|imparo|conosco|scendo|vengo|penso|sì)\b/ui', $text )
				|| (bool) preg_match( '/\b\p{L}+(iamo|ate|ano|ire|are|ere)\b/ui', $text );
			$en_gloss  = (bool) preg_match(
				'/\b(I|I\'m|I’m|you|we|they|the|this|that|to|how|what|when|where|is|are|was|were|my|your|name|want|going|meet|learn|speak|tell|stay|leave|able|please|hello|good|morning|fine|too|each|other|again|almost|here|well|happy|afraid|certain|drink|visit|listen|music|people|home|hour|today|tomorrow|nice|call|myself|means|pleasing|why|because|sorry|excuse|know|see|come|do)\b/i',
				$text
			);
			if ( $en_gloss && ! $it_strong ) {
				return false;
			}
			return $it_strong;
		}
		if ( 'es' === $lang || 'ca' === $lang || 'gl' === $lang ) {
			if ( preg_match( '/\b(in italiano|significa|non dire|non usare|non scrivere|evita)\b/ui', $text ) ) {
				return false;
			}
			// Gloss italiano puro (senza segni spagnoli).
			if ( preg_match( '/[àèéìòù]/u', $text ) && ! preg_match( '/[áéíóúñü¿¡]/ui', $text ) ) {
				return false;
			}
			if ( preg_match( '/[áéíóúñü¿¡]/ui', $text ) ) {
				return true;
			}
			if ( preg_match(
				'/\b(yo|tú|tu|él|ella|usted|nosotros|vosotros|ellos|ellas|me|te|le|nos|os|les|lo|la|los|las|un|una|el|de|en|con|por|para|que|no|sí|muy|más|como|qué|dónde|cuando|quiero|voy|gusta|complace|encanta|disfruto|disfruta|amor|amigo|amar|amarte|odio|mimar|besar|abrazar)\b/ui',
				$text
			) ) {
				return true;
			}
			if ( preg_match( '/\b\p{L}+(ar|er|ir|ando|iendo|ado|ido)\b/ui', $text ) ) {
				return true;
			}
			// Sostantivi/aggettivi spagnoli senza accento (es. "caricia").
			if ( 1 === self::word_count( $text )
				&& preg_match( '/^\p{L}{3,}$/u', $text )
				&& ! preg_match( '/^(il|lo|la|gli|le|un|uno|una|di|da|in|con|per|come|anche|non|che|mi|ti|si|ci|vi|sono|questo|quella|quello|essere|piace|carezza|affetto|tesoro)$/ui', $text ) ) {
				return true;
			}
			return false;
		}
		if ( 'pl' === $lang ) {
			if ( preg_match( '/\b(significa|in italiano|non dire|non esiste|non è|non usare|non togliere)\b/ui', $text ) ) {
				return false;
			}
			if ( preg_match( '/^(ciao|come|sto|grazie|io|tu|e tu|anche|piacere|dove|a casa)\b/iu', $text ) ) {
				return false;
			}
			$pl_strong = (bool) preg_match( '/[ąćęłńóśźż]/ui', $text )
				|| (bool) preg_match( '/\b(cześć|dzień|dobry|dobranoc|dobrze|dziękuję|dziękować|jak|się|masz|mam|mamy|macie|mają|też|imię|nazywam|miło|poznać|dokąd|gdzie|jadę|jedziesz|jechać|domu|szkoły)\b/ui', $text );
			$it_gloss  = (bool) preg_match( '/[àèéìòù]/u', $text )
				|| (bool) preg_match( '/\b(grazie|ciao|buongiorno|come|stai|sto|anche|piacere|conoscerti|andando|casa|io|tu|lui|lei|noi|voi|loro|significa|avverbio)\b/ui', $text );
			if ( $it_gloss && ! $pl_strong ) {
				return false;
			}
			return $pl_strong;
		}

		// Target inglese: niente italiano / glossarie / stopword da sole / errori tipici da ❌.
		if ( preg_match( '/[àèéìòù]/u', $text ) ) {
			return false;
		}
		// Forme sbagliate tipiche negli esempi ❌ (non da ascoltare).
		if ( preg_match( '/\bthe\s+their\b/i', $text ) || preg_match( '/\battackt\b/i', $text ) ) {
			return false;
		}
		if ( preg_match( '/\b(il|lo|la|gli|le|mio|mia|miei|mie|tuo|tua|tuoi|tue|loro|casa|fuggire|scappare|evadere|finalmente|attaccare|attaccò)\b/ui', $text )
			&& ! preg_match( '/\b(the|a|an|to|of|in|on|at|for|with|is|are|was|were|you|he|she|we|they|this|that|yes|no|my|your|their|home|house)\b/i', $text ) ) {
			return false;
		}
		if ( substr_count( $text, ',' ) >= 1
			&& preg_match( '/^[\p{L}\s,\'’]+$/u', $text )
			&& self::word_count( $text ) <= 8
			&& ! preg_match( '/\b(you|he|she|it|we|they|is|are|was|were|this|that|yes|no|look|my|your|his|her|the|a|an)\b/i', $text ) ) {
			return false;
		}
		if ( preg_match( '/\b(il|lo|la|gli|le|un|uno|una|del|della|dei|degli|delle|nel|nella|nei|nelle|sul|sulla|con|per|come|anche|non|che|di|da|su|tra|fra)\b/ui', $text )
			&& ! preg_match( '/\b(I|you|he|she|it|we|they|the|a|an|to|of|in|on|at|for|with|from|is|are|was|were|am|do|did|does|can|will|would|my|your|his|her|their|this|that)\b/i', $text ) ) {
			return false;
		}
		if ( preg_match(
			'/\b(nella|viveva|borsa|pelle|durava|corallo|piccolo|tutto|aspettava|aspettare|aspettavo|aspettavi|aspettiamo|vivere|abitare|visse|barriera|corallina|uova|parte|davanti|nome|proprio|italiano|inglese|significa|vuol|dire|soggetto|verbo|imperfetto|passato|presente|coniugazione|regolare|articolo|tono|familiare|amico|amica|giorni|mattina|fermata|minuti|porta|fratello|sorella)\b/ui',
			$text
		) ) {
			// Non scartare se è chiaramente una frase inglese (es. "We waited for the pizza").
			if ( ! preg_match( '/\b(I|you|he|she|it|we|they|is|are|was|were|am|do|did|does|can|will|the|a|an|for|with|to|of|in|on|at)\b/i', $text ) ) {
				return false;
			}
		}
		if ( preg_match( '/^(il|lo|la|gli|le|un|uno|una)\s+/iu', $text ) ) {
			return false;
		}
		$wc = self::word_count( $text );
		if ( 1 === $wc ) {
			$w = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
			$stop = array(
				'the', 'a', 'an', 'in', 'on', 'at', 'for', 'to', 'of', 'with', 'by', 'and', 'or', 'from', 'as',
				'is', 'are', 'was', 'were', 'be', 'am', 'do', 'did', 'does', 'this', 'that', 'it', 'he', 'she',
				'we', 'they', 'you', 'i', 'my', 'your', 'his', 'her', 'their', 'our', 'not', 'no', 'yes', 'so',
				'if', 'but', 'than', 'then', 'too', 'very', 'just', 'also', 'only', 'here', 'there', 'when',
				'where', 'what', 'who', 'how', 'why', 'which', 'into', 'onto', 'over', 'under', 'about', 'after',
				'before', 'between', 'through', 'during', 'without', 'within', 'each', 'every', 'all', 'some',
				'any', 'more', 'most', 'other', 'such', 'own', 'same', 'both', 'few', 'many', 'much', 'e', 'o',
			);
			if ( in_array( $w, $stop, true ) ) {
				return false;
			}
			if ( function_exists( 'mb_strlen' ) ? mb_strlen( $w, 'UTF-8' ) < 2 : strlen( $w ) < 2 ) {
				return false;
			}
		}
		return true;
	}
}
