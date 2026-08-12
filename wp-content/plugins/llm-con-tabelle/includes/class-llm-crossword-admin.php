<?php
/**
 * Admin cruciverba: schema CSV, definizioni e shortcode da copiare.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Crossword_Admin {

	const NONCE_ACTION  = 'llm_crossword_save';
	const NONCE_NAME    = 'llm_crossword_nonce';
	const AJAX_ACTION   = 'llm_crossword_analyze';
	const NOTICE_PREFIX = 'llm_cw_notice_';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLM_Crossword::CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_analyze' ) );
		add_filter( 'manage_' . LLM_Crossword::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLM_Crossword::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	private static function is_crossword_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && LLM_Crossword::CPT === $screen->post_type;
	}

	public static function enqueue( $hook ) {
		if ( ! self::is_crossword_screen() ) {
			return;
		}
		/* L'anteprima riusa il foglio di stile del gioco per mostrare la grafica reale. */
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

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'llm-crossword-admin',
			LLM_TABELLE_URL . 'assets/llm-crossword-admin.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-crossword-admin',
			'llmCrosswordAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
				'i18n'    => array(
					'working'      => __( 'Leggo il cruciverba…', 'llm-con-tabelle' ),
					'networkError' => __( 'Errore di rete. Riprova.', 'llm-con-tabelle' ),
					'copied'       => __( 'Shortcode copiato.', 'llm-con-tabelle' ),
					'copyFailed'   => __( 'Copia non riuscita: seleziona il testo a mano.', 'llm-con-tabelle' ),
					'confirmDefs'  => __( 'Rigenero le definizioni dallo schema: quelle già scritte vengono mantenute se corrispondono. Procedo?', 'llm-con-tabelle' ),
					'bundleEmpty'  => __( 'Incolla prima il file del cruciverba.', 'llm-con-tabelle' ),
				),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_crossword_data',
			__( 'File cruciverba', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_box' ),
			LLM_Crossword::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Cruciverba in modifica.
	 */
	public static function render_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$known     = LLM_Crossword::get_known( $post->ID );
		$target    = LLM_Crossword::get_target( $post->ID );
		$has_data  = '' !== trim( LLM_Crossword::get_csv( $post->ID ) ) || '' !== trim( LLM_Crossword::get_defs( $post->ID ) );
		$lang_line = ( $known && $target )
			? LLM_Crossword::format_lang_line( $known, $target )
			: 'Italiano → Polacco';
		$bundle    = $has_data
			? LLM_Crossword::get_bundle( $post->ID )
			: LLM_Crossword::format_bundle( $lang_line, '', '' );
		$shortcode = '[llm_crossword id="' . (int) $post->ID . '"]';
		$codes     = LLM_Languages::get_codes();
		?>
		<div class="llm-cw-admin">
			<?php if ( 'auto-draft' !== $post->post_status ) : ?>
				<div class="llm-cw-admin__shortcode">
					<label for="llm-cw-shortcode"><?php esc_html_e( 'Shortcode di questo cruciverba', 'llm-con-tabelle' ); ?></label>
					<div class="llm-cw-admin__shortcode-row">
						<input type="text" id="llm-cw-shortcode" readonly value="<?php echo esc_attr( $shortcode ); ?>">
						<button type="button" class="button" id="llm-cw-copy"><?php esc_html_e( 'Copia', 'llm-con-tabelle' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Incollalo in una pagina o in un widget per far apparire il cruciverba giocabile.', 'llm-con-tabelle' ); ?></p>
				</div>
			<?php else : ?>
				<p class="description llm-cw-admin__hint">
					<?php esc_html_e( 'Salva la bozza per ottenere lo shortcode di questo cruciverba.', 'llm-con-tabelle' ); ?>
				</p>
			<?php endif; ?>

			<div class="llm-cw-admin__pair">
				<p class="description"><?php esc_html_e( 'Coppia di lingue: serve per mostrare questo cruciverba solo nelle riviste della stessa coppia.', 'llm-con-tabelle' ); ?></p>
				<div class="llm-cw-admin__pair-row">
					<p>
						<label for="llm_cw_known"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ); ?></strong></label>
						<select name="llm_cw_known" id="llm_cw_known" class="widefat" required>
							<option value=""><?php esc_html_e( '— Scegli —', 'llm-con-tabelle' ); ?></option>
							<?php foreach ( $codes as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="llm_cw_target"><strong><?php esc_html_e( 'Lingua da imparare', 'llm-con-tabelle' ); ?></strong></label>
						<select name="llm_cw_target" id="llm_cw_target" class="widefat" required>
							<option value=""><?php esc_html_e( '— Scegli —', 'llm-con-tabelle' ); ?></option>
							<?php foreach ( $codes as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				</div>
			</div>

			<div class="llm-cw-admin__field">
				<label for="llm-cw-bundle"><strong><?php esc_html_e( 'File completo del cruciverba', 'llm-con-tabelle' ); ?></strong></label>
				<p class="description">
					<?php
					esc_html_e( 'Incolla qui un unico testo con tre sezioni:', 'llm-con-tabelle' );
					echo ' <code>###### LINGUA</code>, <code>###### SCHEMA</code>, <code>###### DEFINIZIONI</code>. ';
					esc_html_e( 'La riga LINGUA viene aggiornata automaticamente dalla coppia scelta sopra al salvataggio.', 'llm-con-tabelle' );
					?>
				</p>
				<textarea id="llm-cw-bundle" name="llm_crossword_bundle" rows="28" class="large-text code" spellcheck="false"><?php echo esc_textarea( $bundle ); ?></textarea>
			</div>

			<p class="llm-cw-admin__actions">
				<button type="button" class="button button-secondary" id="llm-cw-preview"><?php esc_html_e( 'Controlla cruciverba', 'llm-con-tabelle' ); ?></button>
				<button type="button" class="button" id="llm-cw-skeleton"><?php esc_html_e( 'Rigenera elenco definizioni dallo schema', 'llm-con-tabelle' ); ?></button>
				<span class="llm-cw-admin__status" id="llm-cw-status" role="status" aria-live="polite"></span>
			</p>

			<div class="llm-cw-admin__preview" id="llm-cw-preview-box" hidden></div>
		</div>
		<?php
	}

	/**
	 * @param int     $post_id ID cruciverba.
	 * @param WP_Post $post    Post.
	 */
	public static function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* Non sanitize_textarea_field: lascia caratteri speciali delle definizioni. */
		$raw = isset( $_POST['llm_crossword_bundle'] ) ? wp_unslash( (string) $_POST['llm_crossword_bundle'] ) : '';
		$raw = str_replace( "\0", '', $raw );

		$known  = isset( $_POST['llm_cw_known'] ) ? sanitize_key( wp_unslash( $_POST['llm_cw_known'] ) ) : '';
		$target = isset( $_POST['llm_cw_target'] ) ? sanitize_key( wp_unslash( $_POST['llm_cw_target'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		if ( ! LLM_Languages::is_valid( $target ) ) {
			$target = '';
		}
		if ( $known && $target && $known === $target ) {
			$target = '';
		}

		$parsed = LLM_Crossword::parse_bundle( $raw );
		if ( is_wp_error( $parsed ) ) {
			self::store_notice(
				$post_id,
				array(
					'errors'   => array( $parsed->get_error_message() ),
					'warnings' => array(),
					'info'     => array(),
				)
			);
			return;
		}

		// Se i select non sono valorizzati, prova a ricavare la coppia dalla sezione LINGUA.
		if ( ( ! $known || ! $target ) && '' !== trim( $parsed['lang'] ) ) {
			$from_line = LLM_Crossword::parse_lang_line( $parsed['lang'] );
			if ( $from_line ) {
				$known  = $from_line['known'];
				$target = $from_line['target'];
			}
		}

		$lang_line = ( $known && $target )
			? LLM_Crossword::format_lang_line( $known, $target )
			: sanitize_text_field( $parsed['lang'] );

		update_post_meta( $post_id, LLM_Crossword::META_KNOWN, $known );
		update_post_meta( $post_id, LLM_Crossword::META_TARGET, $target );
		update_post_meta( $post_id, LLM_Crossword::META_LANG, $lang_line );
		update_post_meta( $post_id, LLM_Crossword::META_CSV, $parsed['csv'] );
		update_post_meta( $post_id, LLM_Crossword::META_DEFS, $parsed['defs'] );

		$notice = self::validate( $parsed['csv'], $parsed['defs'] );
		if ( ! $known || ! $target ) {
			$notice['warnings'][] = __( 'Seleziona la coppia di lingue: senza coppia il cruciverba non compare nelle riviste.', 'llm-con-tabelle' );
		}
		self::store_notice( $post_id, $notice );
	}

	/**
	 * Controlla schema e definizioni senza bloccare il salvataggio.
	 *
	 * @param string $csv  Schema CSV.
	 * @param string $defs Definizioni.
	 * @return array{errors:string[],warnings:string[],info:string[]}
	 */
	private static function validate( $csv, $defs ) {
		$out = array(
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		if ( '' === trim( $csv ) ) {
			return $out;
		}

		$parsed = LLM_Crossword::parse_grid( $csv );
		if ( is_wp_error( $parsed ) ) {
			$out['errors'][] = $parsed->get_error_message();
			return $out;
		}

		$entries = LLM_Crossword::entries( $parsed['grid'] );
		if ( empty( $entries ) ) {
			$out['errors'][] = __( 'Lo schema non contiene nessuna parola di almeno due lettere.', 'llm-con-tabelle' );
			return $out;
		}

		$defs_parsed      = LLM_Crossword::parse_definitions( $defs, $entries );
		$out['warnings']  = $defs_parsed['warnings'];
		$missing          = count( $entries ) - count( $defs_parsed['clues'] );

		if ( $missing > 0 ) {
			$out['warnings'][] = sprintf(
				/* translators: %d: number of words without a clue */
				_n(
					'%d parola non ha ancora una definizione: nel gioco mostrerà solo il numero di lettere.',
					'%d parole non hanno ancora una definizione: nel gioco mostreranno solo il numero di lettere.',
					$missing,
					'llm-con-tabelle'
				),
				$missing
			);
		}

		$out['info'][] = sprintf(
			/* translators: 1: rows, 2: columns, 3: number of words */
			__( 'Schema valido: griglia %1$d x %2$d con %3$d parole.', 'llm-con-tabelle' ),
			$parsed['rows'],
			$parsed['cols'],
			count( $entries )
		);

		return $out;
	}

	/**
	 * @param int   $post_id ID cruciverba.
	 * @param array $notice  Esito validazione.
	 */
	private static function store_notice( $post_id, array $notice ) {
		if ( empty( $notice['errors'] ) && empty( $notice['warnings'] ) && empty( $notice['info'] ) ) {
			return;
		}
		set_transient( self::NOTICE_PREFIX . absint( $post_id ) . '_' . get_current_user_id(), $notice, 60 );
	}

	public static function notices() {
		if ( ! self::is_crossword_screen() ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			return;
		}
		$key    = self::NOTICE_PREFIX . $post_id . '_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );

		foreach ( array( 'errors' => 'error', 'warnings' => 'warning', 'info' => 'success' ) as $bucket => $class ) {
			if ( empty( $notice[ $bucket ] ) || ! is_array( $notice[ $bucket ] ) ) {
				continue;
			}
			echo '<div class="notice notice-' . esc_attr( $class ) . '"><ul style="margin:0.5em 0;">';
			foreach ( $notice[ $bucket ] as $message ) {
				echo '<li>' . esc_html( $message ) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	/**
	 * Elenco definizioni del pannello laterale, diviso in orizzontali e verticali.
	 *
	 * @param array                $entries Parole.
	 * @param array                $clues   Definizioni per chiave.
	 * @param array<string,string> $i18n    Testi UI.
	 * @return string HTML.
	 */
	private static function preview_clues_html( array $entries, array $clues, array $i18n ) {
		$groups = array(
			'across' => array(),
			'down'   => array(),
		);

		foreach ( $entries as $entry ) {
			$key  = LLM_Crossword::clue_key( $entry['number'], $entry['direction'] );
			$text = isset( $clues[ $key ] ) ? LLM_Crossword::clue_html( $clues[ $key ] ) : '';
			if ( '' === $text ) {
				$text = esc_html( sprintf( $i18n['letters_count'], $entry['length'] ) );
			}
			$groups[ $entry['direction'] ][] = '<div class="cw-clue-row"><span class="cw-clue-num">'
				. (int) $entry['number'] . '</span> ' . $text . '</div>';
		}

		$html = '';
		if ( ! empty( $groups['across'] ) ) {
			$html .= '<h3>' . esc_html( $i18n['across'] ) . '</h3>' . implode( '', $groups['across'] );
		}
		if ( ! empty( $groups['down'] ) ) {
			$html .= '<h3>' . esc_html( $i18n['down'] ) . '</h3>' . implode( '', $groups['down'] );
		}
		return $html;
	}

	/**
	 * Anteprima con la stessa grafica del frontend: griglia numerata a sinistra,
	 * pulsanti e definizioni a destra. Qui le lettere della soluzione sono visibili.
	 *
	 * @param string[] $grid    Righe della griglia.
	 * @param array    $entries Parole.
	 * @param array    $clues   Definizioni per chiave.
	 * @return string HTML.
	 */
	private static function preview_html( array $grid, array $entries, array $clues ) {
		$i18n = LLM_Crossword_I18n::bundle();

		$numbering = array();
		foreach ( $entries as $entry ) {
			$numbering[ $entry['row'] . '-' . $entry['col'] ] = $entry['number'];
		}

		$rows = count( $grid );
		$cols = $rows > 0 ? strlen( $grid[0] ) : 0;

		/* Restringe la cella se lo schema e' largo, come fa il gioco sul telefono. */
		$cell = $cols > 0 ? (int) floor( 720 / $cols ) : 38;
		$cell = max( 20, min( 38, $cell ) );

		$cells = '';
		for ( $r = 0; $r < $rows; $r++ ) {
			for ( $c = 0; $c < $cols; $c++ ) {
				$char = $grid[ $r ][ $c ];
				if ( '#' === $char ) {
					$cells .= '<div class="cw-black"></div>';
					continue;
				}
				$num    = isset( $numbering[ $r . '-' . $c ] ) ? $numbering[ $r . '-' . $c ] : 0;
				$cells .= '<div class="cw-cell-wrap">';
				if ( $num ) {
					$cells .= '<span class="cw-number">' . (int) $num . '</span>';
				}
				$cells .= '<div class="cw-input cw-static">' . esc_html( $char ) . '</div></div>';
			}
		}

		$grid_style = sprintf(
			'grid-template-columns:repeat(%1$d, var(--cw-cell-size));grid-template-rows:repeat(%2$d, var(--cw-cell-size));',
			$cols,
			$rows
		);

		return '<p class="llm-cw-preview-note">'
			. esc_html__( 'Cosi apparira nel frontend. Qui vedi le lettere della soluzione: nel gioco le caselle partono vuote.', 'llm-con-tabelle' )
			. '</p>'
			. '<div class="llm-crossword llm-crossword--preview llm-ui-scope llm-ui-scope--light" style="--cw-cell-size:' . $cell . 'px;" aria-hidden="true">'
			. '<div class="cw-container">'
			. '<div class="cw-board">'
			. '<div class="cw-grid" style="' . esc_attr( $grid_style ) . '">' . $cells . '</div>'
			. '<div class="cw-reveal-wrap">'
			. '<span class="llm-ui-btn llm-ui-btn--primary cw-reveal">'
			. '<svg class="cw-reveal__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
			. '<path fill="currentColor" d="M12 2a7 7 0 0 0-4 12.75V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.25A7 7 0 0 0 12 2zm2 14h-4v-.4a1 1 0 0 0 .4-.8V14h3.2v.8a1 1 0 0 0 .4.8V16zm.2-3.2H9.8A5 5 0 1 1 14.2 12.8zM10 20a1 1 0 0 0 1 1h2a1 1 0 1 0 0-2h-2a1 1 0 0 0-1 1z"/>'
			. '</svg>'
			. '<span>' . esc_html( $i18n['reveal_letter'] ) . '</span>'
			. '</span>'
			. '</div>'
			. '<div class="cw-controls">'
			. '<span class="llm-ui-btn llm-ui-btn--ghost cw-btn">' . esc_html( $i18n['check'] ) . '</span>'
			. '<span class="llm-ui-btn llm-ui-btn--ghost cw-btn">' . esc_html( $i18n['restart'] ) . '</span>'
			. '</div>'
			. '<p class="cw-status">' . esc_html( $i18n['start_hint'] ) . '</p>'
			. '</div>'
			. '<div class="cw-panel">'
			. '<div class="cw-entrylist">' . self::preview_clues_html( $entries, $clues, $i18n ) . '</div>'
			. '</div>'
			. '</div>'
			. '</div>';
	}

	public static function ajax_analyze() {
		if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'llm-con-tabelle' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'llm-con-tabelle' ) ), 403 );
		}

		$raw = isset( $_POST['bundle'] ) ? wp_unslash( (string) $_POST['bundle'] ) : '';
		$raw = str_replace( "\0", '', $raw );

		$bundle = LLM_Crossword::parse_bundle( $raw );
		if ( is_wp_error( $bundle ) ) {
			wp_send_json_error( array( 'message' => $bundle->get_error_message() ), 200 );
		}

		$parsed = LLM_Crossword::parse_grid( $bundle['csv'] );
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 200 );
		}

		$entries = LLM_Crossword::entries( $parsed['grid'] );
		if ( empty( $entries ) ) {
			wp_send_json_error( array( 'message' => __( 'Lo schema non contiene nessuna parola di almeno due lettere.', 'llm-con-tabelle' ) ), 200 );
		}

		$defs_parsed = LLM_Crossword::parse_definitions( $bundle['defs'], $entries );
		$skeleton    = LLM_Crossword::definitions_skeleton( $entries, $bundle['defs'] );
		$written     = count( $defs_parsed['clues'] );

		wp_send_json_success(
			array(
				'preview'  => self::preview_html( $parsed['grid'], $entries, $defs_parsed['clues'] ),
				'bundle'   => LLM_Crossword::format_bundle( $bundle['lang'], $bundle['csv'], $skeleton ),
				'warnings' => $defs_parsed['warnings'],
				'summary'  => sprintf(
					/* translators: 1: rows, 2: columns, 3: number of words, 4: words with a clue */
					__( 'Griglia %1$d x %2$d, %3$d parole, %4$d con definizione.', 'llm-con-tabelle' ),
					$parsed['rows'],
					$parsed['cols'],
					count( $entries ),
					$written
				),
			)
		);
	}

	/**
	 * @param array<string,string> $columns Colonne lista.
	 * @return array<string,string>
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['llm_cw_pair']      = __( 'Coppia', 'llm-con-tabelle' );
				$out['llm_cw_grid']      = __( 'Griglia', 'llm-con-tabelle' );
				$out['llm_cw_shortcode'] = __( 'Shortcode', 'llm-con-tabelle' );
			}
		}
		return $out;
	}

	/**
	 * @param string $column  Colonna.
	 * @param int    $post_id ID cruciverba.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'llm_cw_pair' === $column ) {
			$known  = LLM_Crossword::get_known( $post_id );
			$target = LLM_Crossword::get_target( $post_id );
			if ( $known && $target ) {
				echo esc_html( LLM_Languages::label( $known ) . ' → ' . LLM_Languages::label( $target ) );
			} else {
				echo '—';
			}
			return;
		}
		if ( 'llm_cw_shortcode' === $column ) {
			echo '<code>[llm_crossword id="' . (int) $post_id . '"]</code>';
			return;
		}
		if ( 'llm_cw_grid' !== $column ) {
			return;
		}

		$parsed = LLM_Crossword::parse_grid( LLM_Crossword::get_csv( $post_id ) );
		if ( is_wp_error( $parsed ) ) {
			echo '<span class="llm-cw-col-error">' . esc_html__( 'Schema da sistemare', 'llm-con-tabelle' ) . '</span>';
			return;
		}

		$entries = LLM_Crossword::entries( $parsed['grid'] );
		$defs    = LLM_Crossword::parse_definitions( LLM_Crossword::get_defs( $post_id ), $entries );

		echo esc_html(
			sprintf(
				/* translators: 1: rows, 2: columns, 3: words with a clue, 4: total words */
				__( '%1$d x %2$d — %3$d/%4$d definizioni', 'llm-con-tabelle' ),
				$parsed['rows'],
				$parsed['cols'],
				count( $defs['clues'] ),
				count( $entries )
			)
		);
	}
}
