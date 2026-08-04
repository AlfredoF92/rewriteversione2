<?php
/**
 * Importazione completa storia da file (metadati + frasi).
 *
 * Formato file:
 *   TITOLO#valore
 *   LINGUA_INTERFACCIA#it
 *   ...
 *   ---FRASI---
 *   "Numero posizione";"Frase (lingua interfaccia)";...
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Full_Import {

	const NONCE_ACTION      = 'llm_full_import';
	const TRANSIENT_PREFIX  = 'llm_full_imp_';
	const SECTION_SEPARATOR = '---FRASI---';
	const META_DELIMITER    = '#';

	/** Chiavi meta riconosciute nel file (uppercase). */
	const META_KEYS = array(
		'TITOLO',
		'LINGUA_INTERFACCIA',
		'LINGUA_OBIETTIVO',
		'TITOLO_OBIETTIVO',
		'TRAMA',
		'INTRODUZIONE',
		'FINALE',
		'SCHEDA',
		'CATEGORIA',
		'LIVELLO_CEFR',
		'LIVELLO',
		'TOPIC_GRAMMATICALI',
		'GRAMMATICA',
	);

	/**
	 * Contenuto demo per textarea «Incolla csv» (test importazione).
	 *
	 * @return string
	 */
	public static function get_demo_import_content() {
		self::ensure_category_exists( 'it-polish' );

		$lines = array(
			'TITOLO#Demo: Clara e Luca, appuntamento al ristorante',
			'LINGUA_INTERFACCIA#it',
			'LINGUA_OBIETTIVO#pl',
			'TITOLO_OBIETTIVO#Clara i Luca, pierwsza randka w restauracji',
			'TRAMA#Clara e Luca si incontrano per la prima volta in un ristorante nel centro di Roma. È una serata speciale: luci soffuse, musica leggera e il profumo della cucina italiana.',
			'INTRODUZIONE#Benvenuto in questa storia demo! Imparerai frasi utili per una cena romantica in polacco.',
			'FINALE#Bravissimo! Hai completato la storia demo di Clara e Luca. Ora conosci le basi di una conversazione al ristorante in polacco.',
			'SCHEDA#Storia demo: appuntamento al ristorante — perfetta per imparare il polacco di tutti i giorni.',
			'CATEGORIA#it-polish',
		);

		$lines[] = self::SECTION_SEPARATOR;
		$lines[] = '"Numero posizione";"Frase (lingua interfaccia)";"Frase (lingua obiettivo)";"Analisi grammaticale";"Traduzione alternativa"';
		$lines[] = '"1";"Buongiorno!";"Dzień dobry!";"<p>Saluto del mattino in polacco.</p>";"Buona giornata! → Miłego dnia!"';
		$lines[] = '"2";"Buongiorno tesoro. Come stai?";"Dzień dobry, skarbie. Jak się masz?";"<p>Frase affettuosa per iniziare la conversazione.</p>";"Come va? → Jak leci?"';
		$lines[] = '"3";"Grazie mille!";"Dziękuję bardzo!";"<p>Espressione di gratitudine.</p>";""';

		return implode( "\n", $lines );
	}

	/**
	 * Crea la categoria WordPress se non esiste (solo per demo / import).
	 *
	 * @param string $name Nome categoria.
	 * @return int ID termine o 0.
	 */
	private static function ensure_category_exists( $name ) {
		$name = trim( (string) $name );
		if ( $name === '' ) {
			return 0;
		}

		$term = get_term_by( 'name', $name, 'category' );
		if ( ! $term ) {
			$term = get_term_by( 'slug', sanitize_title( $name ), 'category' );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug' => sanitize_title( $name ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return 0;
		}

		return isset( $result['term_id'] ) ? (int) $result['term_id'] : 0;
	}

	public static function init() {
		add_action( 'wp_ajax_llm_story_full_import_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_llm_story_full_import_commit',  array( __CLASS__, 'ajax_commit' ) );
	}

	/* ── Parsing ─────────────────────────────────────────────────── */

	/**
	 * Divide il file in sezione meta e sezione frasi.
	 *
	 * @param string $path Percorso assoluto al file.
	 * @return array{meta: array<string,string>, phrases_raw: string, warnings: string[]}|\WP_Error
	 */
	public static function parse_full_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'llm_fi_read', __( 'File non leggibile.', 'llm-con-tabelle' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return new \WP_Error( 'llm_fi_open', __( 'Impossibile aprire il file.', 'llm-con-tabelle' ) );
		}

		// Rimuovi BOM UTF-8 se presente.
		if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$raw = substr( $raw, 3 );
		}

		$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		$lines = explode( "\n", $raw );

		$meta         = array();
		$phrases_raw  = '';
		$warnings     = array();
		$in_phrases   = false;
		$current_key  = null;
		$phrases_lines = array();

		foreach ( $lines as $line ) {
			if ( $in_phrases ) {
				$phrases_lines[] = $line;
				continue;
			}

			if ( trim( $line ) === self::SECTION_SEPARATOR ) {
				$in_phrases = true;
				continue;
			}

			// Verifica se la riga inizia con una chiave riconosciuta seguita da #
			$matched_key = null;
			foreach ( self::META_KEYS as $k ) {
				if ( strpos( $line, $k . self::META_DELIMITER ) === 0 ) {
					$matched_key = $k;
					break;
				}
			}

			if ( null !== $matched_key ) {
				$current_key         = $matched_key;
				$meta[ $current_key ] = substr( $line, strlen( $matched_key ) + 1 );
			} elseif ( null !== $current_key && trim( $line ) !== '' ) {
				// Riga di continuazione per valori multi-riga.
				$meta[ $current_key ] .= "\n" . $line;
			}
		}

		if ( ! $in_phrases ) {
			$warnings[] = __( 'Separatore ---FRASI--- non trovato: il file non contiene frasi.', 'llm-con-tabelle' );
		}

		$phrases_raw = implode( "\n", $phrases_lines );

		return array(
			'meta'        => $meta,
			'phrases_raw' => $phrases_raw,
			'warnings'    => $warnings,
		);
	}

	/**
	 * Valida i metadati letti dal file.
	 *
	 * @param array<string,string> $meta
	 * @return array{errors: string[], warnings: string[], categoria_id: int}
	 */
	public static function validate_meta( array $meta ) {
		$errors   = array();
		$warnings = array();
		$cat_id   = 0;

		if ( empty( $meta['TITOLO'] ) || trim( $meta['TITOLO'] ) === '' ) {
			$errors[] = __( 'Campo TITOLO mancante o vuoto.', 'llm-con-tabelle' );
		}

		if ( ! empty( $meta['LINGUA_INTERFACCIA'] ) ) {
			$code = sanitize_key( $meta['LINGUA_INTERFACCIA'] );
			if ( ! LLM_Languages::is_valid( $code ) ) {
				$errors[] = sprintf(
					/* translators: %s: language code */
					__( 'LINGUA_INTERFACCIA "%s" non riconosciuta.', 'llm-con-tabelle' ),
					esc_html( $code )
				);
			}
		} else {
			$warnings[] = __( 'LINGUA_INTERFACCIA non specificata.', 'llm-con-tabelle' );
		}

		if ( ! empty( $meta['LINGUA_OBIETTIVO'] ) ) {
			$code = sanitize_key( $meta['LINGUA_OBIETTIVO'] );
			if ( ! LLM_Languages::is_valid( $code ) ) {
				$errors[] = sprintf(
					/* translators: %s: language code */
					__( 'LINGUA_OBIETTIVO "%s" non riconosciuta.', 'llm-con-tabelle' ),
					esc_html( $code )
				);
			}
		} else {
			$warnings[] = __( 'LINGUA_OBIETTIVO non specificata.', 'llm-con-tabelle' );
		}

		if ( ! empty( $meta['CATEGORIA'] ) ) {
			$cat_name = trim( $meta['CATEGORIA'] );
			$term     = get_term_by( 'name', $cat_name, 'category' );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $cat_name ), 'category' );
			}
			if ( ! $term || is_wp_error( $term ) ) {
				$errors[] = sprintf(
					/* translators: %s: category name */
					__( 'Categoria "%s" non esiste. Creala prima in WordPress → Categorie.', 'llm-con-tabelle' ),
					esc_html( $cat_name )
				);
			} else {
				$cat_id = (int) $term->term_id;
			}
		}

		return array(
			'errors'      => $errors,
			'warnings'    => $warnings,
			'categoria_id' => $cat_id,
		);
	}

	/* ── AJAX preview ─────────────────────────────────────────────── */

	public static function ajax_preview() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'llm-con-tabelle' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if (
			! $post_id ||
			! isset( $_POST['nonce_post'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce_post'] ) ), self::NONCE_ACTION . '_' . $post_id )
		) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'llm-con-tabelle' ) ), 400 );
		}

		if ( ! LLM_Story_Phrases_Csv::user_can_edit_story( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'llm-con-tabelle' ) ), 403 );
		}

		$text_content = isset( $_POST['text_content'] ) ? wp_unslash( (string) $_POST['text_content'] ) : '';
		$has_file     = ! empty( $_FILES['file']['tmp_name'] ) && empty( $_FILES['file']['error'] ) && is_readable( $_FILES['file']['tmp_name'] );

		if ( trim( $text_content ) !== '' ) {
			// Testo incollato: scrivi su file temporaneo e parsa.
			$tmp = wp_tempnam( 'llm-full-import-' );
			if ( ! $tmp ) {
				wp_send_json_error( array( 'message' => __( 'Impossibile preparare il contenuto.', 'llm-con-tabelle' ) ), 500 );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $tmp, $text_content );
			$parsed = self::parse_full_file( $tmp );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp );
		} elseif ( $has_file ) {
			$parsed = self::parse_full_file( $_FILES['file']['tmp_name'] );
		} else {
			wp_send_json_error( array( 'message' => __( 'Carica un file oppure incolla il contenuto.', 'llm-con-tabelle' ) ), 400 );
		}

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 400 );
		}
		$meta         = $parsed['meta'];
		$phrases_raw  = $parsed['phrases_raw'];
		$file_warnings = $parsed['warnings'];

		// Valida metadati.
		$validation  = self::validate_meta( $meta );
		$all_errors  = $validation['errors'];
		$all_warnings = array_merge( $file_warnings, $validation['warnings'] );
		$cat_id      = $validation['categoria_id'];

		if ( ! empty( $all_errors ) ) {
			wp_send_json_error(
				array(
					'message'  => implode( ' ', $all_errors ),
					'errors'   => $all_errors,
					'warnings' => $all_warnings,
				),
				422
			);
		}

		// Parse frasi.
		$phrases_parsed = array();
		$phrases_warnings = array();
		if ( trim( $phrases_raw ) !== '' ) {
			list( $phrases_parsed, $phrases_warnings ) = LLM_Story_Phrases_Csv::parse_csv_string( $phrases_raw );
			if ( is_wp_error( $phrases_parsed ) ) {
				$all_warnings[] = sprintf(
					/* translators: %s: error message */
					__( 'Frasi non importabili: %s', 'llm-con-tabelle' ),
					$phrases_parsed->get_error_message()
				);
				$phrases_parsed = array();
			} else {
				$all_warnings = array_merge( $all_warnings, $phrases_warnings );
			}
		}

		// Merge frasi con quelle esistenti per anteprima.
		$phrases_preview = array();
		if ( ! empty( $phrases_parsed ) && is_array( $phrases_parsed ) ) {
			$current = LLM_Story_Repository::get_phrases( $post_id );
			$merge   = LLM_Story_Phrases_Csv::merge_for_import( $current, $phrases_parsed );
			$phrases_preview = array_slice( $merge['preview'], 0, 5 );
			$all_warnings    = array_merge( $all_warnings, $merge['duplicate_warnings'] );
		}

		// Salva in transient.
		$token = wp_generate_password( 20, false, false );
		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'post_id'         => $post_id,
				'user_id'         => get_current_user_id(),
				'meta'            => $meta,
				'cat_id'          => $cat_id,
				'phrases_parsed'  => is_array( $phrases_parsed ) ? $phrases_parsed : array(),
				'created'         => time(),
			),
			15 * MINUTE_IN_SECONDS
		);

		$preview_payload = self::build_form_payload( $meta, $cat_id, $post_id, is_array( $phrases_parsed ) && ! empty( $phrases_parsed ) ? $phrases_parsed : null );

		wp_send_json_success(
			array(
				'token'           => $token,
				'meta'            => $meta,
				'cat_id'          => $cat_id,
				'phrases_count'   => is_array( $phrases_parsed ) ? count( $phrases_parsed ) : 0,
				'phrases_preview' => $phrases_preview,
				'warnings'        => $all_warnings,
				'form'            => $preview_payload['form'],
				'phrases_merged'  => $preview_payload['phrases'],
			)
		);
	}

	/**
	 * Prepara i dati per il form admin (senza salvare nel database).
	 *
	 * @param array<string,string>     $meta           Metadati parse dal file.
	 * @param int                      $cat_id         ID categoria.
	 * @param int                      $post_id        ID post storia.
	 * @param array<int,array<string,mixed>>|null $phrases_parsed Frasi parse dal CSV.
	 * @return array{form: array<string,mixed>, phrases: array<int,array<string,mixed>>|null}
	 */
	private static function build_form_payload( array $meta, $cat_id, $post_id, $phrases_parsed = null ) {
		$merged_phrases = null;
		if ( ! empty( $phrases_parsed ) && is_array( $phrases_parsed ) ) {
			$current        = LLM_Story_Repository::get_phrases( $post_id );
			$merge          = LLM_Story_Phrases_Csv::merge_for_import( $current, $phrases_parsed );
			$merged_phrases = $merge['merged'];
		}

		$form = array(
			'title'                => ! empty( $meta['TITOLO'] ) ? sanitize_text_field( $meta['TITOLO'] ) : get_the_title( $post_id ),
			'known_lang'           => isset( $meta['LINGUA_INTERFACCIA'] ) ? sanitize_key( $meta['LINGUA_INTERFACCIA'] ) : '',
			'target_lang'          => isset( $meta['LINGUA_OBIETTIVO'] ) ? sanitize_key( $meta['LINGUA_OBIETTIVO'] ) : '',
			'title_target'         => isset( $meta['TITOLO_OBIETTIVO'] ) ? LLM_Story_Meta::sanitize_plot( $meta['TITOLO_OBIETTIVO'] ) : '',
			'story_plot'           => isset( $meta['TRAMA'] ) ? LLM_Story_Meta::sanitize_plot( $meta['TRAMA'] ) : '',
			'story_intro'          => isset( $meta['INTRODUZIONE'] ) ? LLM_Story_Meta::sanitize_plot( $meta['INTRODUZIONE'] ) : '',
			'story_finale'         => isset( $meta['FINALE'] ) ? LLM_Story_Meta::sanitize_plot( $meta['FINALE'] ) : '',
			'story_card_text'      => isset( $meta['SCHEDA'] ) ? LLM_Story_Meta::sanitize_plot( $meta['SCHEDA'] ) : '',
			'category_id'          => (int) $cat_id,
			'category_name'        => isset( $meta['CATEGORIA'] ) ? sanitize_text_field( $meta['CATEGORIA'] ) : '',
			'story_cefr_level'     => isset( $meta['LIVELLO'] ) ? sanitize_text_field( $meta['LIVELLO'] ) : ( isset( $meta['LIVELLO_CEFR'] ) ? sanitize_text_field( $meta['LIVELLO_CEFR'] ) : '' ),
			'story_grammar_topics' => isset( $meta['GRAMMATICA'] ) ? LLM_Story_Meta::sanitize_plot( $meta['GRAMMATICA'] ) : ( isset( $meta['TOPIC_GRAMMATICALI'] ) ? LLM_Story_Meta::sanitize_plot( $meta['TOPIC_GRAMMATICALI'] ) : '' ),
		);

		return array(
			'form'    => $form,
			'phrases' => is_array( $merged_phrases ) ? $merged_phrases : null,
		);
	}

	/* ── AJAX commit ─────────────────────────────────────────────── */

	public static function ajax_commit() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'llm-con-tabelle' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		if (
			! $post_id || $token === '' ||
			! isset( $_POST['nonce_post'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce_post'] ) ), self::NONCE_ACTION . '_' . $post_id )
		) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'llm-con-tabelle' ) ), 400 );
		}

		if ( ! LLM_Story_Phrases_Csv::user_can_edit_story( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'llm-con-tabelle' ) ), 403 );
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! is_array( $data ) || (int) $data['post_id'] !== $post_id || (int) $data['user_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Anteprima scaduta o non valida. Ricarica il file.', 'llm-con-tabelle' ) ), 400 );
		}

		delete_transient( self::TRANSIENT_PREFIX . $token );

		$meta           = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
		$cat_id         = isset( $data['cat_id'] ) ? (int) $data['cat_id'] : 0;
		$phrases_parsed = isset( $data['phrases_parsed'] ) && is_array( $data['phrases_parsed'] ) ? $data['phrases_parsed'] : array();

		$payload = self::build_form_payload( $meta, $cat_id, $post_id, $phrases_parsed );
		$form    = $payload['form'];
		$merged_phrases = $payload['phrases'];

		$log   = array();
		$log[] = __( 'Caricamento dati nel form…', 'llm-con-tabelle' );

		if ( ! empty( $form['title'] ) ) {
			$log[] = sprintf(
				/* translators: %s: post title */
				__( 'Titolo caricato nel form: "%s".', 'llm-con-tabelle' ),
				$form['title']
			);
		}

		if ( $form['known_lang'] !== '' || $form['target_lang'] !== '' ) {
			$log[] = __( 'Lingue e metadati caricati nel form.', 'llm-con-tabelle' );
		}

		if ( $cat_id > 0 ) {
			$log[] = sprintf(
				/* translators: %s: category name */
				__( 'Categoria selezionata nel form: %s.', 'llm-con-tabelle' ),
				$form['category_name'] !== '' ? $form['category_name'] : (string) $cat_id
			);
		}

		if ( is_array( $merged_phrases ) && ! empty( $merged_phrases ) ) {
			$log[] = sprintf(
				/* translators: %d: number of phrases */
				__( '%d frasi caricate nel form.', 'llm-con-tabelle' ),
				count( $merged_phrases )
			);
		} else {
			$log[] = __( 'Nessuna frase nel file: frasi del form non modificate.', 'llm-con-tabelle' );
		}

		$log[] = __( 'Fatto. Clicca «Salva bozza» o «Pubblica» per salvare definitivamente.', 'llm-con-tabelle' );

		wp_send_json_success(
			array(
				'log'     => $log,
				'form'    => $form,
				'phrases' => $merged_phrases,
			)
		);
	}
}
