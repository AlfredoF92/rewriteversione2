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
		wp_enqueue_style(
			'llm-crossword',
			LLM_TABELLE_URL . 'assets/llm-crossword.css',
			array(),
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
					'working'      => __( 'Leggo lo schema…', 'llm-con-tabelle' ),
					'networkError' => __( 'Errore di rete. Riprova.', 'llm-con-tabelle' ),
					'copied'       => __( 'Shortcode copiato.', 'llm-con-tabelle' ),
					'copyFailed'   => __( 'Copia non riuscita: seleziona il testo a mano.', 'llm-con-tabelle' ),
					'confirmDefs'  => __( 'Rigenero l’elenco: le definizioni già scritte vengono mantenute, le righe non riconosciute vanno perse. Procedo?', 'llm-con-tabelle' ),
				),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_crossword_data',
			__( 'Schema e definizioni', 'llm-con-tabelle' ),
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

		$csv       = LLM_Crossword::get_csv( $post->ID );
		$defs      = LLM_Crossword::get_defs( $post->ID );
		$shortcode = '[llm_crossword id="' . (int) $post->ID . '"]';
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

			<div class="llm-cw-admin__field">
				<label for="llm-cw-csv"><strong><?php esc_html_e( 'Schema in formato CSV', 'llm-con-tabelle' ); ?></strong></label>
				<p class="description">
					<?php esc_html_e( 'Una riga della griglia per ogni riga di testo, celle separate da virgola. Scrivi la lettera della soluzione, oppure # (o una cella vuota) per le caselle nere. Tutte le righe devono avere lo stesso numero di celle.', 'llm-con-tabelle' ); ?>
				</p>
				<textarea id="llm-cw-csv" name="llm_crossword_csv" rows="12" class="large-text code" spellcheck="false" placeholder="K,I,T,C,H,E,N,#,D,#,H&#10;#,#,A,#,#,#,#,#,I,#,O"><?php echo esc_textarea( $csv ); ?></textarea>
			</div>

			<p class="llm-cw-admin__actions">
				<button type="button" class="button button-secondary" id="llm-cw-preview"><?php esc_html_e( 'Controlla schema', 'llm-con-tabelle' ); ?></button>
				<button type="button" class="button button-primary" id="llm-cw-skeleton"><?php esc_html_e( 'Genera elenco definizioni dal CSV', 'llm-con-tabelle' ); ?></button>
				<span class="llm-cw-admin__status" id="llm-cw-status" role="status" aria-live="polite"></span>
			</p>

			<div class="llm-cw-admin__preview" id="llm-cw-preview-box" hidden></div>

			<div class="llm-cw-admin__field">
				<label for="llm-cw-defs"><strong><?php esc_html_e( 'Definizioni', 'llm-con-tabelle' ); ?></strong></label>
				<p class="description">
					<?php
					echo esc_html__( 'Una definizione per riga, campi separati da barra verticale:', 'llm-con-tabelle' );
					echo ' <code>numero|direzione|parola|categoria|definizione inglese|definizione italiana</code>. ';
					echo esc_html__( 'La direzione è O per orizzontale e V per verticale. I campi che non ti servono lasciali vuoti; le righe che iniziano con # sono commenti.', 'llm-con-tabelle' );
					?>
				</p>
				<textarea id="llm-cw-defs" name="llm_crossword_defs" rows="16" class="large-text code" spellcheck="false" placeholder="1|O|KITCHEN|Noun|The room where you cook|La stanza dove cucini"><?php echo esc_textarea( $defs ); ?></textarea>
			</div>
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

		$csv  = isset( $_POST['llm_crossword_csv'] ) ? sanitize_textarea_field( wp_unslash( $_POST['llm_crossword_csv'] ) ) : '';
		$defs = isset( $_POST['llm_crossword_defs'] ) ? sanitize_textarea_field( wp_unslash( $_POST['llm_crossword_defs'] ) ) : '';

		update_post_meta( $post_id, LLM_Crossword::META_CSV, $csv );
		update_post_meta( $post_id, LLM_Crossword::META_DEFS, $defs );

		self::store_notice( $post_id, self::validate( $csv, $defs ) );
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
			. '<div class="llm-crossword llm-crossword--preview" style="--cw-cell-size:' . $cell . 'px;" aria-hidden="true">'
			. '<div class="cw-container">'
			. '<div class="cw-board">'
			. '<div class="cw-grid" style="' . esc_attr( $grid_style ) . '">' . $cells . '</div>'
			. '<div class="cw-controls">'
			. '<span class="cw-btn">' . esc_html( $i18n['check'] ) . '</span>'
			. '<span class="cw-btn">' . esc_html( $i18n['restart'] ) . '</span>'
			. '</div>'
			. '<p class="cw-status">' . esc_html( $i18n['start_hint'] ) . '</p>'
			. '</div>'
			. '<div class="cw-panel">'
			. '<span class="cw-btn cw-primary cw-big">' . esc_html( $i18n['reveal_letter'] ) . '</span>'
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

		$csv  = isset( $_POST['csv'] ) ? sanitize_textarea_field( wp_unslash( $_POST['csv'] ) ) : '';
		$defs = isset( $_POST['defs'] ) ? sanitize_textarea_field( wp_unslash( $_POST['defs'] ) ) : '';

		$parsed = LLM_Crossword::parse_grid( $csv );
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 200 );
		}

		$entries = LLM_Crossword::entries( $parsed['grid'] );
		if ( empty( $entries ) ) {
			wp_send_json_error( array( 'message' => __( 'Lo schema non contiene nessuna parola di almeno due lettere.', 'llm-con-tabelle' ) ), 200 );
		}

		$defs_parsed = LLM_Crossword::parse_definitions( $defs, $entries );
		$written     = count( $defs_parsed['clues'] );

		wp_send_json_success(
			array(
				'preview'  => self::preview_html( $parsed['grid'], $entries, $defs_parsed['clues'] ),
				'skeleton' => LLM_Crossword::definitions_skeleton( $entries, $defs ),
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
