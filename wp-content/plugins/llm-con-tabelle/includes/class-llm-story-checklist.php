<?php
/**
 * Checklist avanzamento storia (admin): conteggi + date, ricalcolo automatico.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Checklist {

	const META_KEY = '_llm_story_checklist';

	public static function init() {
		add_action( 'save_post_' . LLM_STORY_CPT, array( __CLASS__, 'on_save_post' ), 40, 2 );
		add_action( 'llm_story_content_changed', array( __CLASS__, 'refresh' ), 10, 1 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'wp_ajax_llm_story_checklist_prompt', array( __CLASS__, 'ajax_prompt' ) );
		add_action( 'wp_ajax_llm_story_checklist_info', array( __CLASS__, 'ajax_info' ) );
		add_action( 'wp_ajax_llm_story_checklist_phrases', array( __CLASS__, 'ajax_phrases' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Checklist item key → prompt filename (in prompt/CREA STORIA/ o plugin/prompts/).
	 *
	 * @return array<string,string>
	 */
	public static function prompt_map() {
		return array(
			'grammar'       => 'Prompt_Crea_Appunti.md',
			'load_appunti'  => 'script_load_appunti.md',
			'pronunciation' => 'genera-consigli-pronuncia.md',
			'remember'      => 'prompt-per-generare-i-punti-elenco.md',
			'notes'         => 'genera-note-della-storia.md',
			'notes_target'  => 'genera-note-della-storia.md',
			'story_texts'   => 'Crea storia da documento.md',
			'listen_words'  => 'script_listen_words.md',
		);
	}

	/**
	 * Checklist item key → info_*.md (prompt/CREA STORIA/ o plugin/prompts/).
	 *
	 * @return array<string,string>
	 */
	public static function info_map() {
		$keys = array(
			'phrases',
			'phrases_alt_export',
			'langs',
			'cover',
			'story_texts',
			'cefr_topics',
			'sources',
			'media',
			'notes',
			'notes_target',
			'grammar',
			'load_appunti',
			'alt',
			'pronunciation',
			'ipa',
			'approx',
			'remember',
			'audio_tts',
			'audio_azure',
			'audio_notes',
			'listen_words',
			'crossword',
			'published',
		);
		$out = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = 'info_' . $key . '.md';
		}
		return $out;
	}

	/**
	 * @param string $item_key Checklist item key.
	 * @return string Absolute path or empty.
	 */
	public static function prompt_path_for( $item_key ) {
		return self::md_path_for( $item_key, self::prompt_map() );
	}

	/**
	 * @param string $item_key Checklist item key.
	 * @return string Absolute path or empty.
	 */
	public static function info_path_for( $item_key ) {
		return self::md_path_for( $item_key, self::info_map() );
	}

	/**
	 * @param string               $item_key Checklist item key.
	 * @param array<string,string> $map      Filename map.
	 * @return string Absolute path or empty.
	 */
	private static function md_path_for( $item_key, array $map ) {
		$file = isset( $map[ $item_key ] ) ? (string) $map[ $item_key ] : '';
		if ( '' === $file || false !== strpos( $file, '..' ) || false !== strpos( $file, '/' ) || false !== strpos( $file, '\\' ) ) {
			return '';
		}
		$candidates = array();
		if ( defined( 'ABSPATH' ) ) {
			$candidates[] = trailingslashit( ABSPATH ) . 'prompt/CREA STORIA/' . $file;
		}
		if ( defined( 'LLM_TABELLE_DIR' ) ) {
			$candidates[] = trailingslashit( LLM_TABELLE_DIR ) . 'prompts/' . $file;
		}
		foreach ( $candidates as $path ) {
			if ( is_readable( $path ) ) {
				return $path;
			}
		}
		return '';
	}

	/**
	 * @param string $item_key Checklist item key.
	 * @return string Raw prompt text or empty.
	 */
	public static function load_prompt( $item_key ) {
		return self::load_md_file( self::prompt_path_for( $item_key ) );
	}

	/**
	 * @param string $item_key Checklist item key.
	 * @return string Raw info text or empty.
	 */
	public static function load_info( $item_key ) {
		return self::load_md_file( self::info_path_for( $item_key ) );
	}

	/**
	 * @param string $path Absolute path.
	 * @return string
	 */
	private static function load_md_file( $path ) {
		if ( '' === $path ) {
			return '';
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_string( $raw ) ? $raw : '';
	}

	public static function enqueue_assets() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User || ! in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}
		$need = false;
		if ( is_admin() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$need   = $screen && isset( $screen->post_type ) && LLM_STORY_CPT === $screen->post_type;
		} elseif ( is_singular( LLM_STORY_CPT ) ) {
			$need = true;
		}
		if ( ! $need ) {
			return;
		}
		wp_enqueue_style(
			'llm-story-checklist',
			LLM_TABELLE_URL . 'assets/llm-story-checklist.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-story-checklist',
			LLM_TABELLE_URL . 'assets/llm-story-checklist.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-story-checklist',
			'llmStoryChecklist',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'llm_story_checklist_prompt' ),
				'i18n'    => array(
					'title'        => __( 'Prompt', 'llm-con-tabelle' ),
					'titleInfo'    => __( 'Info', 'llm-con-tabelle' ),
					'titlePhrases' => __( 'Frasi', 'llm-con-tabelle' ),
					'titleAlt'     => __( 'Frasi + alternative', 'llm-con-tabelle' ),
					'copy'         => __( 'Copia', 'llm-con-tabelle' ),
					'copied'       => __( 'Copiato', 'llm-con-tabelle' ),
					'close'        => __( 'Chiudi', 'llm-con-tabelle' ),
					'loading'      => __( 'Caricamento…', 'llm-con-tabelle' ),
					'error'        => __( 'Prompt non disponibile.', 'llm-con-tabelle' ),
					'errorInfo'    => __( 'Info non disponibile.', 'llm-con-tabelle' ),
					'errorPhrases' => __( 'Frasi non disponibili.', 'llm-con-tabelle' ),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	private static function current_user_is_admin() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		return $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true );
	}

	public static function ajax_prompt() {
		if ( ! self::current_user_is_admin() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'llm_story_checklist_prompt', 'nonce' );
		$key = isset( $_POST['item_key'] ) ? sanitize_key( wp_unslash( (string) $_POST['item_key'] ) ) : '';
		if ( '' === $key || ! isset( self::prompt_map()[ $key ] ) ) {
			wp_send_json_error( array( 'message' => 'unknown' ), 400 );
		}
		$text = self::load_prompt( $key );
		if ( '' === $text ) {
			wp_send_json_error( array( 'message' => 'empty' ), 404 );
		}
		$file = self::prompt_map()[ $key ];
		wp_send_json_success(
			array(
				'item_key' => $key,
				'file'     => $file,
				'text'     => $text,
			)
		);
	}

	public static function ajax_info() {
		if ( ! self::current_user_is_admin() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'llm_story_checklist_prompt', 'nonce' );
		$key = isset( $_POST['item_key'] ) ? sanitize_key( wp_unslash( (string) $_POST['item_key'] ) ) : '';
		if ( '' === $key || ! isset( self::info_map()[ $key ] ) ) {
			wp_send_json_error( array( 'message' => 'unknown' ), 400 );
		}
		$text = self::load_info( $key );
		if ( '' === $text ) {
			wp_send_json_error( array( 'message' => 'empty' ), 404 );
		}
		$file = self::info_map()[ $key ];
		wp_send_json_success(
			array(
				'item_key' => $key,
				'file'     => $file,
				'text'     => $text,
			)
		);
	}

	public static function ajax_phrases() {
		if ( ! self::current_user_is_admin() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'llm_story_checklist_prompt', 'nonce' );
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'plain';
		if ( ! $story_id || LLM_STORY_CPT !== get_post_type( $story_id ) ) {
			wp_send_json_error( array( 'message' => 'story' ), 400 );
		}
		if ( ! in_array( $mode, array( 'plain', 'alt' ), true ) ) {
			wp_send_json_error( array( 'message' => 'mode' ), 400 );
		}
		$text = 'alt' === $mode ? self::format_phrases_with_alt( $story_id ) : self::format_phrases_plain( $story_id );
		if ( '' === $text ) {
			wp_send_json_error( array( 'message' => 'empty' ), 404 );
		}
		wp_send_json_success(
			array(
				'story_id' => $story_id,
				'mode'     => $mode,
				'text'     => $text,
			)
		);
	}

	/**
	 * @param string $code Lang code.
	 * @return string
	 */
	private static function lang_sigla( $code ) {
		$code = strtolower( sanitize_key( (string) $code ) );
		if ( '' === $code ) {
			return '??';
		}
		return strtoupper( $code );
	}

	/**
	 * @param string $html Rich or plain.
	 * @return string
	 */
	private static function plain_phrase( $html ) {
		$t = trim( wp_strip_all_tags( html_entity_decode( (string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$t = preg_replace( '/[ \t]+/u', ' ', $t );
		$t = preg_replace( '/\s*\n\s*/u', ' ', (string) $t );
		return trim( (string) $t );
	}

	/**
	 * @param int $story_id Story ID.
	 * @return array{0:string,1:string} known/target sigle.
	 */
	private static function story_lang_siglas( $story_id ) {
		$known  = self::lang_sigla( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target = self::lang_sigla( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		return array( $known, $target );
	}

	/**
	 * @param int $story_id Story ID.
	 * @return string
	 */
	public static function format_phrases_plain( $story_id ) {
		$story_id = absint( $story_id );
		$phrases  = class_exists( 'LLM_Story_Repository' ) ? LLM_Story_Repository::get_phrases( $story_id ) : array();
		if ( ! is_array( $phrases ) || ! $phrases ) {
			return '';
		}
		list( $known, $target ) = self::story_lang_siglas( $story_id );
		$blocks = array();
		foreach ( $phrases as $row ) {
			$a = self::plain_phrase( isset( $row['interface'] ) ? $row['interface'] : '' );
			$b = self::plain_phrase( isset( $row['target'] ) ? $row['target'] : '' );
			$blocks[] = $known . ': ' . $a . "\n" . $target . ': ' . $b;
		}
		return implode( "\n\n", $blocks );
	}

	/**
	 * Overlap grezzo di parole (per capire se una stringa è più “vicina” a nota o target).
	 *
	 * @param string $a Text.
	 * @param string $b Text.
	 * @return int
	 */
	private static function word_overlap_score( $a, $b ) {
		$norm = static function ( $s ) {
			$s = strtolower( self::plain_phrase( $s ) );
			$s = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $s );
			$parts = preg_split( '/\s+/u', trim( (string) $s ), -1, PREG_SPLIT_NO_EMPTY );
			return is_array( $parts ) ? array_unique( $parts ) : array();
		};
		$wa = $norm( $a );
		$wb = $norm( $b );
		if ( ! $wa || ! $wb ) {
			return 0;
		}
		return count( array_intersect( $wa, $wb ) );
	}

	/**
	 * @param string $word Lingua in lettere (es. inglese, polacco).
	 * @return string Sigla o ''.
	 */
	private static function lang_word_to_sigla( $word ) {
		$w = strtolower( trim( (string) $word ) );
		$w = strtr(
			$w,
			array(
				'á' => 'a',
				'à' => 'a',
				'é' => 'e',
				'è' => 'e',
				'í' => 'i',
				'ì' => 'i',
				'ó' => 'o',
				'ò' => 'o',
				'ú' => 'u',
				'ù' => 'u',
				'ñ' => 'n',
			)
		);
		$map = array(
			'italiano' => 'IT',
			'italian'  => 'IT',
			'inglese'  => 'EN',
			'english'  => 'EN',
			'spagnolo' => 'ES',
			'espanol'  => 'ES',
			'spanish'  => 'ES',
			'polacco'  => 'PL',
			'polish'   => 'PL',
			'francese' => 'FR',
			'french'   => 'FR',
			'tedesco'  => 'DE',
			'german'   => 'DE',
		);
		return isset( $map[ $w ] ) ? $map[ $w ] : '';
	}

	/**
	 * Estrae (alt nota, alt target).
	 *
	 * @param string $alt_raw       Alt field.
	 * @param string $known_sigla   Es. IT.
	 * @param string $target_sigla  Es. EN.
	 * @param string $interface     Frase ufficiale nota.
	 * @param string $target_phrase Frase ufficiale target.
	 * @return array{0:string,1:string}
	 */
	private static function extract_alt_pair( $alt_raw, $known_sigla = '', $target_sigla = '', $interface = '', $target_phrase = '' ) {
		$plain        = self::plain_phrase( (string) $alt_raw );
		$alt_known    = '';
		$alt_target   = '';
		$known_sigla  = strtoupper( (string) $known_sigla );
		$target_sigla = strtoupper( (string) $target_sigla );

		if ( '' === $plain ) {
			return array( '', '' );
		}

		$assign_pair = static function ( $first, $second ) use ( $interface, $target_phrase ) {
			$first  = trim( (string) $first );
			$second = trim( (string) $second );
			$s1t    = self::word_overlap_score( $first, $target_phrase );
			$s1i    = self::word_overlap_score( $first, $interface );
			$s2t    = self::word_overlap_score( $second, $target_phrase );
			$s2i    = self::word_overlap_score( $second, $interface );
			// Se la prima è più vicina al target ufficiale → prima=target-alt, seconda=nota-alt.
			if ( $s1t > $s1i || $s2i > $s2t ) {
				return array( $second, $first );
			}
			if ( $s1i > $s1t || $s2t > $s2i ) {
				return array( $first, $second );
			}
			// Default: prima=nota, seconda=target (template it→pl).
			return array( $first, $second );
		};

		// Formato: potrebbe essere: "A" che in LINGUA si può tradurre in "B"
		if ( preg_match(
			'/potrebbe essere\s*:\s*[«"“]([^»"”]+)[»"”]\s*che in\s+([^\s]+)\s+si può tradurre in\s*[«"“]([^»"”]+)[»"”]/iu',
			$plain,
			$m
		) ) {
			return $assign_pair( $m[1], $m[3] );
		}

		if ( preg_match( '/(?:could be|mogłoby brzmieć|podría ser)\s*[«"“]([^»"”]+)[»"”]/iu', $plain, $m ) ) {
			$alt_target = trim( $m[1] );
		}
		if ( preg_match( '/(?:In English that is|In italiano è|Po polsku to|En español es)\s*[«"“]([^»"”]+)[»"”]/iu', $plain, $m ) ) {
			$alt_known = trim( $m[1] );
		}
		if ( '' !== $alt_known || '' !== $alt_target ) {
			return array( $alt_known, $alt_target );
		}

		if ( preg_match_all( '/[«"“]([^»"”]+)[»"”]/u', $plain, $mm ) && ! empty( $mm[1] ) && count( $mm[1] ) >= 2 ) {
			return $assign_pair( $mm[1][0], $mm[1][1] );
		}
		if ( ! empty( $mm[1] ) && 1 === count( $mm[1] ) ) {
			return array( '', trim( $mm[1][0] ) );
		}

		if ( strlen( $plain ) <= 220 && false === stripos( $plain, 'traduzione alternativa' ) ) {
			if ( ! preg_match( '/^[:\s.\-–—]+$/u', $plain ) ) {
				return array( '', $plain );
			}
		}

		return array( '', '' );
	}

	/**
	 * Se phrase_alt è vuoto, prova lo slot grammatica "Traduzione alternativa".
	 *
	 * @param array<string,mixed> $row Phrase row.
	 * @return string
	 */
	private static function alt_source_from_row( $row ) {
		$alt = isset( $row['alt'] ) ? trim( (string) $row['alt'] ) : '';
		if ( '' !== self::plain_phrase( $alt ) ) {
			return $alt;
		}
		if ( empty( $row['grammar_slots'] ) || ! is_array( $row['grammar_slots'] ) ) {
			return '';
		}
		foreach ( $row['grammar_slots'] as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$title = isset( $slot['title'] ) ? (string) $slot['title'] : '';
			$body  = isset( $slot['body'] ) ? (string) $slot['body'] : '';
			if ( '' === $title && '' === $body ) {
				continue;
			}
			$hay = strtolower( $title . ' ' . $body );
			if ( false !== strpos( $hay, 'alternativa' ) || false !== strpos( $hay, 'alternative' ) ) {
				$chunk = trim( $title . "\n" . $body );
				if ( '' !== self::plain_phrase( $chunk ) ) {
					return $chunk;
				}
			}
		}
		return '';
	}

	/**
	 * @param int $story_id Story ID.
	 * @return string
	 */
	public static function format_phrases_with_alt( $story_id ) {
		$story_id = absint( $story_id );
		$phrases  = class_exists( 'LLM_Story_Repository' ) ? LLM_Story_Repository::get_phrases( $story_id ) : array();
		if ( ! is_array( $phrases ) || ! $phrases ) {
			return '';
		}
		list( $known, $target ) = self::story_lang_siglas( $story_id );
		$blocks = array();
		foreach ( $phrases as $row ) {
			$a = self::plain_phrase( isset( $row['interface'] ) ? $row['interface'] : '' );
			$b = self::plain_phrase( isset( $row['target'] ) ? $row['target'] : '' );
			list( $alt_known, $alt_target ) = self::extract_alt_pair(
				self::alt_source_from_row( $row ),
				$known,
				$target,
				$a,
				$b
			);
			$blocks[] = $known . ': ' . $a . "\n"
				. $target . ': ' . $b . "\n"
				. $known . '-alternativa: ' . $alt_known . "\n"
				. $target . '-alternativa: ' . $alt_target;
		}
		return implode( "\n\n", $blocks );
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::refresh( (int) $post_id );
	}

	/**
	 * @param int $story_id ID storia.
	 * @return array<string,mixed>
	 */
	public static function refresh( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || LLM_STORY_CPT !== get_post_type( $story_id ) ) {
			return array();
		}
		$prev = self::get_stored( $story_id );
		$data = self::compute( $story_id, $prev );
		update_post_meta( $story_id, self::META_KEY, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
		return $data;
	}

	/**
	 * @param int  $story_id ID.
	 * @param bool $fresh    Se true ricalcola; altrimenti usa meta salvata.
	 * @return array<string,mixed>
	 */
	public static function get( $story_id, $fresh = false ) {
		$story_id = absint( $story_id );
		if ( $fresh ) {
			return self::refresh( $story_id );
		}
		$stored = self::get_stored( $story_id );
		if ( ! empty( $stored['items'] ) && is_array( $stored['items'] ) ) {
			if ( ! isset( $stored['items']['phrases_alt_export'] ) || isset( $stored['items']['alt'] ) || isset( $stored['items']['ipa'] ) || isset( $stored['items']['approx'] ) || self::stored_has_broken_unicode( $stored ) || self::stored_missing_checklist_keys( $stored ) ) {
				return self::refresh( $story_id );
			}
			return $stored;
		}
		return self::refresh( $story_id );
	}

	/**
	 * Snapshot senza una chiave prevista dalle categorie (es. step nuovo aggiunto al plugin).
	 *
	 * @param array<string,mixed> $stored Snapshot.
	 * @return bool
	 */
	private static function stored_missing_checklist_keys( $stored ) {
		if ( empty( $stored['items'] ) || ! is_array( $stored['items'] ) ) {
			return true;
		}
		foreach ( self::categories() as $cat ) {
			$keys = isset( $cat['keys'] ) && is_array( $cat['keys'] ) ? $cat['keys'] : array();
			foreach ( $keys as $k ) {
				if ( ! isset( $stored['items'][ $k ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Meta salvata con escape Unicode rotti (es. u00b7 al posto di ·).
	 *
	 * @param array<string,mixed> $stored Snapshot.
	 * @return bool
	 */
	private static function stored_has_broken_unicode( $stored ) {
		if ( empty( $stored['items'] ) || ! is_array( $stored['items'] ) ) {
			return false;
		}
		foreach ( $stored['items'] as $item ) {
			$detail = isset( $item['detail'] ) ? (string) $item['detail'] : '';
			if ( preg_match( '/\bu[0-9a-f]{4}\b/i', $detail ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $story_id ID.
	 * @return array<string,mixed>
	 */
	private static function get_stored( $story_id ) {
		$raw = get_post_meta( $story_id, self::META_KEY, true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	/**
	 * @param int                 $story_id ID.
	 * @param array<string,mixed> $prev     Snapshot precedente.
	 * @return array<string,mixed>
	 */
	public static function compute( $story_id, $prev = array() ) {
		$story_id = absint( $story_id );
		$phrases  = class_exists( 'LLM_Story_Repository' ) ? LLM_Story_Repository::get_phrases( $story_id ) : array();
		if ( ! is_array( $phrases ) ) {
			$phrases = array();
		}
		$n = count( $phrases );

		$filled = static function ( $key ) use ( $phrases ) {
			$c = 0;
			foreach ( $phrases as $row ) {
				$v = isset( $row[ $key ] ) ? trim( wp_strip_all_tags( (string) $row[ $key ] ) ) : '';
				if ( '' !== $v ) {
					++$c;
				}
			}
			return $c;
		};

		$grammar_filled = 0;
		$grammar_chars  = 0;
		$slots_filled   = 0;
		$am             = 0;
		$af             = 0;
		$azm            = 0;
		$azf            = 0;
		$an             = 0;
		foreach ( $phrases as $row ) {
			$g_html = isset( $row['grammar'] ) ? trim( wp_strip_all_tags( (string) $row['grammar'] ) ) : '';
			$slot_c = 0;
			if ( ! empty( $row['grammar_slots'] ) && is_array( $row['grammar_slots'] ) ) {
				foreach ( $row['grammar_slots'] as $slot ) {
					if ( ! is_array( $slot ) ) {
						continue;
					}
					$t = isset( $slot['title'] ) ? trim( (string) $slot['title'] ) : '';
					$b = isset( $slot['body'] ) ? trim( wp_strip_all_tags( (string) $slot['body'] ) ) : '';
					if ( '' !== $t || '' !== $b ) {
						++$slot_c;
					}
				}
			}
			if ( '' !== $g_html || $slot_c > 0 ) {
				++$grammar_filled;
				$grammar_chars += max( strlen( $g_html ), 1 );
			}
			if ( $slot_c > 0 ) {
				++$slots_filled;
			}
			if ( ! empty( $row['audio_male_id'] ) || ! empty( $row['audio_male_url'] ) ) {
				++$am;
			}
			if ( ! empty( $row['audio_female_id'] ) || ! empty( $row['audio_female_url'] ) ) {
				++$af;
			}
			if ( ! empty( $row['audio_azure_male_id'] ) || ! empty( $row['audio_azure_male_url'] ) ) {
				++$azm;
			}
			if ( ! empty( $row['audio_azure_female_id'] ) || ! empty( $row['audio_azure_female_url'] ) ) {
				++$azf;
			}
			if ( ! empty( $row['audio_notes_female_id'] ) || ! empty( $row['audio_notes_female_url'] ) ) {
				++$an;
			}
		}
		$avg_grammar = $grammar_filled > 0 ? (int) round( $grammar_chars / $grammar_filled ) : 0;

		global $wpdb;
		$listen_phrases = 0;
		$listen_rows    = 0;
		$listen_audio_f = 0;
		$listen_audio_m = 0;
		$listen_table   = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$listen_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS n,
					COUNT(DISTINCT phrase_id) AS phrases,
					SUM(CASE WHEN audio_id > 0 THEN 1 ELSE 0 END) AS af,
					SUM(CASE WHEN audio_male_id > 0 THEN 1 ELSE 0 END) AS am
				 FROM {$listen_table} WHERE story_id = %d",
				$story_id
			),
			ARRAY_A
		);
		if ( is_array( $listen_stats ) ) {
			$listen_rows    = (int) $listen_stats['n'];
			$listen_phrases = (int) $listen_stats['phrases'];
			$listen_audio_f = (int) $listen_stats['af'];
			$listen_audio_m = (int) $listen_stats['am'];
		}

		$media_n   = class_exists( 'LLM_Story_Repository' ) ? count( LLM_Story_Repository::get_media_blocks( $story_id ) ) : 0;
		$has_cover = (bool) get_post_thumbnail_id( $story_id );
		$known     = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target    = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		$plot      = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_PLOT, true ) );
		$intro     = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_INTRO, true ) );
		$finale    = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_FINALE, true ) );
		$card      = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CARD_TEXT, true ) );
		$cefr      = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, true ) );
		$topics    = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_GRAMMAR_TOPICS, true ) );
		$sources   = class_exists( 'LLM_Story_Meta' ) ? LLM_Story_Meta::get_sources( $story_id ) : array();
		$cw_id     = class_exists( 'LLM_Story_Crossword' ) ? (int) LLM_Story_Crossword::get_crossword_id( $story_id ) : 0;
		$post      = get_post( $story_id );
		$status    = $post ? (string) $post->post_status : '';

		$items              = array();
		$items['phrases']   = self::item( 'Frasi caricate', $n, max( 1, $n ), $n > 0 ? sprintf( '%d frasi', $n ) : 'Nessuna frase', $n > 0 ? 'ok' : 'missing' );
		$alt_n              = $filled( 'alt' );
		$items['phrases_alt_export'] = self::item(
			'Frasi + traduzione alternativa',
			$alt_n,
			max( 1, $n ),
			$n > 0 ? sprintf( '%d / %d con alternativa', $alt_n, $n ) : 'Nessuna frase',
			null,
			'optional'
		);
		$items['langs']     = self::item( 'Lingue (nota + obiettivo)', ( '' !== $known && '' !== $target ) ? 1 : 0, 1, ( '' !== $known && '' !== $target ) ? ( $known . ' -> ' . $target ) : 'Mancano le lingue', ( '' !== $known && '' !== $target ) ? 'ok' : 'missing' );
		$items['cover']     = self::item( 'Copertina', $has_cover ? 1 : 0, 1, $has_cover ? 'Impostata' : 'Manca', $has_cover ? 'ok' : 'missing' );
		$items['story_texts'] = self::item(
			'Testi storia (plot/intro/finale/card)',
			(int) ( '' !== $plot ) + (int) ( '' !== $intro ) + (int) ( '' !== $finale ) + (int) ( '' !== $card ),
			4,
			sprintf( 'plot%s intro%s finale%s card%s', '' !== $plot ? '[ok]' : '[no]', '' !== $intro ? '[ok]' : '[no]', '' !== $finale ? '[ok]' : '[no]', '' !== $card ? '[ok]' : '[no]' ),
			null
		);
		$items['cefr_topics'] = self::item(
			'CEFR + topic grammaticali',
			(int) ( '' !== $cefr ) + (int) ( '' !== $topics ),
			2,
			( '' !== $cefr ? $cefr : '-' ) . ( '' !== $topics ? ' | topic ok' : ' | topic mancanti' ),
			null
		);
		$items['sources'] = self::item( 'Fonti', count( $sources ), max( 1, count( $sources ) ), count( $sources ) ? sprintf( '%d fonti', count( $sources ) ) : 'Nessuna', count( $sources ) ? 'ok' : 'missing', 'optional' );
		$items['media']   = self::item( 'Scene / immagini flusso', $media_n, max( 1, $media_n ), $media_n ? sprintf( '%d immagini', $media_n ) : 'Nessuna', $media_n ? 'ok' : 'missing' );
		$poster_id = (int) get_post_meta( $story_id, LLM_Story_Meta::GRAMMAR_INFOGRAPHIC, true );
		$has_poster = $poster_id > 0 && wp_attachment_is_image( $poster_id );
		$items['grammar_infographic'] = self::item( 'Infografica topic', $has_poster ? 1 : 0, 1, $has_poster ? 'Impostata' : 'Nessuna', $has_poster ? 'ok' : 'missing', 'optional' );
		$items['notes']   = self::item( 'Note della frase (lingua nota)', $filled( 'notes' ), max( 1, $n ), sprintf( '%d / %d', $filled( 'notes' ), $n ), null, 'optional' );
		$items['notes_target'] = self::item( 'Note della frase (lingua obiettivo)', $filled( 'notes_target' ), max( 1, $n ), sprintf( '%d / %d', $filled( 'notes_target' ), $n ), null, 'optional' );
		$items['grammar'] = self::item(
			'Appunti grammaticali',
			$grammar_filled,
			max( 1, $n ),
			sprintf( '%d / %d | media %d caratteri | slot %d/%d', $grammar_filled, $n, $avg_grammar, $slots_filled, $n ),
			null
		);
		$items['load_appunti'] = self::item(
			'Caricare gli appunti',
			$slots_filled,
			max( 1, $n ),
			sprintf( '%d / %d frasi con slot (title+body)', $slots_filled, $n ),
			null
		);
		$pron_n    = $filled( 'pronunciation' );
		$ipa_n     = $filled( 'ipa' );
		$approx_n  = $filled( 'approx' );
		$pron_any  = $pron_n + $ipa_n + $approx_n;
		$pron_ok   = $n > 0 && $pron_n >= $n && $ipa_n >= $n && $approx_n >= $n;
		$items['pronunciation'] = self::item(
			'Pronuncia',
			$pron_ok ? $n : 0,
			max( 1, $n ),
			sprintf( 'consigli %d/%d | IPA %d/%d | approssimata %d/%d', $pron_n, $n, $ipa_n, $n, $approx_n, $n ),
			$pron_ok ? 'ok' : ( $pron_any > 0 ? 'partial' : 'missing' )
		);
		$items['remember'] = self::item( 'Ricorda', $filled( 'remember' ), max( 1, $n ), sprintf( '%d / %d', $filled( 'remember' ), $n ), null, 'optional' );
		$items['audio_tts']     = self::item(
			'Audio ascolto traduzione (M+F)',
			min( $am, $af ),
			max( 1, $n ),
			sprintf( 'M %d / %d | F %d / %d', $am, $n, $af, $n ),
			( $n > 0 && $am === $n && $af === $n ) ? 'ok' : ( ( $am > 0 || $af > 0 ) ? 'partial' : 'missing' )
		);
		$items['audio_azure'] = self::item( 'Audio Azure (M+F)', min( $azm, $azf ), max( 1, $n ), sprintf( 'M %d / %d | F %d / %d', $azm, $n, $af, $n ), null );
		$items['audio_notes'] = self::item( 'Audio delle note', $an, max( 1, $n ), sprintf( '%d / %d', $an, $n ), null );
		$items['listen_words'] = self::item(
			'Parole evidenziate (ascolto appunti)',
			$listen_rows,
			max( 1, $listen_rows ),
			sprintf( '%d voci | %d frasi | audio F %d | M %d', $listen_rows, $listen_phrases, $listen_audio_f, $listen_audio_m ),
			$listen_rows > 0 ? ( ( $listen_audio_f > 0 || $listen_audio_m > 0 ) ? 'ok' : 'partial' ) : 'missing'
		);
		$items['crossword'] = self::item( 'Crucintarsio', $cw_id > 0 ? 1 : 0, 1, $cw_id > 0 ? ( 'ID ' . $cw_id ) : 'Non collegato', $cw_id > 0 ? 'ok' : 'missing', 'optional' );
		$items['published'] = self::item( 'Pubblicata', 'publish' === $status ? 1 : 0, 1, $status ? $status : '-', 'publish' === $status ? 'ok' : 'missing', 'optional' );

		$prev_items = isset( $prev['items'] ) && is_array( $prev['items'] ) ? $prev['items'] : array();
		$now        = current_time( 'mysql' );
		foreach ( $items as $key => &$item ) {
			$old_done = isset( $prev_items[ $key ]['done'] ) ? (int) $prev_items[ $key ]['done'] : 0;
			$old_at   = isset( $prev_items[ $key ]['done_at'] ) ? (string) $prev_items[ $key ]['done_at'] : '';
			$done     = (int) $item['done'];
			if ( $done <= 0 ) {
				$item['done_at'] = '';
			} elseif ( $done > $old_done || '' === $old_at ) {
				$item['done_at'] = $now;
			} else {
				$item['done_at'] = $old_at;
			}
		}
		unset( $item );

		$ok    = 0;
		$score = 0;
		foreach ( $items as $item ) {
			if ( 'optional' === $item['status'] ) {
				continue;
			}
			++$score;
			if ( 'ok' === $item['status'] ) {
				++$ok;
			}
		}

		return array(
			'story_id'   => $story_id,
			'updated_at' => $now,
			'total'      => $score,
			'ok'         => $ok,
			'phrase_n'   => $n,
			'items'      => $items,
		);
	}

	/**
	 * @param string      $label  Label.
	 * @param int         $done   Done.
	 * @param int         $total  Total.
	 * @param string      $detail Detail.
	 * @param string|null $status     Status or null.
	 * @param string      $importance required|optional. Se optional, il vuoto non è un errore.
	 * @return array<string,mixed>
	 */
	private static function item( $label, $done, $total, $detail, $status = null, $importance = 'required' ) {
		$done  = max( 0, (int) $done );
		$total = max( 1, (int) $total );
		if ( null === $status ) {
			if ( $done <= 0 ) {
				$status = 'missing';
			} elseif ( $done >= $total ) {
				$status = 'ok';
			} else {
				$status = 'partial';
			}
		}
		if ( 'optional' === $importance && 'missing' === $status ) {
			$status = 'optional';
		}
		return array(
			'label'   => (string) $label,
			'done'    => $done,
			'total'   => $total,
			'detail'  => (string) $detail,
			'status'  => (string) $status,
			'done_at' => '',
		);
	}

	public static function meta_box() {
		add_meta_box(
			'llm_story_checklist',
			__( 'Checklist pubblicazione', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_meta_box' ),
			LLM_STORY_CPT,
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		echo self::render_html( self::get( (int) $post->ID, true ), 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Categorie checklist (ordine di visualizzazione) → chiavi item.
	 *
	 * @return array<string,array{label:string,keys:array<int,string>}>
	 */
	public static function categories() {
		return array(
			'story'  => array(
				'label' => __( 'Info storia', 'llm-con-tabelle' ),
				'keys'  => array(
					'langs',
					'story_texts',
					'cefr_topics',
					'phrases',
					'grammar',
					'load_appunti',
					'pronunciation',
				),
			),
			'images' => array(
				'label' => __( 'Immagini', 'llm-con-tabelle' ),
				'keys'  => array(
					'cover',
					'media',
					'grammar_infographic',
				),
			),
			'audio'  => array(
				'label' => __( 'Audio', 'llm-con-tabelle' ),
				'keys'  => array(
					'audio_tts',
					'audio_azure',
					'audio_notes',
					'listen_words',
				),
			),
			'optional' => array(
				'label' => __( 'Facoltativi', 'llm-con-tabelle' ),
				'keys'  => array(
					'sources',
					'notes',
					'notes_target',
					'phrases_alt_export',
					'remember',
					'crossword',
					'published',
				),
			),
		);
	}

	/**
	 * @param array<string,mixed> $data Snapshot.
	 * @param string              $ctx  admin|front.
	 * @return string
	 */
	public static function render_html( $data, $ctx = 'front' ) {
		if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return '';
		}
		$ok       = isset( $data['ok'] ) ? (int) $data['ok'] : 0;
		$total    = isset( $data['total'] ) ? (int) $data['total'] : count( $data['items'] );
		$upd      = isset( $data['updated_at'] ) ? (string) $data['updated_at'] : '';
		$story_id = isset( $data['story_id'] ) ? (int) $data['story_id'] : 0;
		$class    = 'admin' === $ctx ? 'llm-story-checklist llm-story-checklist--admin' : 'llm-story-checklist llm-story-checklist--front';
		$id       = 'front' === $ctx ? 'llm-story-checklist-panel' : '';
		$map      = self::prompt_map();
		$btn_cls  = 'llm-game-theme__btn llm-story-checklist__action-btn';
		$items    = $data['items'];
		$ordered  = array();
		$seen     = array();
		foreach ( self::categories() as $cat ) {
			$keys = isset( $cat['keys'] ) && is_array( $cat['keys'] ) ? $cat['keys'] : array();
			$row_keys = array();
			foreach ( $keys as $k ) {
				if ( isset( $items[ $k ] ) ) {
					$row_keys[] = $k;
					$seen[ $k ] = true;
				}
			}
			if ( $row_keys ) {
				$ordered[] = array(
					'label' => isset( $cat['label'] ) ? (string) $cat['label'] : '',
					'keys'  => $row_keys,
				);
			}
		}
		$orphan = array();
		foreach ( $items as $k => $_item ) {
			if ( empty( $seen[ $k ] ) ) {
				$orphan[] = $k;
			}
		}
		if ( $orphan ) {
			$ordered[] = array(
				'label' => __( 'Altro', 'llm-con-tabelle' ),
				'keys'  => $orphan,
			);
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $class ); ?>"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> data-story-id="<?php echo esc_attr( (string) $story_id ); ?>">
			<div class="llm-story-checklist__head">
				<strong class="llm-story-checklist__title"><?php echo esc_html__( 'Checklist pubblicazione', 'llm-con-tabelle' ); ?></strong>
				<span class="llm-story-checklist__score"><?php echo esc_html( $ok . ' / ' . $total ); ?></span>
			</div>
			<?php if ( $upd ) : ?>
				<p class="llm-story-checklist__updated"><?php echo esc_html( sprintf( /* translators: %s: datetime */ __( 'Aggiornata: %s', 'llm-con-tabelle' ), $upd ) ); ?></p>
			<?php endif; ?>
			<table class="llm-story-checklist__table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Step', 'llm-con-tabelle' ); ?></th>
						<th><?php echo esc_html__( 'Stato', 'llm-con-tabelle' ); ?></th>
						<th><?php echo esc_html__( 'Dettaglio', 'llm-con-tabelle' ); ?></th>
						<th><?php echo esc_html__( 'Data', 'llm-con-tabelle' ); ?></th>
						<th><?php echo esc_html__( 'Info/Prompt', 'llm-con-tabelle' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $ordered as $group ) : ?>
					<tr class="llm-story-checklist__cat">
						<th colspan="5" scope="colgroup"><?php echo esc_html( $group['label'] ); ?></th>
					</tr>
					<?php foreach ( $group['keys'] as $item_key ) : ?>
						<?php
						$item = $items[ $item_key ];
						$status     = isset( $item['status'] ) ? (string) $item['status'] : 'missing';
						if ( 'optional' === $status ) {
							$icon         = '🟠';
							$status_label = 'Missing';
						} elseif ( 'ok' === $status ) {
							$icon         = '✅';
							$status_label = $status;
						} elseif ( 'partial' === $status ) {
							$icon         = '⚠️';
							$status_label = $status;
						} else {
							$icon         = '❌';
							$status_label = $status;
						}
						$label      = isset( $item['label'] ) ? (string) $item['label'] : '';
						$detail     = isset( $item['detail'] ) ? (string) $item['detail'] : '';
						$at         = isset( $item['done_at'] ) && '' !== $item['done_at'] ? (string) $item['done_at'] : '-';
						$prompt_key = is_string( $item_key ) ? $item_key : '';
						$has_prompt = '' !== $prompt_key && isset( $map[ $prompt_key ] ) && '' !== self::prompt_path_for( $prompt_key );
						$has_info   = '' !== $prompt_key && isset( self::info_map()[ $prompt_key ] ) && '' !== self::info_path_for( $prompt_key );
						$prompt_labels = array(
							'listen_words' => __( 'Prompt per generare elenco parole', 'llm-con-tabelle' ),
						);
						$prompt_label = isset( $prompt_labels[ $prompt_key ] ) ? $prompt_labels[ $prompt_key ] : __( 'Vedi prompt', 'llm-con-tabelle' );
						$prompt_btn_cls = $btn_cls . ( isset( $prompt_labels[ $prompt_key ] ) ? ' llm-story-checklist__action-btn--wide' : '' );
						$phrases_mode = '';
						if ( 'phrases' === $prompt_key && $story_id > 0 ) {
							$phrases_mode = 'plain';
						} elseif ( 'phrases_alt_export' === $prompt_key && $story_id > 0 ) {
							$phrases_mode = 'alt';
						}
						?>
					<tr class="llm-story-checklist__row is-<?php echo esc_attr( $status ); ?>">
						<td class="llm-story-checklist__label"><?php echo esc_html( $label ); ?></td>
						<td class="llm-story-checklist__status"><?php echo esc_html( $icon . ' ' . $status_label ); ?></td>
						<td class="llm-story-checklist__detail"><?php echo esc_html( $detail ); ?></td>
						<td class="llm-story-checklist__date"><?php echo esc_html( $at ); ?></td>
						<td class="llm-story-checklist__prompt">
							<div class="llm-story-checklist__actions">
							<?php if ( $has_info ) : ?>
								<button type="button" class="<?php echo esc_attr( $btn_cls ); ?>" data-llm-checklist-info="<?php echo esc_attr( $prompt_key ); ?>">
									<?php echo esc_html__( 'Info', 'llm-con-tabelle' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( '' !== $phrases_mode ) : ?>
								<button type="button" class="<?php echo esc_attr( $btn_cls ); ?>" data-llm-checklist-phrases="<?php echo esc_attr( $phrases_mode ); ?>" data-story-id="<?php echo esc_attr( (string) $story_id ); ?>">
									<?php echo esc_html( 'plain' === $phrases_mode ? __( 'Vedi frasi…', 'llm-con-tabelle' ) : __( 'Vedi frasi + alt…', 'llm-con-tabelle' ) ); ?>
								</button>
							<?php elseif ( $has_prompt ) : ?>
								<button type="button" class="<?php echo esc_attr( $prompt_btn_cls ); ?>" data-llm-checklist-prompt="<?php echo esc_attr( $prompt_key ); ?>">
									<?php echo esc_html( $prompt_label ); ?>
								</button>
							<?php elseif ( ! $has_info ) : ?>
								<span class="llm-story-checklist__prompt-empty">—</span>
							<?php endif; ?>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
