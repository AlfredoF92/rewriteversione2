<?php
/**
 * Admin quiz: coppia lingue, CRUD domande, import CSV.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Quiz_Admin {

	const NONCE_ACTION = 'llm_quiz_save';
	const NONCE_NAME   = 'llm_quiz_nonce';
	const AJAX_IMPORT  = 'llm_quiz_import_csv';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLM_Quiz::CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_IMPORT, array( __CLASS__, 'ajax_import_csv' ) );
		add_filter( 'manage_' . LLM_Quiz::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLM_Quiz::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list_query' ) );
	}

	private static function is_quiz_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && LLM_Quiz::CPT === $screen->post_type;
	}

	public static function enqueue( $hook ) {
		if ( ! self::is_quiz_screen() ) {
			return;
		}
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'llm-quiz-admin',
			LLM_TABELLE_URL . 'assets/llm-quiz-admin.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-quiz-admin',
			LLM_TABELLE_URL . 'assets/llm-quiz-admin.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-quiz-admin',
			'llmQuizAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_IMPORT,
				'nonce'   => wp_create_nonce( self::AJAX_IMPORT ),
				'i18n'    => array(
					'confirmReplace' => __( 'Sostituisco tutte le domande attuali con quelle del CSV. Procedo?', 'llm-con-tabelle' ),
					'confirmAppend'  => __( 'Aggiungo le domande del CSV a quelle già presenti. Procedo?', 'llm-con-tabelle' ),
					'importEmpty'    => __( 'Incolla prima il CSV.', 'llm-con-tabelle' ),
					'importing'      => __( 'Importo…', 'llm-con-tabelle' ),
					'networkError'   => __( 'Errore di rete. Riprova.', 'llm-con-tabelle' ),
					'removeQ'        => __( 'Eliminare questa domanda?', 'llm-con-tabelle' ),
					'questionLabel'  => __( 'Domanda', 'llm-con-tabelle' ),
					'edit'           => __( 'Modifica', 'llm-con-tabelle' ),
					'close'          => __( 'Chiudi', 'llm-con-tabelle' ),
					'uncategorized'  => __( 'Senza categoria', 'llm-con-tabelle' ),
					'correctLetter'  => __( 'Corretta', 'llm-con-tabelle' ),
				),
				'categories' => LLM_Quiz::default_categories(),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_quiz_settings',
			__( 'Coppia linguistica', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_settings' ),
			LLM_Quiz::CPT,
			'side',
			'high'
		);
		add_meta_box(
			'llm_quiz_import',
			__( 'Importa CSV', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_import' ),
			LLM_Quiz::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'llm_quiz_questions',
			__( 'Domande', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_questions' ),
			LLM_Quiz::CPT,
			'normal',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Quiz.
	 */
	public static function render_settings( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$known  = LLM_Quiz::get_known( $post->ID );
		$target = LLM_Quiz::get_target( $post->ID );
		$langs  = LLM_Languages::get_codes();
		?>
		<p>
			<label for="llm_quiz_known"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_quiz_known" id="llm_quiz_known" class="widefat" required>
				<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="llm_quiz_target"><strong><?php esc_html_e( 'Lingua da imparare', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_quiz_target" id="llm_quiz_target" class="widefat" required>
				<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'Una banca quiz per coppia. Ogni domanda ha 3 risposte con spiegazione e una risposta corretta (A/B/C).', 'llm-con-tabelle' ); ?>
		</p>
		<?php
	}

	/**
	 * @param WP_Post $post Quiz.
	 */
	public static function render_import( $post ) {
		unset( $post );
		?>
		<div class="llm-quiz-admin__import">
			<p class="description">
				<?php esc_html_e( 'Colonne CSV (virgola; testo con virgole tra virgolette):', 'llm-con-tabelle' ); ?>
			</p>
			<pre class="llm-quiz-admin__csv-help">category,question,answer1,explanation1,answer2,explanation2,answer3,explanation3,correct</pre>
			<p class="description">
				<?php esc_html_e( 'correct = A, B o C (oppure 1/2/3). Categorie esempio: Curiosità sul paese, Curiosità sull’Italia, Curiosità sulla Polonia, Eventi storici.', 'llm-con-tabelle' ); ?>
			</p>
			<textarea id="llm_quiz_csv" class="widefat code" rows="6" placeholder="<?php echo esc_attr( "category,question,answer1,explanation1,answer2,explanation2,answer3,explanation3,correct\nEventi storici,Nel 1066…?,Conquista normanna,Spiega A…,Il tè,Spiega B…,USA 1776,Spiega C…,A" ); ?>"></textarea>
			<p class="llm-quiz-admin__import-actions">
				<button type="button" class="button button-primary" id="llm-quiz-import-append"><?php esc_html_e( 'Importa e aggiungi', 'llm-con-tabelle' ); ?></button>
				<button type="button" class="button" id="llm-quiz-import-replace"><?php esc_html_e( 'Importa e sostituisci tutto', 'llm-con-tabelle' ); ?></button>
				<span class="llm-quiz-admin__import-status" id="llm-quiz-import-status" aria-live="polite"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Quiz.
	 */
	public static function render_questions( $post ) {
		$questions = LLM_Quiz::get_questions( $post->ID );
		?>
		<div class="llm-quiz-admin__questions-wrap">
			<p class="description">
				<?php esc_html_e( 'Panoramica compatta per categoria. Clicca «Modifica» per aprire i dettagli. Poi Salva il post.', 'llm-con-tabelle' ); ?>
			</p>
			<p class="llm-quiz-admin__toolbar">
				<button type="button" class="button button-secondary" id="llm-quiz-add-question"><?php esc_html_e( 'Aggiungi domanda', 'llm-con-tabelle' ); ?></button>
				<button type="button" class="button" id="llm-quiz-collapse-all"><?php esc_html_e( 'Chiudi tutte', 'llm-con-tabelle' ); ?></button>
				<span class="llm-quiz-admin__count" id="llm-quiz-count">
					<?php
					printf(
						/* translators: %d: question count */
						esc_html__( '%d domande', 'llm-con-tabelle' ),
						count( $questions )
					);
					?>
				</span>
			</p>
			<div id="llm-quiz-questions" class="llm-quiz-admin__questions" data-count="<?php echo esc_attr( (string) count( $questions ) ); ?>">
				<?php echo self::render_questions_list_html( $questions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<template id="llm-quiz-question-template">
				<?php
				self::render_question_block(
					'__INDEX__',
					array(
						'id'       => '',
						'category' => '',
						'question' => '',
						'correct'  => 0,
						'answers'  => array(
							array( 'text' => '', 'explanation' => '' ),
							array( 'text' => '', 'explanation' => '' ),
							array( 'text' => '', 'explanation' => '' ),
						),
					),
					true
				);
				?>
			</template>
			<datalist id="llm-quiz-category-list">
				<?php foreach ( LLM_Quiz::default_categories() as $c ) : ?>
					<option value="<?php echo esc_attr( $c ); ?>">
				<?php endforeach; ?>
			</datalist>
		</div>
		<?php
	}

	/**
	 * HTML elenco raggruppato (usato anche dopo import AJAX).
	 *
	 * @param array $questions Domande.
	 * @return string
	 */
	public static function render_questions_list_html( array $questions ) {
		if ( empty( $questions ) ) {
			ob_start();
			echo '<p class="llm-quiz-admin__empty">' . esc_html__( 'Nessuna domanda. Aggiungine una o importa un CSV.', 'llm-con-tabelle' ) . '</p>';
			return (string) ob_get_clean();
		}

		$groups = LLM_Quiz::group_by_category( $questions );
		ob_start();
		foreach ( $groups as $cat_label => $items ) {
			echo '<section class="llm-quiz-admin__cat" data-category="' . esc_attr( $cat_label ) . '">';
			echo '<h4 class="llm-quiz-admin__cat-title">' . esc_html( $cat_label ) . ' <span class="llm-quiz-admin__cat-count">(' . count( $items ) . ')</span></h4>';
			echo '<div class="llm-quiz-admin__cat-list">';
			foreach ( $items as $index => $q ) {
				self::render_question_block( (int) $index, $q, false );
			}
			echo '</div></section>';
		}
		return (string) ob_get_clean();
	}

	/**
	 * @param int|string $index   Indice form.
	 * @param array      $q       Domanda.
	 * @param bool       $open    Se true, editor già aperto (nuove domande).
	 */
	private static function render_question_block( $index, array $q, $open = false ) {
		$id       = isset( $q['id'] ) ? (string) $q['id'] : '';
		$category = isset( $q['category'] ) ? (string) $q['category'] : '';
		$question = isset( $q['question'] ) ? (string) $q['question'] : '';
		$correct  = isset( $q['correct'] ) ? (int) $q['correct'] : 0;
		$answers  = isset( $q['answers'] ) && is_array( $q['answers'] ) ? $q['answers'] : array();
		$answers  = array_pad( $answers, LLM_Quiz::ANSWERS_PER_QUESTION, array( 'text' => '', 'explanation' => '' ) );
		$label_n  = is_numeric( $index ) ? ( (int) $index + 1 ) : '#';
		$letter   = chr( 65 + max( 0, min( 2, $correct ) ) );
		$preview  = $question !== '' ? $question : __( '(vuota)', 'llm-con-tabelle' );
		$open_cls = $open ? ' is-open' : '';
		?>
		<div class="llm-quiz-admin__question<?php echo esc_attr( $open_cls ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>" data-category="<?php echo esc_attr( $category ); ?>">
			<div class="llm-quiz-admin__row">
				<span class="llm-quiz-admin__num llm-quiz-admin__question-label">#<?php echo esc_html( (string) $label_n ); ?></span>
				<span class="llm-quiz-admin__preview" title="<?php echo esc_attr( $preview ); ?>"><?php echo esc_html( wp_html_excerpt( $preview, 110, '…' ) ); ?></span>
				<span class="llm-quiz-admin__badge llm-quiz-admin__badge--correct"><?php echo esc_html( sprintf( __( 'Corretta %s', 'llm-con-tabelle' ), $letter ) ); ?></span>
				<span class="llm-quiz-admin__question-tools">
					<button type="button" class="button-link llm-quiz-toggle"><?php echo $open ? esc_html__( 'Chiudi', 'llm-con-tabelle' ) : esc_html__( 'Modifica', 'llm-con-tabelle' ); ?></button>
					<button type="button" class="button-link llm-quiz-move-up"><?php esc_html_e( 'Su', 'llm-con-tabelle' ); ?></button>
					<button type="button" class="button-link llm-quiz-move-down"><?php esc_html_e( 'Giù', 'llm-con-tabelle' ); ?></button>
					<button type="button" class="button-link-delete llm-quiz-remove"><?php esc_html_e( 'Elimina', 'llm-con-tabelle' ); ?></button>
				</span>
			</div>
			<div class="llm-quiz-admin__editor">
				<input type="hidden" name="llm_quiz_q[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" class="llm-quiz-q-id">
				<div class="llm-quiz-admin__editor-grid">
					<p>
						<label><?php esc_html_e( 'Categoria', 'llm-con-tabelle' ); ?></label>
						<input type="text" list="llm-quiz-category-list" name="llm_quiz_q[<?php echo esc_attr( (string) $index ); ?>][category]" class="widefat llm-quiz-q-category" value="<?php echo esc_attr( $category ); ?>">
					</p>
					<p>
						<label><?php esc_html_e( 'Risposta corretta', 'llm-con-tabelle' ); ?></label>
						<select name="llm_quiz_q[<?php echo esc_attr( (string) $index ); ?>][correct]" class="widefat llm-quiz-q-correct">
							<?php for ( $i = 0; $i < LLM_Quiz::ANSWERS_PER_QUESTION; $i++ ) : ?>
								<option value="<?php echo (int) $i; ?>" <?php selected( $correct, $i ); ?>><?php echo esc_html( chr( 65 + $i ) ); ?></option>
							<?php endfor; ?>
						</select>
					</p>
				</div>
				<p>
					<label><?php esc_html_e( 'Testo domanda', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_quiz_q[<?php echo esc_attr( (string) $index ); ?>][question]" class="widefat llm-quiz-q-text" rows="2"><?php echo esc_textarea( $question ); ?></textarea>
				</p>
				<div class="llm-quiz-admin__answers">
					<?php for ( $i = 0; $i < LLM_Quiz::ANSWERS_PER_QUESTION; $i++ ) : ?>
						<?php
						$a_text = isset( $answers[ $i ]['text'] ) ? (string) $answers[ $i ]['text'] : '';
						$a_exp  = isset( $answers[ $i ]['explanation'] ) ? (string) $answers[ $i ]['explanation'] : '';
						$let    = chr( 65 + $i );
						?>
						<div class="llm-quiz-admin__answer<?php echo $correct === $i ? ' is-correct' : ''; ?>">
							<p>
								<label>
									<span class="llm-quiz-admin__ans-letter"><?php echo esc_html( $let ); ?></span>
									<?php esc_html_e( 'Risposta', 'llm-con-tabelle' ); ?>
								</label>
								<input type="text" class="widefat" name="llm_quiz_q[<?php echo esc_attr( (string) $index ); ?>][answers][<?php echo (int) $i; ?>][text]" value="<?php echo esc_attr( $a_text ); ?>">
							</p>
							<p>
								<label><?php esc_html_e( 'Spiegazione', 'llm-con-tabelle' ); ?></label>
								<textarea class="widefat" name="llm_quiz_q[<?php echo esc_attr( (string) $index ); ?>][answers][<?php echo (int) $i; ?>][explanation]" rows="2"><?php echo esc_textarea( $a_exp ); ?></textarea>
							</p>
						</div>
					<?php endfor; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int     $post_id ID.
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

		$known  = isset( $_POST['llm_quiz_known'] ) ? sanitize_key( wp_unslash( $_POST['llm_quiz_known'] ) ) : '';
		$target = isset( $_POST['llm_quiz_target'] ) ? sanitize_key( wp_unslash( $_POST['llm_quiz_target'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		if ( ! LLM_Languages::is_valid( $target ) ) {
			$target = '';
		}
		if ( $known && $target && $known === $target ) {
			$target = '';
		}

		update_post_meta( $post_id, LLM_Quiz::META_KNOWN, $known );
		update_post_meta( $post_id, LLM_Quiz::META_TARGET, $target );

		$raw_q = array();
		if ( ! empty( $_POST['llm_quiz_q'] ) && is_array( $_POST['llm_quiz_q'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalizzato sotto.
			$raw_q = wp_unslash( $_POST['llm_quiz_q'] );
		}
		LLM_Quiz::save_questions( $post_id, is_array( $raw_q ) ? $raw_q : array() );
	}

	public static function ajax_import_csv() {
		if ( ! check_ajax_referer( self::AJAX_IMPORT, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'llm-con-tabelle' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'llm-con-tabelle' ) ), 403 );
		}
		if ( LLM_Quiz::CPT !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Post non valido.', 'llm-con-tabelle' ) ), 400 );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'append';
		if ( ! in_array( $mode, array( 'append', 'replace' ), true ) ) {
			$mode = 'append';
		}

		/* Non usare sanitize_textarea_field: toglierebbe le virgolette del CSV. */
		$csv = isset( $_POST['csv'] ) ? wp_unslash( (string) $_POST['csv'] ) : '';
		$csv = str_replace( "\0", '', $csv );

		$parsed = LLM_Quiz::parse_csv( $csv );
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 400 );
		}

		if ( 'replace' === $mode ) {
			$merged = $parsed;
		} else {
			$merged = array_merge( LLM_Quiz::get_questions( $post_id ), $parsed );
		}
		LLM_Quiz::save_questions( $post_id, $merged );

		$html = self::render_questions_list_html( LLM_Quiz::get_questions( $post_id ) );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of questions */
					__( 'Import ok. Ora ci sono %d domande. Ricorda di aggiornare il post se modifichi altro.', 'llm-con-tabelle' ),
					count( LLM_Quiz::get_questions( $post_id ) )
				),
				'count'   => count( LLM_Quiz::get_questions( $post_id ) ),
				'html'    => $html,
			)
		);
	}

	/**
	 * @param array<string,string> $columns Colonne.
	 * @return array<string,string>
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['llm_quiz_pair']  = __( 'Coppia', 'llm-con-tabelle' );
				$out['llm_quiz_count'] = __( 'Domande', 'llm-con-tabelle' );
			}
		}
		return $out;
	}

	/**
	 * @param string $column  Colonna.
	 * @param int    $post_id ID.
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'llm_quiz_pair':
				$known  = LLM_Quiz::get_known( $post_id );
				$target = LLM_Quiz::get_target( $post_id );
				if ( $known && $target ) {
					echo esc_html( LLM_Languages::label( $known ) . ' → ' . LLM_Languages::label( $target ) );
				} else {
					echo '—';
				}
				break;
			case 'llm_quiz_count':
				echo esc_html( (string) count( LLM_Quiz::get_questions( $post_id ) ) );
				break;
		}
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $which     Top/bottom.
	 */
	public static function list_filters( $post_type, $which = '' ) {
		if ( LLM_Quiz::CPT !== $post_type ) {
			return;
		}
		$current = isset( $_GET['llm_quiz_pair'] ) ? sanitize_text_field( wp_unslash( $_GET['llm_quiz_pair'] ) ) : '';
		echo '<select name="llm_quiz_pair">';
		echo '<option value="">' . esc_html__( 'Tutte le coppie', 'llm-con-tabelle' ) . '</option>';
		foreach ( LLM_Magazine::all_pairs() as $pair ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $pair['key'] ),
				selected( $current, $pair['key'], false ),
				esc_html( $pair['label'] )
			);
		}
		echo '</select>';
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function filter_list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( LLM_Quiz::CPT !== $query->get( 'post_type' ) ) {
			return;
		}
		$pair = isset( $_GET['llm_quiz_pair'] ) ? sanitize_text_field( wp_unslash( $_GET['llm_quiz_pair'] ) ) : '';
		if ( ! preg_match( '/^[a-z]{2}-[a-z]{2}$/', $pair ) ) {
			return;
		}
		list( $known, $target ) = explode( '-', $pair );
		$query->set(
			'meta_query',
			array(
				'relation' => 'AND',
				array(
					'key'   => LLM_Quiz::META_KNOWN,
					'value' => $known,
				),
				array(
					'key'   => LLM_Quiz::META_TARGET,
					'value' => $target,
				),
			)
		);
	}
}
