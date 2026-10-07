<?php
/**
 * Cruciverba legato a una storia: banca definizioni, CPT, box in Modifica storia.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Crossword {

	const META_ID   = '_llm_story_crossword_id';
	const META_BANK = '_llm_story_crossword_bank';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * @param int $story_id ID storia.
	 * @return int ID cruciverba, 0 se manca.
	 */
	public static function get_crossword_id( $story_id ) {
		return absint( get_post_meta( absint( $story_id ), self::META_ID, true ) );
	}

	/**
	 * @param int $story_id ID storia.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_bank( $story_id ) {
		$raw = get_post_meta( absint( $story_id ), self::META_BANK, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
			$raw     = is_array( $decoded ) ? $decoded : maybe_unserialize( $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			$item = self::normalize_bank_item( $row );
			if ( $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * @param int   $story_id ID storia.
	 * @param array $bank     Banca.
	 */
	public static function save_bank( $story_id, array $bank ) {
		$story_id = absint( $story_id );
		$clean    = array();
		foreach ( $bank as $row ) {
			$item = self::normalize_bank_item( $row );
			if ( $item ) {
				$clean[] = $item;
			}
		}
		update_post_meta( $story_id, self::META_BANK, $clean );
	}

	/**
	 * @param mixed $row Riga grezza.
	 * @return array<string,mixed>|null
	 */
	public static function normalize_bank_item( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$word = LLM_Crucintarsio_Generator::normalize_word( isset( $row['word'] ) ? $row['word'] : '' );
		if ( strlen( $word ) < 2 ) {
			return null;
		}
		$kind = isset( $row['kind'] ) ? sanitize_key( (string) $row['kind'] ) : 'notes';
		if ( ! in_array( $kind, self::allowed_kinds(), true ) ) {
			$kind = 'notes';
		}
		return array(
			'word'       => $word,
			'category'   => sanitize_text_field( isset( $row['category'] ) ? (string) $row['category'] : '' ),
			'kind'       => $kind,
			'phrase_n'   => absint( isset( $row['phrase_n'] ) ? $row['phrase_n'] : 0 ),
			'def_known'  => self::clean_def( isset( $row['def_known'] ) ? $row['def_known'] : '' ),
			'def_target' => self::clean_def( isset( $row['def_target'] ) ? $row['def_target'] : '' ),
			'placed'     => ! empty( $row['placed'] ),
		);
	}

	/**
	 * @return string[]
	 */
	public static function allowed_kinds() {
		return array(
			'notes',
			'grammar',
			'curiosity',
			'pronunciation',
			'translation',
			'conjugation',
			'etymology',
		);
	}

	/**
	 * @param string $text     Definizione.
	 * @param int    $phrase_n Numero frase (1-based).
	 * @return string
	 */
	private static function with_solution_hint( $text, $phrase_n ) {
		unset( $phrase_n );
		$split = LLM_Crossword::split_solution_hint( (string) $text );
		return $split['text'];
	}

	/**
	 * @param mixed $value Testo definizione.
	 * @return string
	 */
	private static function clean_def( $value ) {
		$value = str_replace( array( "\r", "\n" ), ' ', (string) $value );
		$value = str_replace( '|', '/', $value );
		$value = trim( wp_strip_all_tags( $value ) );
		$split = LLM_Crossword::split_solution_hint( $value );
		return $split['text'];
	}

	/**
	 * Contesto storia per generare le definizioni (CLI dump).
	 *
	 * @param int $story_id ID storia.
	 * @param int $count    Quante parole chiedere.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function dump_story_context( $story_id, $count ) {
		$story_id = absint( $story_id );
		$post     = $story_id ? get_post( $story_id ) : null;
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return new WP_Error( 'llm_ci_no_story', 'Storia non trovata: #' . $story_id );
		}

		$known  = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		$phrases = class_exists( 'LLM_Story_Repository' ) ? LLM_Story_Repository::get_phrases( $story_id ) : array();

		$rows = array();
		foreach ( $phrases as $i => $p ) {
			$rows[] = array(
				'n'             => $i + 1,
				'interface'     => isset( $p['interface'] ) ? wp_strip_all_tags( (string) $p['interface'] ) : '',
				'target'        => isset( $p['target'] ) ? wp_strip_all_tags( (string) $p['target'] ) : '',
				'notes'         => isset( $p['notes'] ) ? wp_strip_all_tags( (string) $p['notes'] ) : '',
				'grammar'       => isset( $p['grammar'] ) ? wp_strip_all_tags( (string) $p['grammar'] ) : '',
				'alt'           => isset( $p['alt'] ) ? wp_strip_all_tags( (string) $p['alt'] ) : '',
				'remember'      => isset( $p['remember'] ) ? wp_strip_all_tags( (string) $p['remember'] ) : '',
				'pronunciation' => isset( $p['pronunciation'] ) ? wp_strip_all_tags( (string) $p['pronunciation'] ) : '',
				'ipa'           => isset( $p['ipa'] ) ? wp_strip_all_tags( (string) $p['ipa'] ) : '',
				'approx'        => isset( $p['approx'] ) ? wp_strip_all_tags( (string) $p['approx'] ) : '',
			);
		}

		return array(
			'story_id'         => $story_id,
			'title'            => (string) $post->post_title,
			'title_target'     => (string) get_post_meta( $story_id, LLM_Story_Meta::TITLE_TARGET, true ),
			'known'            => $known,
			'target'           => $target,
			'lang_line'        => LLM_Crossword::format_lang_line( $known, $target ),
			'requested_count'  => max( 1, absint( $count ) ),
			'plot'             => (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_PLOT, true ),
			'intro'            => (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_INTRO, true ),
			'finale'           => (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_FINALE, true ),
			'grammar_topics'   => (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_GRAMMAR_TOPICS, true ),
			'cefr'             => (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, true ),
			'phrases'          => $rows,
			'instructions'     => array(
				'words_language' => 'Le PAROLE della griglia sono nella lingua da imparare (target). Solo token che compaiono in phrase_target, mai da note/ricorda/grammatica. Solo A-Z, niente spazi né accenti. Minimo 2 lettere.',
				'def_sources'    => 'Le definizioni usano solo i consigli sulla traduzione: grammar, alt e remember. Non usare notes (note di storia) né pronunciation/ipa/approx.',
				'def_known'      => 'Definizione breve e concisa nella lingua nota, che richiami traduzione o Ricorda. Non paragrafi. NON scrivere Soluzione/Frase n. nel testo. La parola soluzione non deve comparire nel testo.',
				'def_target'     => 'Stessa idea, breve, nella lingua da imparare. NON scrivere Soluzione/Frase n. nel testo. La parola soluzione non deve comparire nel testo.',
				'no_solution_in_def' => 'La soluzione (campo word) non deve stare nella definizione: non scriverla in def_known né in def_target, in nessuna lingua.',
				'kinds'          => array( 'grammar', 'curiosity', 'translation', 'conjugation', 'etymology' ),
				'kind_labels'    => array(
					'grammar'       => 'Grammatica dai consigli sulla traduzione',
					'curiosity'     => 'Curiosità presa da grammar o remember (non dalle note di storia)',
					'translation'   => 'Traduzione: nella lingua XXX si traduce XXX',
					'conjugation'   => 'Coniugazione grammaticale (coniugazione del verbo, ecc.)',
					'etymology'     => 'Etimologia solo se è già in grammar o remember',
				),
				'forbidden_kinds' => 'Non usare kind notes (note di storia) né pronunciation.',
				'phrase_n'       => 'Ogni voce ha phrase_n (1-based). Copri TUTTE le frasi: da ogni frase almeno 1 definizione. Il gioco mostra una sola volta, in piccolo: Soluzione Frase n. XX. Non ripeterlo in def_known né in def_target.',
				'mix'            => 'Lunghezze griglia: minimo 10 parole di 2 o 3 lettere, minimo 10 parole di 4 lettere; il resto misto fino a 12. Definizioni corte. Non inventare fatti assenti da grammar/alt/remember.',
			),
		);
	}

	/**
	 * Salva la banca definizioni da JSON apply.
	 *
	 * @param int   $story_id ID storia.
	 * @param array $words    Elenco parole.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public static function apply_definitions( $story_id, array $words ) {
		$story_id = absint( $story_id );
		$post     = $story_id ? get_post( $story_id ) : null;
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return new WP_Error( 'llm_ci_no_story', 'Storia non trovata: #' . $story_id );
		}

		$bank = array();
		foreach ( $words as $row ) {
			if ( is_array( $row ) ) {
				$row['placed'] = false;
			}
			$item = self::normalize_bank_item( $row );
			if ( $item ) {
				$bank[] = $item;
			}
		}
		if ( count( $bank ) < 2 ) {
			return new WP_Error( 'llm_ci_few_defs', 'Servono almeno due definizioni valide.' );
		}

		self::save_bank( $story_id, $bank );
		return $bank;
	}

	/**
	 * Genera lo schema, aggiorna Scelte/Scartate e crea/aggiorna il CPT cruciverba.
	 *
	 * @param int $story_id  ID storia.
	 * @param int $intrecci  Obiettivo parole incastrate.
	 * @param int $variants  Varianti da provare.
	 * @param int $size      Lato griglia.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function generate_for_story( $story_id, $intrecci, $variants, $size = 15 ) {
		$story_id = absint( $story_id );
		$post     = $story_id ? get_post( $story_id ) : null;
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return new WP_Error( 'llm_ci_no_story', 'Storia non trovata: #' . $story_id );
		}

		$bank = self::get_bank( $story_id );
		if ( count( $bank ) < 2 ) {
			return new WP_Error( 'llm_ci_no_bank', 'Prima lancia script-crucintarsio-crea-definizioni per questa storia.' );
		}

		$words = array();
		foreach ( $bank as $item ) {
			$words[] = $item['word'];
		}

		$best = LLM_Crucintarsio_Generator::build_best( $words, $size, $variants, $intrecci );
		if ( is_wp_error( $best ) ) {
			return $best;
		}

		$placed_map = array();
		foreach ( $best['placed'] as $w ) {
			$placed_map[ $w ] = true;
		}

		foreach ( $bank as $i => $item ) {
			$bank[ $i ]['placed'] = isset( $placed_map[ $item['word'] ] );
		}
		self::save_bank( $story_id, $bank );

		$parsed = LLM_Crossword::parse_grid( $best['csv'] );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		$entries = LLM_Crossword::entries( $parsed['grid'] );
		$defs    = self::defs_for_entries( $entries, $bank );

		$known  = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		$cw_id  = self::upsert_crossword( $story_id, $post->post_title, $known, $target, $best['csv'], $defs );

		return array(
			'story_id'      => $story_id,
			'crossword_id'  => $cw_id,
			'shortcode'     => '[llm_crossword id="' . (int) $cw_id . '"]',
			'placed'        => $best['placed'],
			'unplaced'      => $best['unplaced'],
			'placed_count'  => $best['placed_count'],
			'intrecci'      => absint( $intrecci ),
			'variants'      => absint( $variants ),
			'size'          => absint( $size ),
			'rows'          => $best['rows'],
			'cols'          => $best['cols'],
		);
	}

	/**
	 * @param array $entries Parole dallo schema.
	 * @param array $bank    Banca.
	 * @return string Testo definizioni |.
	 */
	private static function defs_for_entries( array $entries, array $bank ) {
		$queues = array();
		foreach ( $bank as $item ) {
			if ( empty( $item['placed'] ) ) {
				continue;
			}
			$w = $item['word'];
			if ( ! isset( $queues[ $w ] ) ) {
				$queues[ $w ] = array();
			}
			$queues[ $w ][] = $item;
		}

		$clues = array();
		foreach ( $entries as $entry ) {
			$key  = LLM_Crossword::clue_key( $entry['number'], $entry['direction'] );
			$word = strtoupper( (string) $entry['word'] );
			$item = array(
				'category'   => '',
				'def_known'  => '',
				'def_target' => '',
			);
			if ( ! empty( $queues[ $word ] ) ) {
				$item = array_shift( $queues[ $word ] );
			}
			$phrase_n      = isset( $item['phrase_n'] ) ? absint( $item['phrase_n'] ) : 0;
			$clues[ $key ] = array(
				'pos' => isset( $item['category'] ) ? (string) $item['category'] : '',
				'en'  => self::with_solution_hint( isset( $item['def_known'] ) ? $item['def_known'] : '', $phrase_n ),
				'it'  => self::with_solution_hint( isset( $item['def_target'] ) ? $item['def_target'] : '', $phrase_n ),
			);
		}

		return LLM_Crossword::definitions_skeleton( $entries, self::clues_to_raw( $entries, $clues ) );
	}

	/**
	 * @param array $entries Parole.
	 * @param array $clues   Mappa clue_key => pos/en/it.
	 * @return string
	 */
	private static function clues_to_raw( array $entries, array $clues ) {
		$lines = array();
		foreach ( $entries as $entry ) {
			$key  = LLM_Crossword::clue_key( $entry['number'], $entry['direction'] );
			$clue = isset( $clues[ $key ] ) ? $clues[ $key ] : array(
				'pos' => '',
				'en'  => '',
				'it'  => '',
			);
			$lines[] = implode(
				'|',
				array(
					(string) $entry['number'],
					LLM_Crossword::direction_letter( $entry['direction'] ),
					$entry['word'],
					$clue['pos'],
					$clue['en'],
					$clue['it'],
				)
			);
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param int    $story_id ID storia.
	 * @param string $title    Titolo storia.
	 * @param string $known    Lingua nota.
	 * @param string $target   Lingua obiettivo.
	 * @param string $csv      Schema.
	 * @param string $defs     Definizioni.
	 * @return int ID cruciverba.
	 */
	private static function upsert_crossword( $story_id, $title, $known, $target, $csv, $defs ) {
		$cw_id = self::get_crossword_id( $story_id );
		$cw    = $cw_id ? get_post( $cw_id ) : null;
		if ( ! $cw || LLM_Crossword::CPT !== $cw->post_type ) {
			$cw_id = wp_insert_post(
				array(
					'post_type'   => LLM_Crossword::CPT,
					'post_status' => 'publish',
					'post_title'  => sprintf( 'Cruciverba: %s', $title ),
				),
				true
			);
			if ( is_wp_error( $cw_id ) ) {
				return 0;
			}
			$cw_id = (int) $cw_id;
			update_post_meta( $story_id, self::META_ID, $cw_id );
		} else {
			wp_update_post(
				array(
					'ID'         => $cw_id,
					'post_title' => sprintf( 'Cruciverba: %s', $title ),
				)
			);
		}

		$lang_line = ( $known && $target ) ? LLM_Crossword::format_lang_line( $known, $target ) : '';
		update_post_meta( $cw_id, LLM_Crossword::META_KNOWN, $known );
		update_post_meta( $cw_id, LLM_Crossword::META_TARGET, $target );
		update_post_meta( $cw_id, LLM_Crossword::META_LANG, $lang_line );
		update_post_meta( $cw_id, LLM_Crossword::META_CSV, $csv );
		update_post_meta( $cw_id, LLM_Crossword::META_DEFS, $defs );

		return (int) $cw_id;
	}

	public static function enqueue( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || LLM_STORY_CPT !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( class_exists( 'LLM_Crossword_Admin' ) ) {
			wp_enqueue_style( 'llm-ui' );
			wp_enqueue_style(
				'llm-crossword',
				LLM_TABELLE_URL . 'assets/llm-crossword.css',
				array( 'llm-ui' ),
				LLM_TABELLE_VERSION
			);
			wp_enqueue_style(
				'llm-crossword-admin',
				LLM_TABELLE_URL . 'assets/llm-crossword-admin.css',
				array( 'llm-crossword' ),
				LLM_TABELLE_VERSION
			);
		}
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_story_crossword',
			__( 'Cruciverba di questa storia', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_box' ),
			LLM_STORY_CPT,
			'normal',
			'low'
		);
	}

	/**
	 * @param WP_Post $post Storia.
	 */
	public static function render_box( $post ) {
		$story_id = (int) $post->ID;
		$bank     = self::get_bank( $story_id );
		$cw_id    = self::get_crossword_id( $story_id );
		$scelte   = array();
		$scartate = array();
		foreach ( $bank as $item ) {
			if ( ! empty( $item['placed'] ) ) {
				$scelte[] = $item;
			} else {
				$scartate[] = $item;
			}
		}

		$kind_labels = array(
			'notes'         => __( 'Note di storia', 'llm-con-tabelle' ),
			'grammar'       => __( 'Grammatica', 'llm-con-tabelle' ),
			'curiosity'     => __( 'Curiosità', 'llm-con-tabelle' ),
			'pronunciation' => __( 'Pronuncia', 'llm-con-tabelle' ),
			'translation'   => __( 'Traduzione', 'llm-con-tabelle' ),
			'conjugation'   => __( 'Coniugazione grammaticale', 'llm-con-tabelle' ),
			'etymology'     => __( 'Etimologia', 'llm-con-tabelle' ),
		);

		echo '<div class="llm-story-cw">';

		if ( empty( $bank ) ) {
			echo '<p class="description">' . esc_html__( 'Ancora nessuna definizione. Chiedi in chat: lancia script-crucintarsio-crea-definizioni per questa storia, con quante parole vuoi.', 'llm-con-tabelle' ) . '</p>';
			echo '</div>';
			return;
		}

		if ( $cw_id ) {
			$shortcode = '[llm_crossword id="' . (int) $cw_id . '"]';
			echo '<p><label for="llm-story-cw-shortcode"><strong>' . esc_html__( 'Shortcode da copiare', 'llm-con-tabelle' ) . '</strong></label></p>';
			echo '<p><input type="text" class="large-text code" id="llm-story-cw-shortcode" readonly value="' . esc_attr( $shortcode ) . '" onclick="this.select();" /></p>';
			$edit = get_edit_post_link( $cw_id, 'raw' );
			if ( $edit ) {
				echo '<p><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Apri la scheda Cruciverba', 'llm-con-tabelle' ) . '</a></p>';
			}
			if ( method_exists( 'LLM_Crossword_Admin', 'preview_for_id' ) ) {
				$html = LLM_Crossword_Admin::preview_for_id( $cw_id );
				if ( $html ) {
					echo '<div class="llm-story-cw__preview">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già escapato dall'admin cruciverba.
				}
			}
		} else {
			echo '<p class="description">' . esc_html__( 'Definizioni pronte. Manca lo schema: lancia script-genera-crucintarsio (storia, numero di intrecci, numero di varianti).', 'llm-con-tabelle' ) . '</p>';
		}

		echo '<div class="llm-story-cw__lists">';
		self::render_word_list( __( 'Scelte', 'llm-con-tabelle' ), $scelte, $kind_labels );
		self::render_word_list( __( 'Scartate', 'llm-con-tabelle' ), $scartate, $kind_labels );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * @param string $title       Titolo elenco.
	 * @param array  $items       Parole.
	 * @param array  $kind_labels Etichette kind.
	 */
	private static function render_word_list( $title, array $items, array $kind_labels ) {
		echo '<div class="llm-story-cw__list">';
		echo '<h3>' . esc_html( $title ) . ' <span>(' . count( $items ) . ')</span></h3>';
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html__( 'Nessuna.', 'llm-con-tabelle' ) . '</p>';
			echo '</div>';
			return;
		}
		echo '<ol>';
		foreach ( $items as $item ) {
			$kind = isset( $kind_labels[ $item['kind'] ] ) ? $kind_labels[ $item['kind'] ] : $item['kind'];
			echo '<li>';
			echo '<strong>' . esc_html( $item['word'] ) . '</strong>';
			if ( $item['category'] ) {
				echo ' · ' . esc_html( $item['category'] );
			}
			echo ' · <em>' . esc_html( $kind ) . '</em>';
			if ( ! empty( $item['phrase_n'] ) ) {
				echo ' · ' . esc_html( sprintf( __( 'Frase n. %d', 'llm-con-tabelle' ), absint( $item['phrase_n'] ) ) );
			}
			if ( $item['def_known'] ) {
				echo '<br>' . esc_html( $item['def_known'] );
			}
			if ( $item['def_target'] ) {
				echo '<br><em>(' . esc_html( $item['def_target'] ) . ')</em>';
			}
			echo '</li>';
		}
		echo '</ol>';
		echo '</div>';
	}
}
