<?php
/**
 * Meta box storia LLM: impostazioni in post meta; frasi/media in tabelle.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Story {

	const NONCE_ACTION = 'llm_story_save';
	const NONCE_NAME   = 'llm_story_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLM_STORY_CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'before_delete_post' ) );
		add_filter( 'manage_' . LLM_STORY_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLM_STORY_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_edit_box' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'story_list_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_story_list_query' ) );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || LLM_STORY_CPT !== $screen->post_type ) {
			return;
		}

		// CSS: sia nella lista che nell'editor
		wp_enqueue_style(
			'llm-admin-story',
			LLM_TABELLE_URL . 'assets/llm-admin.css',
			array(),
			LLM_TABELLE_VERSION
		);

		if ( 'edit.php' === $hook ) {
			// Pagina lista: serve solo media uploader + script quick edit
			wp_enqueue_media();
			wp_enqueue_script(
				'llm-admin-story',
				LLM_TABELLE_URL . 'assets/llm-admin.js',
				array( 'jquery' ),
				LLM_TABELLE_VERSION,
				true
			);
			wp_localize_script(
				'llm-admin-story',
				'llmAdmin',
				array(
					'selectImage' => __( 'Scegli immagine', 'llm-con-tabelle' ),
					'changeImage' => __( 'Cambia immagine', 'llm-con-tabelle' ),
					'useImage'    => __( 'Usa questa immagine', 'llm-con-tabelle' ),
				)
			);
			return;
		}

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'llm-admin-story',
			LLM_TABELLE_URL . 'assets/llm-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			LLM_TABELLE_VERSION,
			true
		);
		$post_id = isset( $GLOBALS['post']->ID ) ? (int) $GLOBALS['post']->ID : 0;

		wp_localize_script(
			'llm-admin-story',
			'llmAdmin',
			array(
				'selectImage'      => __( 'Scegli immagine', 'llm-con-tabelle' ),
				'changeImage'      => __( 'Cambia immagine', 'llm-con-tabelle' ),
				'useImage'         => __( 'Usa questa immagine', 'llm-con-tabelle' ),
				'removeRow'        => __( 'Rimuovi', 'llm-con-tabelle' ),
				'beforeAllPhrases' => __( 'Prima di tutte le frasi', 'llm-con-tabelle' ),
				'phraseLabel'      => __( 'Frase', 'llm-con-tabelle' ),
				'emptyPhraseHint'  => __( '(nessun testo interfaccia)', 'llm-con-tabelle' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'postId'           => $post_id,
				'csvNonce'         => wp_create_nonce( LLM_Story_Phrases_Csv::NONCE_ACTION ),
				'csvNoncePost'     => $post_id ? wp_create_nonce( LLM_Story_Phrases_Csv::NONCE_ACTION . '_' . $post_id ) : '',
				'csvExportUrl'     => $post_id ? LLM_Story_Phrases_Csv::export_url( $post_id ) : '',
				'csvPreviewAction' => 'llm_story_phrases_preview_import',
				'csvCommitAction'  => 'llm_story_phrases_commit_import',
				'csvColPos'        => __( 'N.', 'llm-con-tabelle' ),
				'csvColAction'     => __( 'Operazione', 'llm-con-tabelle' ),
				'csvColIface'      => __( 'Frase (interfaccia)', 'llm-con-tabelle' ),
				'csvColTarget'     => __( 'Frase (obiettivo)', 'llm-con-tabelle' ),
				'csvColGrammar'    => __( 'Analisi grammaticale', 'llm-con-tabelle' ),
				'csvColAlt'        => __( 'Traduzione alternativa', 'llm-con-tabelle' ),
				'csvModalTitle'    => __( 'Anteprima importazione CSV', 'llm-con-tabelle' ),
				'csvSummary'       => __( 'Riepilogo: %1$d sostituzioni, %2$d aggiunte.', 'llm-con-tabelle' ),
				'csvBtnImport'     => __( 'Scegli file CSV…', 'llm-con-tabelle' ),
				'csvBtnExport'     => __( 'Esporta CSV', 'llm-con-tabelle' ),
				'csvBtnCancel'     => __( 'Annulla', 'llm-con-tabelle' ),
				'csvBtnConfirm'    => __( 'Salva importazione', 'llm-con-tabelle' ),
				'csvBtnClose'      => __( 'Chiudi', 'llm-con-tabelle' ),
				'csvLogTitle'      => __( 'Log importazione', 'llm-con-tabelle' ),
				'csvLoading'       => __( 'Elaborazione…', 'llm-con-tabelle' ),
				'csvPasteEmpty'    => __( 'Incolla prima il testo CSV.', 'llm-con-tabelle' ),
				'csvErrGeneric'    => __( 'Operazione non riuscita.', 'llm-con-tabelle' ),
				'csvNeedSaveDraft' => __( 'Salva prima la bozza per usare import/export CSV.', 'llm-con-tabelle' ),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_story_settings',
			__( 'Impostazioni storia', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_settings' ),
			LLM_STORY_CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'llm_story_phrases',
			__( 'Frasi (ordinate, database)', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_phrases' ),
			LLM_STORY_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'llm_story_media',
			__( 'Immagini nel flusso (database)', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_media' ),
			LLM_STORY_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'llm_story_preview',
			__( 'Anteprima struttura', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_preview' ),
			LLM_STORY_CPT,
			'side',
			'default'
		);
	}

	public static function render_settings( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$known   = get_post_meta( $post->ID, LLM_Story_Meta::KNOWN_LANG, true );
		$target  = get_post_meta( $post->ID, LLM_Story_Meta::TARGET_LANG, true );
		$title_t = get_post_meta( $post->ID, LLM_Story_Meta::TITLE_TARGET, true );
		$plot    = get_post_meta( $post->ID, LLM_Story_Meta::STORY_PLOT, true );
		$cost    = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_COST, true );
		$reward  = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_REWARD, true );

		$langs = LLM_Languages::get_codes();
		?>
		<div class="llm-field-row">
			<label for="llm_known_lang"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_known_lang" id="llm_known_lang" class="widefat">
				<option value=""><?php esc_html_e( '— non impostata —', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="llm-field-row">
			<label for="llm_target_lang"><strong><?php esc_html_e( 'Lingua da imparare (obiettivo)', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_target_lang" id="llm_target_lang" class="widefat">
				<option value=""><?php esc_html_e( '— non impostata —', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="llm-field-row">
			<label for="llm_title_target_lang"><strong><?php esc_html_e( 'Titolo nella lingua obiettivo', 'llm-con-tabelle' ); ?></strong></label>
			<input type="text" class="widefat" name="llm_title_target_lang" id="llm_title_target_lang" value="<?php echo esc_attr( $title_t ); ?>" />
		</div>
		<div class="llm-field-row">
			<label for="llm_story_plot"><strong><?php esc_html_e( 'Trama della storia', 'llm-con-tabelle' ); ?></strong></label>
			<textarea name="llm_story_plot" id="llm_story_plot" class="widefat" rows="5"><?php echo esc_textarea( is_string( $plot ) ? $plot : '' ); ?></textarea>
		</div>
		<hr />
		<div class="llm-field-row llm-field-inline">
			<div>
				<label for="llm_story_coin_cost"><strong><?php esc_html_e( 'Costo coin (sblocco)', 'llm-con-tabelle' ); ?></strong></label>
				<input type="number" min="0" step="1" name="llm_story_coin_cost" id="llm_story_coin_cost" value="<?php echo esc_attr( (string) $cost ); ?>" />
			</div>
			<div>
				<label for="llm_story_coin_reward"><strong><?php esc_html_e( 'Premio coin (completamento)', 'llm-con-tabelle' ); ?></strong></label>
				<input type="number" min="0" step="1" name="llm_story_coin_reward" id="llm_story_coin_reward" value="<?php echo esc_attr( (string) $reward ); ?>" />
			</div>
		</div>
		<?php
	}

	public static function render_phrases( $post ) {
		$phrases = LLM_Story_Repository::get_phrases( $post->ID );
		if ( empty( $phrases ) ) {
			$phrases = array(
				array(
					'interface' => '',
					'target'    => '',
					'grammar'   => '',
					'alt'       => '',
				),
			);
		}
		?>
		<p class="description"><?php esc_html_e( 'Salvate in tabella dedicata (nessun JSON). Trascina per riordinare.', 'llm-con-tabelle' ); ?></p>
		<p class="llm-phrases-csv-toolbar">
			<?php if ( $post->ID > 0 ) : ?>
				<a href="<?php echo esc_url( LLM_Story_Phrases_Csv::export_url( $post->ID ) ); ?>" class="button" id="llm-phrases-csv-export"><?php esc_html_e( 'Esporta CSV', 'llm-con-tabelle' ); ?></a>
				<label for="llm-phrases-csv-file" class="button" id="llm-phrases-csv-import"><?php esc_html_e( 'Importa CSV…', 'llm-con-tabelle' ); ?></label>
				<input type="file" id="llm-phrases-csv-file" accept=".csv,text/csv" tabindex="-1" class="llm-csv-file-input-hidden" />
				<button type="button" class="button" id="llm-phrases-csv-paste-toggle" aria-expanded="false" aria-controls="llm-phrases-csv-paste-panel"><?php esc_html_e( 'Incolla CSV…', 'llm-con-tabelle' ); ?></button>
			<?php else : ?>
				<span class="description"><?php esc_html_e( 'Salva la bozza per abilitare importazione ed esportazione CSV delle frasi.', 'llm-con-tabelle' ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( $post->ID > 0 ) : ?>
		<div id="llm-phrases-csv-paste-panel" class="llm-phrases-csv-paste-panel" hidden>
			<p class="description"><?php esc_html_e( 'Incolla qui il contenuto del CSV (delimitatore punto e virgola, come dall’esportazione). Puoi includere tag HTML per la formattazione (stessi criteri di sicurezza dei contenuti del sito). Poi usa «Anteprima importazione».', 'llm-con-tabelle' ); ?></p>
			<label for="llm-phrases-csv-paste" class="screen-reader-text"><?php esc_html_e( 'Contenuto CSV', 'llm-con-tabelle' ); ?></label>
			<textarea id="llm-phrases-csv-paste" class="large-text code" rows="12" spellcheck="false"></textarea>
			<p class="llm-phrases-csv-paste-actions">
				<button type="button" class="button button-primary" id="llm-phrases-csv-paste-preview"><?php esc_html_e( 'Anteprima importazione', 'llm-con-tabelle' ); ?></button>
			</p>
		</div>
		<?php endif; ?>
		<div id="llm-phrases-list" class="llm-sortable-list">
			<?php
			foreach ( $phrases as $i => $p ) {
				self::render_phrase_row( $i, $p );
			}
			?>
		</div>
		<p>
			<button type="button" class="button" id="llm-add-phrase"><?php esc_html_e( 'Aggiungi frase', 'llm-con-tabelle' ); ?></button>
		</p>
		<script type="text/template" id="llm-phrase-template">
			<?php self::render_phrase_row( '{{IDX}}', array( 'interface' => '', 'target' => '', 'grammar' => '', 'alt' => '' ) ); ?>
		</script>
		<?php if ( $post->ID > 0 ) : ?>
		<div id="llm-phrases-csv-modal" class="llm-csv-modal" hidden aria-hidden="true">
			<div class="llm-csv-modal__backdrop" tabindex="-1"></div>
			<div class="llm-csv-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="llm-phrases-csv-modal-title">
				<div class="llm-csv-modal__head">
					<h2 id="llm-phrases-csv-modal-title" class="llm-csv-modal__title"><?php esc_html_e( 'Anteprima importazione CSV', 'llm-con-tabelle' ); ?></h2>
					<button type="button" class="button-link llm-csv-modal__x" id="llm-phrases-csv-modal-close" aria-label="<?php esc_attr_e( 'Chiudi', 'llm-con-tabelle' ); ?>">×</button>
				</div>
				<div class="llm-csv-modal__body">
					<div id="llm-phrases-csv-modal-step-preview">
						<p id="llm-phrases-csv-summary" class="llm-csv-summary"></p>
						<ul id="llm-phrases-csv-warnings" class="llm-csv-warnings"></ul>
						<div class="llm-csv-table-wrap">
							<table class="widefat striped llm-csv-preview-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'N.', 'llm-con-tabelle' ); ?></th>
										<th><?php esc_html_e( 'Operazione', 'llm-con-tabelle' ); ?></th>
										<th><?php esc_html_e( 'Frase (interfaccia)', 'llm-con-tabelle' ); ?></th>
										<th><?php esc_html_e( 'Frase (obiettivo)', 'llm-con-tabelle' ); ?></th>
										<th><?php esc_html_e( 'Analisi grammaticale', 'llm-con-tabelle' ); ?></th>
										<th><?php esc_html_e( 'Traduzione alternativa', 'llm-con-tabelle' ); ?></th>
									</tr>
								</thead>
								<tbody id="llm-phrases-csv-preview-rows"></tbody>
							</table>
						</div>
					</div>
					<div id="llm-phrases-csv-modal-step-log" hidden>
						<p class="llm-csv-log-title"><?php esc_html_e( 'Log importazione', 'llm-con-tabelle' ); ?></p>
						<pre id="llm-phrases-csv-log" class="llm-csv-log"></pre>
					</div>
				</div>
				<div class="llm-csv-modal__foot" id="llm-phrases-csv-modal-foot-preview">
					<button type="button" class="button" id="llm-phrases-csv-cancel"><?php esc_html_e( 'Annulla', 'llm-con-tabelle' ); ?></button>
					<button type="button" class="button button-primary" id="llm-phrases-csv-confirm"><?php esc_html_e( 'Salva importazione', 'llm-con-tabelle' ); ?></button>
				</div>
				<div class="llm-csv-modal__foot" id="llm-phrases-csv-modal-foot-done" hidden>
					<button type="button" class="button button-primary" id="llm-phrases-csv-done"><?php esc_html_e( 'Chiudi', 'llm-con-tabelle' ); ?></button>
				</div>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	private static function render_phrase_row( $index, array $p ) {
		$i = (string) $index;
		if ( is_numeric( $index ) ) {
			$num = (string) ( (int) $index + 1 );
		} else {
			$num = '{{NUM}}';
		}
		$iface = isset( $p['interface'] ) ? $p['interface'] : '';
		$prev  = self::phrase_preview_text( $iface );
		?>
		<div class="llm-phrase-row">
			<span class="llm-drag-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Trascina', 'llm-con-tabelle' ); ?>"></span>
			<details class="llm-phrase-details">
				<summary class="llm-phrase-summary">
					<span class="llm-phrase-summary-title">
						<?php echo esc_html( __( 'Frase', 'llm-con-tabelle' ) ); ?>
						<span class="llm-phrase-num"><?php echo esc_html( $num ); ?></span>
					</span>
					<span class="llm-phrase-preview"><?php echo esc_html( $prev ); ?></span>
				</summary>
				<div class="llm-phrase-fields">
					<label><?php esc_html_e( 'Frase (lingua interfaccia)', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][interface]" class="widefat llm-phrase-interface" rows="2"><?php echo esc_textarea( $iface ); ?></textarea>
					<label><?php esc_html_e( 'Frase (lingua obiettivo)', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][target]" class="widefat" rows="2"><?php echo esc_textarea( isset( $p['target'] ) ? $p['target'] : '' ); ?></textarea>
					<label><?php esc_html_e( 'Analisi grammaticale', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][grammar]" class="widefat" rows="3"><?php echo esc_textarea( isset( $p['grammar'] ) ? $p['grammar'] : '' ); ?></textarea>
					<label><?php esc_html_e( 'Traduzione alternativa', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][alt]" class="widefat" rows="2"><?php echo esc_textarea( isset( $p['alt'] ) ? $p['alt'] : '' ); ?></textarea>
					<button type="button" class="button-link llm-remove-phrase"><?php esc_html_e( 'Rimuovi frase', 'llm-con-tabelle' ); ?></button>
				</div>
			</details>
		</div>
		<?php
	}

	private static function phrase_preview_text( $interface ) {
		$t = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $interface ) ) );
		if ( $t === '' ) {
			return __( '(nessun testo interfaccia)', 'llm-con-tabelle' );
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > 90 ) {
			return mb_substr( $t, 0, 87 ) . '…';
		}
		if ( strlen( $t ) > 90 ) {
			return substr( $t, 0, 87 ) . '…';
		}
		return $t;
	}

	public static function render_media( $post ) {
		$blocks  = LLM_Story_Repository::get_media_blocks( $post->ID );
		$phrases = LLM_Story_Repository::get_phrases( $post->ID );
		$n       = max( 0, count( $phrases ) );
		?>
		<p class="description"><?php esc_html_e( 'Salvate in tabella dedicata.', 'llm-con-tabelle' ); ?></p>
		<div id="llm-media-list" data-phrase-label-after="<?php echo esc_attr__( 'Dopo la frase %d', 'llm-con-tabelle' ); ?>">
			<?php
			if ( empty( $blocks ) ) {
				self::render_media_row( 0, 0, -1, $n );
			} else {
				foreach ( $blocks as $i => $b ) {
					$aid = isset( $b['attachment_id'] ) ? (int) $b['attachment_id'] : 0;
					$pos = isset( $b['after_phrase_index'] ) ? (int) $b['after_phrase_index'] : -1;
					self::render_media_row( $i, $aid, $pos, $n );
				}
			}
			?>
		</div>
		<p>
			<button type="button" class="button" id="llm-add-media"><?php esc_html_e( 'Aggiungi immagine', 'llm-con-tabelle' ); ?></button>
		</p>
		<script type="text/template" id="llm-media-template">
			<?php self::render_media_row( '{{IDX}}', 0, -1, $n ); ?>
		</script>
		<?php
	}

	private static function render_media_row( $index, $attachment_id, $after_index, $phrase_count = 0 ) {
		$i   = (string) $index;
		$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		?>
		<div class="llm-media-row" data-llm-media-row>
			<div class="llm-media-thumb">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<input type="hidden" name="llm_media_blocks[<?php echo esc_attr( $i ); ?>][attachment_id]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" class="llm-attachment-id" />
			<div class="llm-media-actions">
				<button type="button" class="button llm-pick-image"><?php esc_html_e( 'Scegli immagine', 'llm-con-tabelle' ); ?></button>
				<button type="button" class="button-link llm-remove-media"><?php esc_html_e( 'Rimuovi', 'llm-con-tabelle' ); ?></button>
			</div>
			<label><?php esc_html_e( 'Posizione nel flusso', 'llm-con-tabelle' ); ?></label>
			<select name="llm_media_blocks[<?php echo esc_attr( $i ); ?>][after_phrase_index]" class="llm-after-phrase widefat">
				<option value="-1" <?php selected( (int) $after_index, -1 ); ?>><?php esc_html_e( 'Prima di tutte le frasi', 'llm-con-tabelle' ); ?></option>
				<?php
				$count = max( (int) $phrase_count, 0 );
				for ( $k = 0; $k < $count; $k++ ) {
					printf(
						'<option value="%1$d" %3$s>%2$s</option>',
						(int) $k,
						esc_html( sprintf( __( 'Dopo la frase %d', 'llm-con-tabelle' ), $k + 1 ) ),
						selected( (int) $after_index, $k, false )
					);
				}
				?>
			</select>
		</div>
		<?php
	}

	public static function render_preview( $post ) {
		$known   = get_post_meta( $post->ID, LLM_Story_Meta::KNOWN_LANG, true );
		$target  = get_post_meta( $post->ID, LLM_Story_Meta::TARGET_LANG, true );
		$title_t = get_post_meta( $post->ID, LLM_Story_Meta::TITLE_TARGET, true );
		$plot    = get_post_meta( $post->ID, LLM_Story_Meta::STORY_PLOT, true );
		$phrases = LLM_Story_Repository::get_phrases( $post->ID );
		$media   = LLM_Story_Repository::get_media_blocks( $post->ID );
		$cost    = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_COST, true );
		$reward  = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_REWARD, true );

		echo '<p><strong>' . esc_html__( 'Titolo post', 'llm-con-tabelle' ) . ':</strong> ' . esc_html( get_the_title( $post ) ) . '</p>';
		if ( $title_t !== '' ) {
			echo '<p><strong>' . esc_html__( 'Titolo obiettivo', 'llm-con-tabelle' ) . ':</strong> ' . esc_html( $title_t ) . '</p>';
		}
		if ( is_string( $plot ) && $plot !== '' ) {
			echo '<p><strong>' . esc_html__( 'Trama', 'llm-con-tabelle' ) . ':</strong><br />' . esc_html( wp_trim_words( $plot, 40 ) ) . '</p>';
		}
		echo '<p><strong>' . esc_html__( 'Lingue', 'llm-con-tabelle' ) . ':</strong> ';
		echo esc_html( $known ? LLM_Languages::label( $known ) : '—' );
		echo ' → ';
		echo esc_html( $target ? LLM_Languages::label( $target ) : '—' );
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Coin', 'llm-con-tabelle' ) . ':</strong> ';
		echo esc_html( sprintf( __( 'costo %1$d · premio %2$d', 'llm-con-tabelle' ), $cost, $reward ) );
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Frasi (in DB)', 'llm-con-tabelle' ) . ':</strong> ' . esc_html( (string) count( $phrases ) ) . '</p>';

		if ( empty( $phrases ) && empty( $media ) ) {
			echo '<p class="description">' . esc_html__( 'Aggiungi frasi e immagini per vedere il flusso.', 'llm-con-tabelle' ) . '</p>';
			return;
		}

		$flow = self::build_flow_preview( $phrases, $media );
		echo '<ol class="llm-preview-flow">';
		foreach ( $flow as $item ) {
			if ( 'image' === $item['type'] ) {
				$url = wp_get_attachment_image_url( $item['id'], 'thumbnail' );
				echo '<li class="llm-preview-image">';
				if ( $url ) {
					echo '<img src="' . esc_url( $url ) . '" alt="" /> ';
				}
				echo '<span class="description">#' . esc_html( (string) $item['id'] ) . '</span>';
				echo '</li>';
			} else {
				$idx = $item['index'];
				$pi  = isset( $phrases[ $idx ] ) ? $phrases[ $idx ] : array();
				$if  = isset( $pi['interface'] ) ? $pi['interface'] : '';
				$tg  = isset( $pi['target'] ) ? $pi['target'] : '';
				echo '<li class="llm-preview-phrase"><strong>' . esc_html( sprintf( __( 'Frase %d', 'llm-con-tabelle' ), $idx + 1 ) ) . '</strong>';
				if ( $if !== '' ) {
					echo '<br /><em>' . esc_html( wp_trim_words( $if, 20 ) ) . '</em>';
				}
				if ( $tg !== '' ) {
					echo '<br /><span class="description">' . esc_html( wp_trim_words( $tg, 20 ) ) . '</span>';
				}
				echo '</li>';
			}
		}
		echo '</ol>';
		if ( $post->post_status === 'publish' ) {
			$link = get_permalink( $post );
			if ( $link ) {
				echo '<p><a href="' . esc_url( $link ) . '" class="button button-secondary" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Apri sul sito', 'llm-con-tabelle' ) . '</a></p>';
			}
		}
	}

	private static function build_flow_preview( array $phrases, array $media ) {
		$items = array();
		foreach ( $media as $b ) {
			$aid = isset( $b['attachment_id'] ) ? (int) $b['attachment_id'] : 0;
			$ap  = isset( $b['after_phrase_index'] ) ? (int) $b['after_phrase_index'] : -1;
			if ( $aid ) {
				$items[] = array(
					'type'  => 'image',
					'id'    => $aid,
					'after' => $ap,
				);
			}
		}
		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['after'] === $b['after'] ) {
					return 0;
				}
				return ( $a['after'] < $b['after'] ) ? -1 : 1;
			}
		);

		$by_after = array();
		foreach ( $items as $it ) {
			$k = (int) $it['after'];
			if ( ! isset( $by_after[ $k ] ) ) {
				$by_after[ $k ] = array();
			}
			$by_after[ $k ][] = $it;
		}

		$flow   = array();
		$pcount = count( $phrases );
		if ( ! empty( $by_after[-1] ) ) {
			foreach ( $by_after[-1] as $im ) {
				$flow[] = array( 'type' => 'image', 'id' => $im['id'] );
			}
		}
		for ( $i = 0; $i < $pcount; $i++ ) {
			$flow[] = array( 'type' => 'phrase', 'index' => $i );
			if ( ! empty( $by_after[ $i ] ) ) {
				foreach ( $by_after[ $i ] as $im ) {
					$flow[] = array( 'type' => 'image', 'id' => $im['id'] );
				}
			}
		}
		return $flow;
	}

	/**
	 * Box immagine anteprima nella modifica rapida.
	 */
	public static function quick_edit_box( $column_name, $post_type ) {
		if ( 'llm_thumbnail' !== $column_name || LLM_STORY_CPT !== $post_type ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-left llm-qe-wrap">
			<div class="inline-edit-col">
				<span class="title"><?php esc_html_e( 'Immagine anteprima', 'llm-con-tabelle' ); ?></span>
				<div class="llm-qe-thumb-preview">
					<div class="llm-qe-thumb-img"></div>
					<div class="llm-qe-thumb-buttons">
						<button type="button" class="button llm-qe-pick-image">
							<?php esc_html_e( 'Cambia immagine', 'llm-con-tabelle' ); ?>
						</button>
						<button type="button" class="button-link llm-qe-remove-image">
							<?php esc_html_e( 'Rimuovi immagine', 'llm-con-tabelle' ); ?>
						</button>
					</div>
				</div>
				<input type="hidden" name="llm_quick_thumbnail_id" class="llm-qe-thumbnail-id" value="-1" />
				<?php wp_nonce_field( 'llm_quick_edit_thumbnail', 'llm_qe_thumb_nonce' ); ?>
			</div>
		</fieldset>
		<?php
	}

	public static function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Modifica rapida: salva solo la thumbnail
		if ( isset( $_POST['llm_qe_thumb_nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['llm_qe_thumb_nonce'] ) ), 'llm_quick_edit_thumbnail' ) ) {
				$thumb_id = isset( $_POST['llm_quick_thumbnail_id'] ) ? (int) $_POST['llm_quick_thumbnail_id'] : -1;
				if ( $thumb_id > 0 ) {
					set_post_thumbnail( $post_id, $thumb_id );
				} elseif ( 0 === $thumb_id ) {
					delete_post_thumbnail( $post_id );
				}
				// -1 = invariato, non fare nulla
			}
			return;
		}

		// Editor completo
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		$known = isset( $_POST['llm_known_lang'] ) ? sanitize_key( wp_unslash( $_POST['llm_known_lang'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		$target = isset( $_POST['llm_target_lang'] ) ? sanitize_key( wp_unslash( $_POST['llm_target_lang'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $target ) ) {
			$target = '';
		}

		update_post_meta( $post_id, LLM_Story_Meta::KNOWN_LANG, $known );
		update_post_meta( $post_id, LLM_Story_Meta::TARGET_LANG, $target );
		update_post_meta( $post_id, LLM_Story_Meta::TITLE_TARGET, isset( $_POST['llm_title_target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_title_target_lang'] ) ) : '' );
		update_post_meta(
			$post_id,
			LLM_Story_Meta::STORY_PLOT,
			isset( $_POST['llm_story_plot'] ) ? LLM_Story_Meta::sanitize_plot( wp_unslash( $_POST['llm_story_plot'] ) ) : ''
		);

		$cost   = isset( $_POST['llm_story_coin_cost'] ) ? LLM_Story_Meta::sanitize_coin( wp_unslash( $_POST['llm_story_coin_cost'] ) ) : 0;
		$reward = isset( $_POST['llm_story_coin_reward'] ) ? LLM_Story_Meta::sanitize_coin( wp_unslash( $_POST['llm_story_coin_reward'] ) ) : 0;
		update_post_meta( $post_id, LLM_Story_Meta::COIN_COST, $cost );
		update_post_meta( $post_id, LLM_Story_Meta::COIN_REWARD, $reward );

		$phrases_raw = isset( $_POST['llm_phrases'] ) ? wp_unslash( $_POST['llm_phrases'] ) : array();
		$phrases     = LLM_Story_Repository::sanitize_phrases_from_post( $phrases_raw );
		LLM_Story_Repository::save_phrases( $post_id, $phrases );

		$media_raw = isset( $_POST['llm_media_blocks'] ) ? wp_unslash( $_POST['llm_media_blocks'] ) : array();
		$media     = LLM_Story_Repository::sanitize_media_from_post( $media_raw );
		LLM_Story_Repository::save_media_blocks( $post_id, $media );
	}

	/**
	 * @param int $post_id ID post in eliminazione.
	 */
	public static function before_delete_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return;
		}
		LLM_Story_Repository::delete_for_story( $post_id );
	}

	/**
	 * Dropdown filtri lingua nella lista storie (edit.php).
	 *
	 * @param string $post_type Post type della schermata.
	 * @param string $which     'top' | 'bottom' (da WP 4.6).
	 */
	public static function story_list_filters( $post_type, $which = '' ) {
		if ( LLM_STORY_CPT !== $post_type ) {
			return;
		}
		if ( $which && 'top' !== $which ) {
			return;
		}

		$codes = LLM_Languages::get_codes();
		$fk    = isset( $_GET['llm_filter_known'] ) ? sanitize_key( wp_unslash( $_GET['llm_filter_known'] ) ) : '';
		$ft    = isset( $_GET['llm_filter_target'] ) ? sanitize_key( wp_unslash( $_GET['llm_filter_target'] ) ) : '';
		if ( $fk && ! LLM_Languages::is_valid( $fk ) ) {
			$fk = '';
		}
		if ( $ft && ! LLM_Languages::is_valid( $ft ) ) {
			$ft = '';
		}

		echo '<label for="llm_filter_known" class="screen-reader-text">' . esc_html__( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ) . '</label>';
		echo '<select name="llm_filter_known" id="llm_filter_known">';
		echo '<option value="">' . esc_html__( 'Tutte — interfaccia', 'llm-con-tabelle' ) . '</option>';
		foreach ( $codes as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $fk, $code, false ) . '>' . esc_html( $label . ' (' . $code . ')' ) . '</option>';
		}
		echo '</select> ';

		echo '<label for="llm_filter_target" class="screen-reader-text">' . esc_html__( 'Lingua da imparare (obiettivo)', 'llm-con-tabelle' ) . '</label>';
		echo '<select name="llm_filter_target" id="llm_filter_target">';
		echo '<option value="">' . esc_html__( 'Tutte — obiettivo', 'llm-con-tabelle' ) . '</option>';
		foreach ( $codes as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $ft, $code, false ) . '>' . esc_html( $label . ' (' . $code . ')' ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Applica meta_query alla lista storie in base ai filtri GET.
	 *
	 * @param \WP_Query $query Query principale admin.
	 */
	public static function filter_story_list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		$qv = $query->get( 'post_type' );
		if ( LLM_STORY_CPT !== $qv && ! ( is_array( $qv ) && in_array( LLM_STORY_CPT, $qv, true ) ) ) {
			return;
		}

		$pto = get_post_type_object( LLM_STORY_CPT );
		if ( ! $pto || ! current_user_can( $pto->cap->edit_posts ) ) {
			return;
		}

		$fk = isset( $_GET['llm_filter_known'] ) ? sanitize_key( wp_unslash( $_GET['llm_filter_known'] ) ) : '';
		$ft = isset( $_GET['llm_filter_target'] ) ? sanitize_key( wp_unslash( $_GET['llm_filter_target'] ) ) : '';
		if ( $fk && ! LLM_Languages::is_valid( $fk ) ) {
			$fk = '';
		}
		if ( $ft && ! LLM_Languages::is_valid( $ft ) ) {
			$ft = '';
		}

		$clauses = array();
		if ( $fk ) {
			$clauses[] = array(
				'key'     => LLM_Story_Meta::KNOWN_LANG,
				'value'   => $fk,
				'compare' => '=',
				'type'    => 'CHAR',
			);
		}
		if ( $ft ) {
			$clauses[] = array(
				'key'     => LLM_Story_Meta::TARGET_LANG,
				'value'   => $ft,
				'compare' => '=',
				'type'    => 'CHAR',
			);
		}
		if ( empty( $clauses ) ) {
			return;
		}

		$existing = $query->get( 'meta_query' );
		$parts    = array();
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$parts[] = $existing;
		}
		foreach ( $clauses as $c ) {
			$parts[] = $c;
		}
		if ( count( $parts ) === 1 ) {
			$query->set( 'meta_query', $parts[0] );
		} else {
			$query->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $parts ) );
		}
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'cb' === $key ) {
				$new[ $key ]            = $label;
				$new['llm_thumbnail']   = __( 'Anteprima', 'llm-con-tabelle' );
				continue;
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['llm_langs']   = __( 'Lingue', 'llm-con-tabelle' );
				$new['llm_phrases'] = __( 'Frasi (DB)', 'llm-con-tabelle' );
				$new['llm_coins']   = __( 'Coin', 'llm-con-tabelle' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'llm_thumbnail':
				$thumb_id  = (int) get_post_thumbnail_id( $post_id );
				$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 60, 60 ) ) : '';
				echo '<div class="llm-col-thumb" data-thumbnail-id="' . esc_attr( (string) $thumb_id ) . '" data-thumbnail-url="' . esc_attr( $thumb_url ) . '">';
				if ( $thumb_url ) {
					echo '<img src="' . esc_url( $thumb_url ) . '" alt="" width="60" height="60" />';
				} else {
					echo '<span class="llm-no-thumb">—</span>';
				}
				echo '</div>';
				break;
			case 'llm_langs':
				$k = get_post_meta( $post_id, LLM_Story_Meta::KNOWN_LANG, true );
				$t = get_post_meta( $post_id, LLM_Story_Meta::TARGET_LANG, true );
				echo esc_html( ( $k ? $k : '—' ) . ' → ' . ( $t ? $t : '—' ) );
				break;
			case 'llm_phrases':
				echo esc_html( (string) count( LLM_Story_Repository::get_phrases( $post_id ) ) );
				break;
			case 'llm_coins':
				$c = (int) get_post_meta( $post_id, LLM_Story_Meta::COIN_COST, true );
				$r = (int) get_post_meta( $post_id, LLM_Story_Meta::COIN_REWARD, true );
				echo esc_html( sprintf( '%d / %d', $c, $r ) );
				break;
		}
	}
}
