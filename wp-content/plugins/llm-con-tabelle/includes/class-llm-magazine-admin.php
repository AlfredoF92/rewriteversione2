<?php
/**
 * Admin riviste: coppia, data, prima pagina, cruciverba, storie.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Magazine_Admin {

	const NONCE_ACTION = 'llm_magazine_save';
	const NONCE_NAME   = 'llm_magazine_nonce';
	const AJAX_SUGGEST = 'llm_magazine_suggest_quiz';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLM_Magazine::CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_SUGGEST, array( __CLASS__, 'ajax_suggest_quiz' ) );
		add_filter( 'manage_' . LLM_Magazine::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLM_Magazine::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list_query' ) );
	}

	private static function is_magazine_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && LLM_Magazine::CPT === $screen->post_type;
	}

	public static function enqueue( $hook ) {
		if ( ! self::is_magazine_screen() ) {
			return;
		}
		wp_enqueue_style(
			'llm-magazine-admin',
			LLM_TABELLE_URL . 'assets/llm-magazine-admin.css',
			array(),
			LLM_TABELLE_VERSION
		);
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'llm-magazine-admin',
			LLM_TABELLE_URL . 'assets/llm-magazine-admin.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-magazine-admin',
			'llmMagazineAdmin',
			array(
				'storyTitles'       => self::stories_section_titles_map(),
				'defaultStoryTitle' => __( 'Storie del giorno', 'llm-con-tabelle' ),
				'pickPairFirst'     => __( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ),
				'noStories'         => __( 'Nessuna storia nella categoria dedicata a questa coppia (escluse le storie “Brani musicali”).', 'llm-con-tabelle' ),
				'noMusic'           => __( 'Nessun brano: servono storie con la categoria di coppia e anche “Brani musicali”.', 'llm-con-tabelle' ),
				'noQuiz'            => __( 'Nessuna banca quiz per questa coppia. Creane una in Storie LLM → Quiz.', 'llm-con-tabelle' ),
				'noIdiom'           => __( 'Nessuna espressione per questa coppia. Creane in Storie LLM → Espressioni.', 'llm-con-tabelle' ),
				'shortcodeCopied'   => __( 'Shortcode copiato.', 'llm-con-tabelle' ),
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'suggestAction'     => self::AJAX_SUGGEST,
				'suggestNonce'      => wp_create_nonce( self::AJAX_SUGGEST ),
				'quizPerIssue'      => LLM_Magazine::QUIZ_PER_ISSUE,
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_magazine_settings',
			__( 'Impostazioni rivista', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_settings' ),
			LLM_Magazine::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'llm_magazine_author_note',
			__( 'Commento dell\'autore', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_author_note' ),
			LLM_Magazine::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'llm_magazine_rubriche',
			__( 'Rubriche', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_rubriche' ),
			LLM_Magazine::CPT,
			'normal',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Rivista.
	 */
	public static function render_settings( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$known    = LLM_Magazine::get_known( $post->ID );
		$target   = LLM_Magazine::get_target( $post->ID );
		$date     = LLM_Magazine::get_date( $post->ID );
		$homepage = LLM_Magazine::is_homepage( $post->ID );
		$langs    = LLM_Languages::get_codes();
		$shortcode = ( $known && $target && $known !== $target )
			? '[' . LLM_Magazine::homepage_shortcode_name( $known, $target ) . ']'
			: '';
		?>
		<div class="llm-mag-admin">
			<div class="llm-mag-admin__row">
				<div class="llm-mag-admin__field">
					<label for="llm_mag_known"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ); ?></strong></label>
					<select name="llm_mag_known" id="llm_mag_known" class="widefat" required>
						<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
						<?php foreach ( $langs as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="llm-mag-admin__field">
					<label for="llm_mag_target"><strong><?php esc_html_e( 'Lingua da imparare', 'llm-con-tabelle' ); ?></strong></label>
					<select name="llm_mag_target" id="llm_mag_target" class="widefat" required>
						<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
						<?php foreach ( $langs as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="llm-mag-admin__field">
					<label for="llm_mag_date"><strong><?php esc_html_e( 'Data di pubblicazione', 'llm-con-tabelle' ); ?></strong></label>
					<input type="date" name="llm_mag_date" id="llm_mag_date" class="widefat" value="<?php echo esc_attr( $date ); ?>">
					<p class="description"><?php esc_html_e( 'Di default è la data di creazione; puoi cambiarla.', 'llm-con-tabelle' ); ?></p>
				</div>
			</div>

			<div class="llm-mag-admin__homepage">
				<label>
					<input type="checkbox" name="llm_mag_homepage" id="llm_mag_homepage" value="1" <?php checked( $homepage ); ?>>
					<strong><?php esc_html_e( 'Pubblica in prima pagina', 'llm-con-tabelle' ); ?></strong>
				</label>
				<p class="description">
					<?php esc_html_e( 'Se attiva, questa rivista diventa quella mostrata dallo shortcode di prima pagina della coppia. Le altre riviste della stessa coppia perdono il flag.', 'llm-con-tabelle' ); ?>
				</p>
			</div>

			<?php if ( $shortcode ) : ?>
				<div class="llm-mag-admin__shortcode">
					<label for="llm-mag-shortcode"><?php esc_html_e( 'Shortcode prima pagina di questa coppia', 'llm-con-tabelle' ); ?></label>
					<div class="llm-mag-admin__shortcode-row">
						<input type="text" id="llm-mag-shortcode" readonly value="<?php echo esc_attr( $shortcode ); ?>">
						<button type="button" class="button" id="llm-mag-copy"><?php esc_html_e( 'Copia', 'llm-con-tabelle' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Opzionale: resta sulla pagina coppia (es. /it-english/). Ogni rivista ha anche un indirizzo proprio.', 'llm-con-tabelle' ); ?></p>
				</div>
			<?php else : ?>
				<p class="description" id="llm-mag-shortcode-hint"><?php esc_html_e( 'Scegli la coppia di lingue per vedere lo shortcode da usare nella pagina.', 'llm-con-tabelle' ); ?></p>
			<?php endif; ?>
			<?php
			$public_url = LLM_Magazine::url( (int) $post->ID );
			if ( $public_url && 'publish' === $post->post_status ) :
				?>
				<div class="llm-mag-admin__shortcode">
					<label for="llm-mag-permalink"><?php esc_html_e( 'Link pubblico di questa rivista', 'llm-con-tabelle' ); ?></label>
					<div class="llm-mag-admin__shortcode-row">
						<input type="text" id="llm-mag-permalink" readonly value="<?php echo esc_attr( $public_url ); ?>">
						<a class="button" href="<?php echo esc_url( $public_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Apri', 'llm-con-tabelle' ); ?></a>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Rivista.
	 */
	public static function render_author_note( $post ) {
		$note = LLM_Magazine::get_author_note( $post->ID );
		?>
		<div class="llm-mag-admin">
			<p class="description"><?php esc_html_e( 'Appare sopra il cruciverba nella pagina della rivista. Lascia vuoto per non mostrare il blocco.', 'llm-con-tabelle' ); ?></p>
			<div class="llm-mag-admin__row">
				<div class="llm-mag-admin__field">
					<label for="llm_mag_note_title"><strong><?php esc_html_e( 'Titolo', 'llm-con-tabelle' ); ?></strong></label>
					<input type="text" name="llm_mag_note_title" id="llm_mag_note_title" class="widefat" value="<?php echo esc_attr( $note['title'] ); ?>">
				</div>
				<div class="llm-mag-admin__field">
					<label for="llm_mag_note_name"><strong><?php esc_html_e( 'Nome dell\'autore', 'llm-con-tabelle' ); ?></strong></label>
					<input type="text" name="llm_mag_note_name" id="llm_mag_note_name" class="widefat" value="<?php echo esc_attr( $note['name'] ); ?>">
				</div>
			</div>
			<div class="llm-mag-admin__field">
				<label for="llm_mag_note_text"><strong><?php esc_html_e( 'Commento / descrizione', 'llm-con-tabelle' ); ?></strong></label>
				<textarea name="llm_mag_note_text" id="llm_mag_note_text" class="widefat" rows="6"><?php echo esc_textarea( $note['comment'] ); ?></textarea>
			</div>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Rivista.
	 */
	public static function render_rubriche( $post ) {
		$known          = LLM_Magazine::get_known( $post->ID );
		$target         = LLM_Magazine::get_target( $post->ID );
		$crossword_id   = LLM_Magazine::get_crossword_id( $post->ID );
		$selected       = LLM_Magazine::get_story_ids( $post->ID );
		$selected_music = LLM_Magazine::get_music_ids( $post->ID );
		$selected_quiz  = LLM_Magazine::get_quiz_question_ids( $post->ID );
		$crosswords     = LLM_Magazine::crossword_choices();
		$all_stories    = self::all_stories_for_admin();
		$pair_term_map  = self::pair_term_to_keys_map();
		$pair_cat       = ( $known && $target ) ? LLM_Magazine::pair_root_category( $known, $target ) : null;
		$music_cat      = LLM_Magazine::music_category();
		$current_key    = ( $known && $target ) ? LLM_Magazine::pair_key( $known, $target ) : '';
		$quiz_rows      = self::all_quiz_questions_for_admin( (int) $post->ID );

		/* Se non c’è ancora una selezione quiz e la coppia è nota, propone 3 automatiche. */
		if ( empty( $selected_quiz ) && $known && $target ) {
			$suggest       = LLM_Magazine::suggest_quiz_questions( $known, $target, LLM_Magazine::QUIZ_PER_ISSUE, (int) $post->ID );
			$selected_quiz = $suggest['question_ids'];
		}
		?>
		<div class="llm-mag-admin llm-mag-admin--rubriche">
			<div class="llm-mag-admin__section">
				<h3><?php esc_html_e( 'Cruciverba del giorno', 'llm-con-tabelle' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Seleziona un cruciverba della stessa coppia di lingue della rivista.', 'llm-con-tabelle' ); ?></p>
				<p class="llm-mag-admin__stories-empty" id="llm-mag-crossword-empty" <?php echo ( $known && $target ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ); ?>
				</p>
				<select name="llm_mag_crossword" id="llm_mag_crossword" class="widefat" data-current="<?php echo esc_attr( (string) $crossword_id ); ?>" <?php echo ( $known && $target ) ? '' : 'hidden'; ?>>
					<option value="0"><?php esc_html_e( '— Nessun cruciverba —', 'llm-con-tabelle' ); ?></option>
					<?php foreach ( $crosswords as $id => $cw ) : ?>
						<?php
						$pair_attr = ! empty( $cw['pair'] ) ? $cw['pair'] : '';
						$label     = $cw['title'] . ' (#' . $id . ')';
						if ( ! empty( $cw['known'] ) && ! empty( $cw['target'] ) ) {
							$label .= ' — ' . LLM_Languages::label( $cw['known'] ) . ' → ' . LLM_Languages::label( $cw['target'] );
						}
						$show = ( $current_key && $pair_attr === $current_key ) || ( (int) $id === (int) $crossword_id );
						?>
						<option
							value="<?php echo esc_attr( (string) $id ); ?>"
							data-pair="<?php echo esc_attr( $pair_attr ); ?>"
							<?php selected( $crossword_id, $id ); ?>
							<?php echo $show ? '' : 'hidden'; ?>
						>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="llm-mag-admin__section">
				<h3 id="llm-mag-stories-title"><?php echo esc_html( self::stories_section_title( $known, $target ) ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Spunta una o più storie della categoria dedicata alla coppia (inclusi i sotto-argomenti). Le storie con anche la categoria “Brani musicali” compaiono solo nella rubrica sotto.', 'llm-con-tabelle' ); ?>
					<?php if ( $pair_cat ) : ?>
						<br>
						<span id="llm-mag-pair-cat-label">
						<?php
						printf(
							/* translators: %s: category name/slug */
							esc_html__( 'Categoria coppia attuale: %s', 'llm-con-tabelle' ),
							esc_html( $pair_cat->name . ' (' . $pair_cat->slug . ')' )
						);
						?>
						</span>
					<?php else : ?>
						<br><span id="llm-mag-pair-cat-label"></span>
					<?php endif; ?>
				</p>
				<p class="llm-mag-admin__stories-empty" id="llm-mag-stories-empty" <?php echo ( $known && $target ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ); ?>
				</p>
				<div class="llm-mag-admin__stories" id="llm-mag-stories" data-known="<?php echo esc_attr( $known ); ?>" data-target="<?php echo esc_attr( $target ); ?>">
					<?php foreach ( $all_stories as $story ) : ?>
						<?php
						$pair_keys = self::story_pair_keys( $story->ID, $pair_term_map );
						$is_music  = LLM_Magazine::is_music_story( $story->ID );
						$cefr      = (string) get_post_meta( $story->ID, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
						$match     = $current_key && in_array( $current_key, $pair_keys, true ) && ! $is_music;
						$checked   = in_array( (int) $story->ID, $selected, true );
						?>
						<label class="llm-mag-admin__story"
							data-pairs="<?php echo esc_attr( implode( ',', $pair_keys ) ); ?>"
							data-music="<?php echo $is_music ? '1' : '0'; ?>"
							<?php echo $match ? '' : 'hidden'; ?>>
							<input type="checkbox" name="llm_mag_stories[]" value="<?php echo esc_attr( (string) $story->ID ); ?>" <?php checked( $checked ); ?>>
							<span class="llm-mag-admin__story-title"><?php echo esc_html( $story->post_title ); ?></span>
							<span class="llm-mag-admin__story-meta">
								#<?php echo (int) $story->ID; ?>
								· <?php echo esc_html( mysql2date( 'd/m/Y', $story->post_date ) ); ?>
								<?php if ( $cefr ) : ?>
									· <?php echo esc_html( $cefr ); ?>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="llm-mag-admin__section">
				<h3><?php esc_html_e( 'Brani musicali', 'llm-con-tabelle' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Spunta una o più storie che appartengono sia alla categoria della coppia sia a “Brani musicali”. In frontend le card saranno orizzontali.', 'llm-con-tabelle' ); ?>
					<?php if ( $music_cat ) : ?>
						<br>
						<?php
						printf(
							/* translators: %s: category name/slug */
							esc_html__( 'Categoria brani: %s', 'llm-con-tabelle' ),
							esc_html( $music_cat->name . ' (' . $music_cat->slug . ')' )
						);
						?>
					<?php else : ?>
						<br>
						<span class="llm-mag-admin__warn">
							<?php esc_html_e( 'Attenzione: crea prima la categoria WordPress con slug “brani-musicali”.', 'llm-con-tabelle' ); ?>
						</span>
					<?php endif; ?>
				</p>
				<p class="llm-mag-admin__stories-empty" id="llm-mag-music-empty" <?php echo ( $known && $target ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ); ?>
				</p>
				<div class="llm-mag-admin__stories" id="llm-mag-music">
					<?php foreach ( $all_stories as $story ) : ?>
						<?php
						$pair_keys = self::story_pair_keys( $story->ID, $pair_term_map );
						$is_music  = LLM_Magazine::is_music_story( $story->ID );
						$cefr      = (string) get_post_meta( $story->ID, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
						$match     = $current_key && in_array( $current_key, $pair_keys, true ) && $is_music;
						$checked   = in_array( (int) $story->ID, $selected_music, true );
						?>
						<label class="llm-mag-admin__story"
							data-pairs="<?php echo esc_attr( implode( ',', $pair_keys ) ); ?>"
							data-music="<?php echo $is_music ? '1' : '0'; ?>"
							<?php echo $match ? '' : 'hidden'; ?>>
							<input type="checkbox" name="llm_mag_music[]" value="<?php echo esc_attr( (string) $story->ID ); ?>" <?php checked( $checked ); ?>>
							<span class="llm-mag-admin__story-title"><?php echo esc_html( $story->post_title ); ?></span>
							<span class="llm-mag-admin__story-meta">
								#<?php echo (int) $story->ID; ?>
								· <?php echo esc_html( mysql2date( 'd/m/Y', $story->post_date ) ); ?>
								<?php if ( $cefr ) : ?>
									· <?php echo esc_html( $cefr ); ?>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="llm-mag-admin__section">
				<h3><?php esc_html_e( 'Quiz del giorno', 'llm-con-tabelle' ); ?></h3>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of questions */
						esc_html__( 'Scegli le domande da mostrare (consigliate: %d). Se non selezioni nulla, al salvataggio ne vengono proposte %d automaticamente, evitando quelle già usate nelle ultime riviste della stessa coppia.', 'llm-con-tabelle' ),
						LLM_Magazine::QUIZ_PER_ISSUE,
						LLM_Magazine::QUIZ_PER_ISSUE
					);
					?>
				</p>
				<p class="llm-mag-admin__quiz-toolbar">
					<button type="button" class="button" id="llm-mag-quiz-auto"><?php echo esc_html( sprintf( __( 'Scegli %d automatiche (senza ripetizioni)', 'llm-con-tabelle' ), LLM_Magazine::QUIZ_PER_ISSUE ) ); ?></button>
					<span class="llm-mag-admin__quiz-status" id="llm-mag-quiz-status" aria-live="polite"></span>
				</p>
				<p class="llm-mag-admin__stories-empty" id="llm-mag-quiz-empty" <?php echo ( $known && $target ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ); ?>
				</p>
				<div class="llm-mag-admin__stories llm-mag-admin__quiz" id="llm-mag-quiz">
					<?php foreach ( $quiz_rows as $row ) : ?>
						<?php
						$match   = $current_key && $row['pair_key'] === $current_key;
						$checked = in_array( $row['qid'], $selected_quiz, true );
						?>
						<label class="llm-mag-admin__story llm-mag-admin__quiz-row"
							data-pairs="<?php echo esc_attr( $row['pair_key'] ); ?>"
							data-qid="<?php echo esc_attr( $row['qid'] ); ?>"
							<?php echo $match ? '' : 'hidden'; ?>>
							<input type="checkbox" name="llm_mag_quiz_q[]" value="<?php echo esc_attr( $row['qid'] ); ?>" <?php checked( $checked ); ?>>
							<span class="llm-mag-admin__story-title"><?php echo esc_html( $row['question'] ); ?></span>
							<span class="llm-mag-admin__story-meta">
								<?php echo esc_html( $row['category'] ? $row['category'] : '—' ); ?>
								· <?php echo esc_html( sprintf( __( 'Corretta %s', 'llm-con-tabelle' ), $row['correct_letter'] ) ); ?>
								<?php if ( ! empty( $row['used'] ) && $match ) : ?>
									· <em><?php esc_html_e( 'già usata', 'llm-con-tabelle' ); ?></em>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<?php
			$selected_idiom_id = LLM_Magazine::get_idiom_id( $post->ID );
			$idiom_rows        = self::all_idioms_for_admin();
			?>
			<div class="llm-mag-admin__section">
				<h3><?php esc_html_e( 'Espressione del giorno', 'llm-con-tabelle' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Scegli un’espressione dalla banca (Storie LLM → Espressioni). Se non selezioni nulla, la rubrica non compare in rivista.', 'llm-con-tabelle' ); ?>
				</p>
				<p class="llm-mag-admin__stories-empty" id="llm-mag-idiom-empty" <?php echo ( $known && $target ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ); ?>
				</p>
				<div class="llm-mag-admin__stories llm-mag-admin__idioms" id="llm-mag-idioms">
					<label class="llm-mag-admin__story llm-mag-admin__idiom-row" data-pairs="*" <?php echo ( $known && $target ) ? '' : 'hidden'; ?>>
						<input type="radio" name="llm_mag_idiom_id" value="" <?php checked( $selected_idiom_id, '' ); ?>>
						<span class="llm-mag-admin__story-title"><?php esc_html_e( '— Nessuna espressione —', 'llm-con-tabelle' ); ?></span>
					</label>
					<?php foreach ( $idiom_rows as $row ) : ?>
						<?php
						$match   = $current_key && $row['pair_key'] === $current_key;
						$checked = $selected_idiom_id && $selected_idiom_id === $row['id'];
						?>
						<label class="llm-mag-admin__story llm-mag-admin__idiom-row"
							data-pairs="<?php echo esc_attr( $row['pair_key'] ); ?>"
							<?php echo $match ? '' : 'hidden'; ?>>
							<input type="radio" name="llm_mag_idiom_id" value="<?php echo esc_attr( $row['id'] ); ?>" <?php checked( $checked ); ?>>
							<span class="llm-mag-admin__story-title"><?php echo esc_html( $row['phrase'] ); ?></span>
							<span class="llm-mag-admin__story-meta">
								<?php echo esc_html( $row['category'] ? $row['category'] : '—' ); ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<?php
			$videos = LLM_Magazine::get_videos( $post->ID );
			while ( count( $videos ) < LLM_Magazine::VIDEOS_PER_ISSUE ) {
				$videos[] = array(
					'title'       => '',
					'url'         => '',
					'youtube_id'  => '',
					'description' => '',
				);
			}
			?>
			<div class="llm-mag-admin__section">
				<h3><?php esc_html_e( 'Video da ascoltare', 'llm-con-tabelle' ); ?></h3>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of videos */
						esc_html__( 'Proponi fino a %d video YouTube di madrelingua (titolo, URL, breve spiegazione). In rivista restano incorporati nella pagina.', 'llm-con-tabelle' ),
						LLM_Magazine::VIDEOS_PER_ISSUE
					);
					?>
				</p>
				<div class="llm-mag-admin__videos">
					<?php foreach ( $videos as $i => $video ) : ?>
						<div class="llm-mag-admin__video">
							<h4 class="llm-mag-admin__video-heading"><?php echo esc_html( sprintf( __( 'Video %d', 'llm-con-tabelle' ), $i + 1 ) ); ?></h4>
							<p>
								<label for="llm_mag_video_title_<?php echo (int) $i; ?>"><strong><?php esc_html_e( 'Titolo', 'llm-con-tabelle' ); ?></strong></label>
								<input type="text" class="widefat" id="llm_mag_video_title_<?php echo (int) $i; ?>" name="llm_mag_videos[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $video['title'] ); ?>" placeholder="<?php echo esc_attr__( 'Es. Consigli di pronuncia', 'llm-con-tabelle' ); ?>">
							</p>
							<p>
								<label for="llm_mag_video_url_<?php echo (int) $i; ?>"><strong><?php esc_html_e( 'URL YouTube', 'llm-con-tabelle' ); ?></strong></label>
								<input type="url" class="widefat" id="llm_mag_video_url_<?php echo (int) $i; ?>" name="llm_mag_videos[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $video['url'] ); ?>" placeholder="https://www.youtube.com/watch?v=…">
							</p>
							<p>
								<label for="llm_mag_video_desc_<?php echo (int) $i; ?>"><strong><?php esc_html_e( 'Breve spiegazione', 'llm-con-tabelle' ); ?></strong></label>
								<textarea class="widefat" rows="2" id="llm_mag_video_desc_<?php echo (int) $i; ?>" name="llm_mag_videos[<?php echo (int) $i; ?>][description]" placeholder="<?php echo esc_attr__( 'Perché ascoltarlo / di cosa parla', 'llm-con-tabelle' ); ?>"><?php echo esc_textarea( $video['description'] ); ?></textarea>
							</p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Espressioni di tutte le banche, per filtri admin rivista.
	 *
	 * @return array<int,array{id:string,pair_key:string,category:string,phrase:string}>
	 */
	private static function all_idioms_for_admin() {
		if ( ! class_exists( 'LLM_Idiom' ) ) {
			return array();
		}
		$q = new WP_Query(
			array(
				'post_type'              => LLM_Idiom::CPT,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 100,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		$out = array();
		foreach ( $q->posts as $bank ) {
			$known  = LLM_Idiom::get_known( $bank->ID );
			$target = LLM_Idiom::get_target( $bank->ID );
			if ( ! $known || ! $target ) {
				continue;
			}
			$pair_key = LLM_Magazine::pair_key( $known, $target );
			foreach ( LLM_Idiom::get_items( $bank->ID ) as $item ) {
				$out[] = array(
					'id'       => $item['id'],
					'pair_key' => $pair_key,
					'category' => $item['category'],
					'phrase'   => $item['phrase'] ? $item['phrase'] : __( '(senza testo)', 'llm-con-tabelle' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Domande di tutte le banche quiz, per filtri admin.
	 *
	 * @param int $exclude_mag_id Rivista corrente (per marcare “già usata” sulle altre).
	 * @return array<int,array{qid:string,pair_key:string,category:string,question:string,correct_letter:string,used:bool}>
	 */
	private static function all_quiz_questions_for_admin( $exclude_mag_id = 0 ) {
		if ( ! class_exists( 'LLM_Quiz' ) ) {
			return array();
		}
		$q = new WP_Query(
			array(
				'post_type'              => LLM_Quiz::CPT,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 100,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$used_cache = array();
		$out        = array();
		foreach ( $q->posts as $quiz ) {
			$known  = LLM_Quiz::get_known( $quiz->ID );
			$target = LLM_Quiz::get_target( $quiz->ID );
			if ( ! $known || ! $target ) {
				continue;
			}
			$pair_key = LLM_Magazine::pair_key( $known, $target );
			if ( ! isset( $used_cache[ $pair_key ] ) ) {
				$used_cache[ $pair_key ] = LLM_Magazine::used_quiz_question_ids( $known, $target, absint( $exclude_mag_id ) );
			}
			foreach ( LLM_Quiz::get_questions( $quiz->ID ) as $question ) {
				$qid = isset( $question['id'] ) ? (string) $question['id'] : '';
				if ( '' === $qid ) {
					continue;
				}
				$correct = isset( $question['correct'] ) ? (int) $question['correct'] : 0;
				$out[]   = array(
					'qid'            => $qid,
					'pair_key'       => $pair_key,
					'category'       => isset( $question['category'] ) ? (string) $question['category'] : '',
					'question'       => isset( $question['question'] ) ? (string) $question['question'] : '',
					'correct_letter' => chr( 65 + max( 0, min( 2, $correct ) ) ),
					'used'           => in_array( $qid, $used_cache[ $pair_key ], true ),
				);
			}
		}
		return $out;
	}

	/**
	 * Mappa term_id categoria → chiavi coppia (es. it-en) che includono quel termine.
	 *
	 * @return array<int,string[]>
	 */
	private static function pair_term_to_keys_map() {
		$map = array();
		foreach ( LLM_Magazine::all_pairs() as $pair ) {
			foreach ( LLM_Magazine::pair_category_term_ids( $pair['known'], $pair['target'] ) as $tid ) {
				if ( ! isset( $map[ $tid ] ) ) {
					$map[ $tid ] = array();
				}
				$map[ $tid ][] = $pair['key'];
			}
		}
		return $map;
	}

	/**
	 * Chiavi coppia a cui appartiene una storia (via categoria root/figli).
	 * Fallback: meta known/target se non è in nessuna categoria di coppia.
	 *
	 * @param int               $story_id      ID storia.
	 * @param array<int,string[]> $pair_term_map Mappa termini.
	 * @return string[]
	 */
	private static function story_pair_keys( $story_id, array $pair_term_map ) {
		$keys = array();
		$cats = wp_get_post_terms( absint( $story_id ), 'category', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $cats ) ) {
			foreach ( $cats as $cid ) {
				$cid = (int) $cid;
				if ( isset( $pair_term_map[ $cid ] ) ) {
					$keys = array_merge( $keys, $pair_term_map[ $cid ] );
				}
			}
		}
		$keys = array_values( array_unique( $keys ) );
		if ( ! empty( $keys ) ) {
			return $keys;
		}

		$sk = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$st = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		if ( LLM_Languages::is_valid( $sk ) && LLM_Languages::is_valid( $st ) && $sk !== $st ) {
			return array( LLM_Magazine::pair_key( $sk, $st ) );
		}
		return array();
	}

	/**
	 * Titolo rubrica storie in base alla coppia.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return string
	 */
	public static function stories_section_title( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( LLM_Languages::is_valid( $known ) && LLM_Languages::is_valid( $target ) && $known !== $target ) {
			return sprintf(
				/* translators: 1: language to learn with article, 2: known language with article */
				__( 'Storie per imparare %1$s per chi conosce %2$s', 'llm-con-tabelle' ),
				self::lang_with_article( $target ),
				self::lang_with_article( $known )
			);
		}
		return __( 'Storie del giorno', 'llm-con-tabelle' );
	}

	/**
	 * Nome lingua con articolo italiano.
	 *
	 * @param string $code Codice lingua.
	 * @return string
	 */
	private static function lang_with_article( $code ) {
		$labels = array(
			'en' => 'l’inglese',
			'it' => 'l’italiano',
			'pl' => 'il polacco',
			'es' => 'lo spagnolo',
		);
		$code = sanitize_key( (string) $code );
		return isset( $labels[ $code ] ) ? $labels[ $code ] : LLM_Languages::label( $code );
	}

	/**
	 * Titoli sezione storie per tutte le coppie (per aggiornamento JS).
	 *
	 * @return array<string,string> Chiave coppia → titolo.
	 */
	public static function stories_section_titles_map() {
		$map = array();
		foreach ( LLM_Magazine::all_pairs() as $pair ) {
			$map[ $pair['key'] ] = self::stories_section_title( $pair['known'], $pair['target'] );
		}
		return $map;
	}

	/**
	 * @param string $field Nome campo POST (array di ID).
	 * @return int[]
	 */
	private static function sanitize_id_list_from_post( $field ) {
		$ids = array();
		if ( empty( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $ids;
		}
		foreach ( wp_unslash( $_POST[ $field ] ) as $sid ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$sid = absint( $sid );
			if ( $sid ) {
				$ids[] = $sid;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return string[]
	 */
	private static function sanitize_quiz_qid_list_from_post() {
		$ids = array();
		if ( empty( $_POST['llm_mag_quiz_q'] ) || ! is_array( $_POST['llm_mag_quiz_q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $ids;
		}
		foreach ( wp_unslash( $_POST['llm_mag_quiz_q'] ) as $qid ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$qid = sanitize_key( (string) $qid );
			if ( '' !== $qid ) {
				$ids[] = $qid;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Video YouTube dal form rivista.
	 *
	 * @return array<int,array{title:string,url:string,youtube_id:string,description:string}>
	 */
	private static function sanitize_videos_from_post() {
		if ( empty( $_POST['llm_mag_videos'] ) || ! is_array( $_POST['llm_mag_videos'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return array();
		}
		return LLM_Magazine::normalize_videos( wp_unslash( $_POST['llm_mag_videos'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * @return array{category:string,phrase:string,explanation:string}|null
	 */
	private static function sanitize_idiom_from_post() {
		if ( empty( $_POST['llm_mag_idiom'] ) || ! is_array( $_POST['llm_mag_idiom'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return null;
		}
		return LLM_Magazine::normalize_idiom( wp_unslash( $_POST['llm_mag_idiom'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	public static function ajax_suggest_quiz() {
		if ( ! check_ajax_referer( self::AJAX_SUGGEST, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'llm-con-tabelle' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'llm-con-tabelle' ) ), 403 );
		}

		$known      = isset( $_POST['known'] ) ? sanitize_key( wp_unslash( $_POST['known'] ) ) : '';
		$target     = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '';
		$exclude_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$count      = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : LLM_Magazine::QUIZ_PER_ISSUE;
		if ( ! $count ) {
			$count = LLM_Magazine::QUIZ_PER_ISSUE;
		}

		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) || $known === $target ) {
			wp_send_json_error( array( 'message' => __( 'Coppia non valida.', 'llm-con-tabelle' ) ), 400 );
		}

		$suggest = LLM_Magazine::suggest_quiz_questions( $known, $target, $count, $exclude_id );
		if ( empty( $suggest['question_ids'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nessuna domanda disponibile per questa coppia. Crea prima una banca quiz.', 'llm-con-tabelle' ),
				),
				404
			);
		}

		wp_send_json_success(
			array(
				'question_ids' => $suggest['question_ids'],
				'quiz_id'      => $suggest['quiz_id'],
				'message'      => sprintf(
					/* translators: %d: number of questions */
					__( 'Selezionate %d domande (preferenza a quelle non usate di recente).', 'llm-con-tabelle' ),
					count( $suggest['question_ids'] )
				),
			)
		);
	}

	/**
	 * @return WP_Post[]
	 */
	private static function all_stories_for_admin() {
		$q = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 300,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		return $q->posts;
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

		$known  = isset( $_POST['llm_mag_known'] ) ? sanitize_key( wp_unslash( $_POST['llm_mag_known'] ) ) : '';
		$target = isset( $_POST['llm_mag_target'] ) ? sanitize_key( wp_unslash( $_POST['llm_mag_target'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		if ( ! LLM_Languages::is_valid( $target ) ) {
			$target = '';
		}
		if ( $known && $target && $known === $target ) {
			$target = '';
		}

		$date = isset( $_POST['llm_mag_date'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_mag_date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = mysql2date( 'Y-m-d', $post->post_date, false );
		}

		$homepage    = ! empty( $_POST['llm_mag_homepage'] ) ? '1' : '';
		$crossword   = isset( $_POST['llm_mag_crossword'] ) ? absint( $_POST['llm_mag_crossword'] ) : 0;
		if ( $crossword && $known && $target ) {
			$cw_known  = LLM_Crossword::get_known( $crossword );
			$cw_target = LLM_Crossword::get_target( $crossword );
			if ( $cw_known !== $known || $cw_target !== $target ) {
				$crossword = 0;
			}
		} elseif ( $crossword && ( ! $known || ! $target ) ) {
			$crossword = 0;
		}
		$story_ids   = self::sanitize_id_list_from_post( 'llm_mag_stories' );
		$music_ids   = self::sanitize_id_list_from_post( 'llm_mag_music' );
		$quiz_qids   = self::sanitize_quiz_qid_list_from_post();
		$videos      = self::sanitize_videos_from_post();
		$idiom_id    = isset( $_POST['llm_mag_idiom_id'] ) ? sanitize_key( wp_unslash( $_POST['llm_mag_idiom_id'] ) ) : '';
		$note_title  = isset( $_POST['llm_mag_note_title'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_mag_note_title'] ) ) : '';
		$note_name   = isset( $_POST['llm_mag_note_name'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_mag_note_name'] ) ) : '';
		$note_text   = isset( $_POST['llm_mag_note_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['llm_mag_note_text'] ) ) : '';

		/* Se nessuna domanda scelta: auto-pick evitando ripetizioni. */
		if ( empty( $quiz_qids ) && $known && $target ) {
			$suggest   = LLM_Magazine::suggest_quiz_questions( $known, $target, LLM_Magazine::QUIZ_PER_ISSUE, $post_id );
			$quiz_qids = $suggest['question_ids'];
		}

		update_post_meta( $post_id, LLM_Magazine::META_KNOWN, $known );
		update_post_meta( $post_id, LLM_Magazine::META_TARGET, $target );
		update_post_meta( $post_id, LLM_Magazine::META_DATE, $date );
		update_post_meta( $post_id, LLM_Magazine::META_CROSSWORD, $crossword );
		update_post_meta( $post_id, LLM_Magazine::META_STORY_IDS, $story_ids );
		update_post_meta( $post_id, LLM_Magazine::META_MUSIC_IDS, $music_ids );
		update_post_meta( $post_id, LLM_Magazine::META_QUIZ_QIDS, $quiz_qids );
		update_post_meta( $post_id, LLM_Magazine::META_VIDEOS, $videos );
		update_post_meta( $post_id, LLM_Magazine::META_NOTE_TITLE, $note_title );
		update_post_meta( $post_id, LLM_Magazine::META_NOTE_NAME, $note_name );
		update_post_meta( $post_id, LLM_Magazine::META_NOTE_TEXT, $note_text );
		if ( $idiom_id ) {
			update_post_meta( $post_id, LLM_Magazine::META_IDIOM_ID, $idiom_id );
		} else {
			delete_post_meta( $post_id, LLM_Magazine::META_IDIOM_ID );
		}

		if ( '1' === $homepage && $known && $target ) {
			update_post_meta( $post_id, LLM_Magazine::META_HOMEPAGE, '1' );
			LLM_Magazine::clear_other_homepages( $post_id, $known, $target );
		} else {
			delete_post_meta( $post_id, LLM_Magazine::META_HOMEPAGE );
		}
	}

	/**
	 * @param array<string,string> $columns Colonne.
	 * @return array<string,string>
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'cb' === $key ) {
				$out[ $key ]           = $label;
				$out['llm_mag_thumb']  = __( 'Anteprima', 'llm-con-tabelle' );
				continue;
			}
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['llm_mag_pair']      = __( 'Coppia', 'llm-con-tabelle' );
				$out['llm_mag_date']      = __( 'Data', 'llm-con-tabelle' );
				$out['llm_mag_homepage']  = __( 'Prima pagina', 'llm-con-tabelle' );
				$out['llm_mag_shortcode'] = __( 'Shortcode', 'llm-con-tabelle' );
			}
		}
		return $out;
	}

	/**
	 * @param string $column  Colonna.
	 * @param int    $post_id ID.
	 */
	public static function column_content( $column, $post_id ) {
		$known  = LLM_Magazine::get_known( $post_id );
		$target = LLM_Magazine::get_target( $post_id );

		switch ( $column ) {
			case 'llm_mag_thumb':
				$thumb_id  = (int) get_post_thumbnail_id( $post_id );
				$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 60, 60 ) ) : '';
				echo '<div class="llm-mag-col-thumb">';
				if ( $thumb_url ) {
					echo '<img src="' . esc_url( $thumb_url ) . '" alt="" width="60" height="60" />';
				} else {
					echo '<span class="llm-mag-col-no-thumb">—</span>';
				}
				echo '</div>';
				break;
			case 'llm_mag_pair':
				if ( $known && $target ) {
					$fk = LLM_Languages::flag_emoji( $known );
					$ft = LLM_Languages::flag_emoji( $target );
					$parts = array();
					if ( $fk ) {
						$parts[] = $fk;
					}
					$parts[] = LLM_Languages::label( $known );
					$parts[] = '→';
					if ( $ft ) {
						$parts[] = $ft;
					}
					$parts[] = LLM_Languages::label( $target );
					echo esc_html( implode( ' ', $parts ) );
				} else {
					echo '—';
				}
				break;
			case 'llm_mag_date':
				echo esc_html( mysql2date( 'd/m/Y', LLM_Magazine::get_date( $post_id ) . ' 00:00:00' ) );
				break;
			case 'llm_mag_homepage':
				echo LLM_Magazine::is_homepage( $post_id )
					? '<span class="llm-mag-col-yes">' . esc_html__( 'Sì', 'llm-con-tabelle' ) . '</span>'
					: '—';
				break;
			case 'llm_mag_shortcode':
				if ( $known && $target ) {
					echo '<code>[' . esc_html( LLM_Magazine::homepage_shortcode_name( $known, $target ) ) . ']</code>';
				} else {
					echo '—';
				}
				break;
		}
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $which     Top/bottom.
	 */
	public static function list_filters( $post_type, $which = '' ) {
		if ( LLM_Magazine::CPT !== $post_type ) {
			return;
		}
		$current = isset( $_GET['llm_mag_pair'] ) ? sanitize_text_field( wp_unslash( $_GET['llm_mag_pair'] ) ) : '';
		echo '<select name="llm_mag_pair">';
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
		if ( LLM_Magazine::CPT !== $query->get( 'post_type' ) ) {
			return;
		}
		$pair = isset( $_GET['llm_mag_pair'] ) ? sanitize_text_field( wp_unslash( $_GET['llm_mag_pair'] ) ) : '';
		if ( ! preg_match( '/^[a-z]{2}-[a-z]{2}$/', $pair ) ) {
			return;
		}
		list( $known, $target ) = explode( '-', $pair );
		$query->set(
			'meta_query',
			array(
				'relation' => 'AND',
				array(
					'key'   => LLM_Magazine::META_KNOWN,
					'value' => $known,
				),
				array(
					'key'   => LLM_Magazine::META_TARGET,
					'value' => $target,
				),
			)
		);
	}
}
