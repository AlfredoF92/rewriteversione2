<?php
/**
 * Esportazione / importazione frasi storia in CSV (admin).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Phrases_Csv {

	const NONCE_ACTION = 'llm_phrases_csv';
	const TRANSIENT_PREFIX = 'llm_phr_csv_';

	/** Delimitatore campo CSV (solo punto e virgola). */
	const CSV_DELIMITER = ';';

	/**
	 * Intestazioni esatte del CSV esportato (ordine colonne).
	 *
	 * @return string[]
	 */
	public static function export_headers() {
		return array(
			'Numero posizione',
			'Frase (lingua interfaccia)',
			'Frase (lingua obiettivo)',
			'Analisi grammaticale',
			'Traduzione alternativa',
		);
	}

	public static function init() {
		add_action( 'admin_post_llm_export_story_phrases', array( __CLASS__, 'handle_export_download' ) );
		add_action( 'wp_ajax_llm_story_phrases_preview_import', array( __CLASS__, 'ajax_preview_import' ) );
		add_action( 'wp_ajax_llm_story_phrases_commit_import', array( __CLASS__, 'ajax_commit_import' ) );
	}

	/**
	 * @param int $post_id ID storia.
	 * @return string URL download CSV.
	 */
	public static function export_url( $post_id ) {
		$post_id = absint( $post_id );
		return add_query_arg(
			array(
				'action'   => 'llm_export_story_phrases',
				'post_id'  => $post_id,
				'_wpnonce' => wp_create_nonce( 'llm_export_phrases_' . $post_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * @param int $post_id ID storia.
	 * @return string
	 */
	public static function ajax_nonce( $post_id ) {
		return wp_create_nonce( self::NONCE_ACTION . '_' . absint( $post_id ) );
	}

	/**
	 * @param int $post_id ID storia.
	 * @return bool
	 */
	public static function user_can_edit_story( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	public static function handle_export_download() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Accesso negato.', 'llm-con-tabelle' ) );
		}
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id || ! self::user_can_edit_story( $post_id ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'llm-con-tabelle' ) );
		}
		check_admin_referer( 'llm_export_phrases_' . $post_id );

		$phrases = LLM_Story_Repository::get_phrases( $post_id );
		$slug    = sanitize_file_name( get_post_field( 'post_name', $post_id ) );
		if ( $slug === '' ) {
			$slug = 'story-' . $post_id;
		}
		$filename = 'frasi-' . $slug . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			wp_die( esc_html__( 'Impossibile generare il file.', 'llm-con-tabelle' ) );
		}

		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $out, self::export_headers(), self::CSV_DELIMITER );

		$i = 0;
		foreach ( $phrases as $row ) {
			++$i;
			fputcsv(
				$out,
				array(
					(string) $i,
					isset( $row['interface'] ) ? (string) $row['interface'] : '',
					isset( $row['target'] ) ? (string) $row['target'] : '',
					isset( $row['grammar'] ) ? (string) $row['grammar'] : '',
					isset( $row['alt'] ) ? (string) $row['alt'] : '',
				),
				self::CSV_DELIMITER
			);
		}

		fclose( $out );
		exit;
	}

	/**
	 * Legge CSV da percorso file temporaneo.
	 *
	 * @param string $path Percorso assoluto.
	 * @return array{0:\WP_Error|array<int,array<string,mixed>>,1:string[]} [ rows, warnings ]
	 */
	public static function parse_csv_file( $path ) {
		$warnings = array();
		if ( ! is_readable( $path ) ) {
			return array( new \WP_Error( 'llm_csv_read', __( 'File non leggibile.', 'llm-con-tabelle' ) ), $warnings );
		}

		$h = fopen( $path, 'rb' );
		if ( ! $h ) {
			return array( new \WP_Error( 'llm_csv_open', __( 'Impossibile aprire il file.', 'llm-con-tabelle' ) ), $warnings );
		}

		$head3 = fread( $h, 3 );
		if ( $head3 !== "\xEF\xBB\xBF" ) {
			if ( strlen( $head3 ) > 0 ) {
				rewind( $h );
			}
		}

		$rows_out   = array();
		$header_map = null;
		$row_num    = 0;

		while ( ( $cells = fgetcsv( $h, 0, self::CSV_DELIMITER, '"' ) ) !== false ) {
			if ( self::row_is_empty( $cells ) ) {
				continue;
			}
			if ( null === $header_map ) {
				$analyzed = self::analyze_first_csv_row( $cells );
				if ( null === $analyzed['map'] ) {
					fclose( $h );
					return array( new \WP_Error( 'llm_csv_header', __( 'Intestazioni colonne non riconosciute. Usa il modello dall’esportazione.', 'llm-con-tabelle' ) ), $warnings );
				}
				$header_map = $analyzed['map'];
				if ( ! $analyzed['first_row_is_header'] ) {
					++$row_num;
					$row = self::csv_row_to_phrase_row( $cells, $header_map, $row_num, $warnings );
					if ( is_array( $row ) ) {
						$rows_out[] = $row;
					}
				}
				continue;
			}

			++$row_num;
			$pos = isset( $cells[ $header_map['pos'] ] ) ? self::parse_position_cell( $cells[ $header_map['pos'] ] ) : 0;
			$row = self::csv_row_to_phrase_row( $cells, $header_map, $row_num, $warnings );
			if ( is_array( $row ) ) {
				$rows_out[] = $row;
			}
		}
		fclose( $h );

		if ( empty( $rows_out ) ) {
			return array( new \WP_Error( 'llm_csv_nodata', __( 'Nessuna riga dati nel CSV.', 'llm-con-tabelle' ) ), $warnings );
		}

		return array( $rows_out, $warnings );
	}

	/**
	 * Parse da stringa (incolla) — stesso formato del file CSV (delimitatore `;`).
	 *
	 * @param string $raw Contenuto CSV.
	 * @return array{0:\WP_Error|array<int,array<string,mixed>>,1:string[]}
	 */
	public static function parse_csv_string( $raw ) {
		$warnings = array();
		$raw      = is_string( $raw ) ? str_replace( array( "\r\n", "\r" ), "\n", $raw ) : '';
		if ( trim( $raw ) === '' ) {
			return array( new \WP_Error( 'llm_csv_empty', __( 'Testo vuoto.', 'llm-con-tabelle' ) ), $warnings );
		}

		$max = 3 * 1024 * 1024;
		if ( strlen( $raw ) > $max ) {
			return array( new \WP_Error( 'llm_csv_too_large', __( 'Il testo supera la dimensione massima consentita (3 MB).', 'llm-con-tabelle' ) ), $warnings );
		}

		$tmp = wp_tempnam( 'llm-phrases-csv-' );
		if ( ! $tmp ) {
			return array( new \WP_Error( 'llm_csv_temp', __( 'Impossibile preparare il contenuto.', 'llm-con-tabelle' ) ), $warnings );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $tmp, $raw );
		if ( false === $written ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp );
			return array( new \WP_Error( 'llm_csv_temp', __( 'Impossibile preparare il contenuto.', 'llm-con-tabelle' ) ), $warnings );
		}

		list( $parsed, $w2 ) = self::parse_csv_file( $tmp );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $tmp );

		if ( is_wp_error( $parsed ) ) {
			return array( $parsed, array_merge( $warnings, is_array( $w2 ) ? $w2 : array() ) );
		}

		return array( $parsed, array_merge( $warnings, is_array( $w2 ) ? $w2 : array() ) );
	}

	/**
	 * @param array<int,string|false> $cells
	 */
	private static function row_is_empty( $cells ) {
		foreach ( $cells as $c ) {
			if ( is_string( $c ) && trim( $c ) !== '' ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<int,string|false> $cells
	 * @return array{map: array<string,int>|null, first_row_is_header: bool}
	 */
	private static function analyze_first_csv_row( $cells ) {
		$first_is_header = true;
		$map             = self::map_header_row( $cells, $first_is_header );
		return array(
			'map'                 => $map,
			'first_row_is_header' => $first_is_header,
		);
	}

	/**
	 * @param array<int,string|false> $cells
	 * @param array<string,int>       $header_map
	 * @param int                       $row_num
	 * @param string[]                  $warnings
	 * @return array<string,mixed>|null
	 */
	private static function csv_row_to_phrase_row( $cells, $header_map, $row_num, array &$warnings ) {
		$pos = isset( $cells[ $header_map['pos'] ] ) ? self::parse_position_cell( $cells[ $header_map['pos'] ] ) : 0;
		if ( $pos < 1 ) {
			$warnings[] = sprintf(
				/* translators: %d: CSV row number */
				__( 'Riga %d: numero posizione non valido, riga ignorata.', 'llm-con-tabelle' ),
				$row_num
			);
			return null;
		}

		return array(
			'position'  => $pos,
			'interface' => isset( $cells[ $header_map['interface'] ] ) ? (string) $cells[ $header_map['interface'] ] : '',
			'target'    => isset( $cells[ $header_map['target'] ] ) ? (string) $cells[ $header_map['target'] ] : '',
			'grammar'   => isset( $cells[ $header_map['grammar'] ] ) ? (string) $cells[ $header_map['grammar'] ] : '',
			'alt'       => isset( $cells[ $header_map['alt'] ] ) ? (string) $cells[ $header_map['alt'] ] : '',
		);
	}

	/**
	 * @param array<int,string|false> $cells
	 * @param bool                    $first_is_header Out: true se la prima riga è solo intestazione.
	 * @return array<string,int>|null Chiavi pos, interface, target, grammar, alt → indice colonna.
	 */
	private static function map_header_row( $cells, &$first_is_header ) {
		$first_is_header = true;
		$norm = array();
		foreach ( $cells as $i => $c ) {
			$norm[ $i ] = self::normalize_header_cell( is_string( $c ) ? $c : '' );
		}

		$find = function ( $needles ) use ( $norm ) {
			foreach ( $norm as $i => $h ) {
				foreach ( $needles as $n ) {
					if ( strpos( $h, $n ) !== false ) {
						return (int) $i;
					}
				}
			}
			return null;
		};

		$pos_i = $find( array( 'numero posizione', 'numero', 'posizione' ) );
		$if_i  = $find( array( 'lingua interfaccia', 'interfaccia' ) );
		$tg_i  = $find( array( 'lingua obiettivo', 'obiettivo' ) );
		$gr_i  = $find( array( 'analisi grammaticale', 'grammaticale', 'grammar' ) );
		$al_i  = $find( array( 'traduzione alternativa', 'alternativa' ) );

		if ( null !== $pos_i && null !== $if_i && null !== $tg_i && null !== $gr_i && null !== $al_i ) {
			return array(
				'pos'       => $pos_i,
				'interface' => $if_i,
				'target'    => $tg_i,
				'grammar'   => $gr_i,
				'alt'       => $al_i,
			);
		}

		if ( count( $cells ) >= 5 ) {
			$fc = isset( $cells[0] ) ? trim( (string) $cells[0] ) : '';
			if ( $fc !== '' && preg_match( '/^\d+$/', $fc ) ) {
				$first_is_header = false;
			}
			return array(
				'pos'       => 0,
				'interface' => 1,
				'target'    => 2,
				'grammar'   => 3,
				'alt'       => 4,
			);
		}

		return null;
	}

	private static function normalize_header_cell( $s ) {
		$s = strtolower( trim( wp_strip_all_tags( $s ) ) );
		if ( function_exists( 'remove_accents' ) ) {
			$s = remove_accents( $s );
		}
		return $s;
	}

	/**
	 * @param mixed $v
	 */
	private static function parse_position_cell( $v ) {
		if ( is_numeric( $v ) ) {
			return (int) $v;
		}
		if ( is_string( $v ) && preg_match( '/^\s*(\d+)\s*$/', $v, $m ) ) {
			return (int) $m[1];
		}
		return 0;
	}

	/**
	 * @param array<int,array{interface:string,target:string,grammar:string,alt:string}> $current Frasi attuali (DB).
	 * @param array<int,array<string,mixed>>                                            $csv_rows Righe parse (position 1-based).
	 * @return array{merged: array<int,array{interface:string,target:string,grammar:string,alt:string}>, preview: array<int,array<string,mixed>>, duplicate_warnings: string[]}
	 */
	public static function merge_for_import( array $current, array $csv_rows ) {
		$by_pos = array();
		foreach ( $csv_rows as $r ) {
			$p = isset( $r['position'] ) ? absint( $r['position'] ) : 0;
			if ( $p < 1 ) {
				continue;
			}
			$by_pos[ $p ] = array(
				'interface' => isset( $r['interface'] ) ? LLM_Story_Repository::sanitize_phrase_rich_text( $r['interface'] ) : '',
				'target'    => isset( $r['target'] ) ? LLM_Story_Repository::sanitize_phrase_rich_text( $r['target'] ) : '',
				'grammar'   => isset( $r['grammar'] ) ? LLM_Story_Repository::sanitize_phrase_rich_text( $r['grammar'] ) : '',
				'alt'       => isset( $r['alt'] ) ? LLM_Story_Repository::sanitize_phrase_rich_text( $r['alt'] ) : '',
			);
		}

		$dup_warnings = array();
		$seen         = array();
		foreach ( $csv_rows as $r ) {
			$p = isset( $r['position'] ) ? absint( $r['position'] ) : 0;
			if ( $p < 1 ) {
				continue;
			}
			if ( isset( $seen[ $p ] ) ) {
				$dup_warnings[] = sprintf(
					/* translators: %d: phrase position */
					__( 'La posizione %d compare più volte: viene usata l’ultima riga.', 'llm-con-tabelle' ),
					$p
				);
			}
			$seen[ $p ] = true;
		}

		$old_count = count( $current );
		$max_csv   = empty( $by_pos ) ? 0 : max( array_keys( $by_pos ) );
		$max_pos   = max( $old_count, $max_csv );

		$empty = array(
			'interface' => '',
			'target'    => '',
			'grammar'   => '',
			'alt'       => '',
		);

		$merged  = array();
		$preview = array();

		for ( $p = 1; $p <= $max_pos; $p++ ) {
			$old = ( $p <= $old_count && isset( $current[ $p - 1 ] ) ) ? $current[ $p - 1 ] : $empty;

			if ( isset( $by_pos[ $p ] ) ) {
				$row    = $by_pos[ $p ];
				$action = ( $p <= $old_count ) ? 'replace' : 'add';
				$merged[] = $row;
				$preview[] = array(
					'position'  => $p,
					'action'    => $action,
					'interface' => $row['interface'],
					'target'    => $row['target'],
					'grammar'   => $row['grammar'],
					'alt'       => $row['alt'],
					'previous_interface' => isset( $old['interface'] ) ? (string) $old['interface'] : '',
				);
			} else {
				$merged[] = array(
					'interface' => isset( $old['interface'] ) ? (string) $old['interface'] : '',
					'target'    => isset( $old['target'] ) ? (string) $old['target'] : '',
					'grammar'   => isset( $old['grammar'] ) ? (string) $old['grammar'] : '',
					'alt'       => isset( $old['alt'] ) ? (string) $old['alt'] : '',
				);
			}
		}

		return array(
			'merged'             => $merged,
			'preview'            => $preview,
			'duplicate_warnings' => $dup_warnings,
		);
	}

	public static function ajax_preview_import() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'llm-con-tabelle' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! isset( $_POST['nonce_post'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce_post'] ) ), self::NONCE_ACTION . '_' . $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'llm-con-tabelle' ) ), 400 );
		}

		if ( ! self::user_can_edit_story( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'llm-con-tabelle' ) ), 403 );
		}

		$csv_text = isset( $_POST['csv_text'] ) ? wp_unslash( (string) $_POST['csv_text'] ) : '';
		$parsed   = null;
		$warnings = array();

		if ( trim( $csv_text ) !== '' ) {
			list( $parsed, $warnings ) = self::parse_csv_string( $csv_text );
		} elseif (
			! empty( $_FILES['file']['tmp_name'] )
			&& empty( $_FILES['file']['error'] )
			&& is_readable( $_FILES['file']['tmp_name'] )
		) {
			$file = $_FILES['file'];
			list( $parsed, $warnings ) = self::parse_csv_file( $file['tmp_name'] );
		} else {
			wp_send_json_error( array( 'message' => __( 'Carica un file CSV oppure incolla il contenuto.', 'llm-con-tabelle' ) ), 400 );
		}
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 400 );
		}

		$current = LLM_Story_Repository::get_phrases( $post_id );
		$result  = self::merge_for_import( $current, $parsed );

		$token = wp_generate_password( 20, false, false );
		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'post_id'  => $post_id,
				'user_id'  => get_current_user_id(),
				'phrases'  => $result['merged'],
				'created'  => time(),
			),
			15 * MINUTE_IN_SECONDS
		);

		$summary = array(
			'replace' => 0,
			'add'     => 0,
		);
		foreach ( $result['preview'] as $pr ) {
			if ( 'replace' === $pr['action'] ) {
				++$summary['replace'];
			} else {
				++$summary['add'];
			}
		}

		wp_send_json_success(
			array(
				'token'    => $token,
				'preview'  => $result['preview'],
				'warnings' => array_merge( $warnings, $result['duplicate_warnings'] ),
				'summary'  => $summary,
				'labels'   => array(
					'replace' => __( 'Sostituzione', 'llm-con-tabelle' ),
					'add'     => __( 'Aggiunta', 'llm-con-tabelle' ),
				),
			)
		);
	}

	public static function ajax_commit_import() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'llm-con-tabelle' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		if ( ! $post_id || $token === '' || ! isset( $_POST['nonce_post'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce_post'] ) ), self::NONCE_ACTION . '_' . $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'llm-con-tabelle' ) ), 400 );
		}

		if ( ! self::user_can_edit_story( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'llm-con-tabelle' ) ), 403 );
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! is_array( $data ) || (int) $data['post_id'] !== $post_id || (int) $data['user_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Anteprima scaduta o non valida. Ricarica il file.', 'llm-con-tabelle' ) ), 400 );
		}

		$phrases = isset( $data['phrases'] ) && is_array( $data['phrases'] ) ? $data['phrases'] : array();
		delete_transient( self::TRANSIENT_PREFIX . $token );

		$log   = array();
		$log[] = __( 'Avvio importazione frasi…', 'llm-con-tabelle' );

		LLM_Story_Repository::save_phrases( $post_id, $phrases );

		$log[] = sprintf(
			/* translators: %d: number of phrases saved */
			__( 'Salvate %d frasi nel database.', 'llm-con-tabelle' ),
			count( $phrases )
		);
		$log[] = __( 'Importazione completata.', 'llm-con-tabelle' );

		wp_send_json_success(
			array(
				'log'     => $log,
				'phrases' => $phrases,
			)
		);
	}
}
