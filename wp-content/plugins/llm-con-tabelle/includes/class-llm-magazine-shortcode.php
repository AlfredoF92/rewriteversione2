<?php
/**
 * Shortcode rivista di prima pagina per coppia:
 * [rivista-primapagina-english-italian] ecc.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Magazine_Shortcode {

	public static function init() {
		foreach ( LLM_Magazine::all_pairs() as $pair ) {
			add_shortcode( $pair['shortcode'], array( __CLASS__, 'render_homepage' ) );
		}
		add_shortcode( 'llm_rivista_primapagina', array( __CLASS__, 'render_generic' ) );
	}

	/**
	 * Shortcode generico: [llm_rivista_primapagina known="en" target="it"]
	 *
	 * @param array<string,string> $atts Attributi.
	 * @return string
	 */
	public static function render_generic( $atts ) {
		$atts = shortcode_atts(
			array(
				'known'  => '',
				'target' => '',
			),
			$atts,
			'llm_rivista_primapagina'
		);
		return self::render_for_pair( $atts['known'], $atts['target'] );
	}

	/**
	 * Callback degli shortcode tipizzati per coppia.
	 *
	 * @param array  $atts       Attributi (ignorati).
	 * @param string $content    Contenuto.
	 * @param string $shortcode  Tag shortcode.
	 * @return string
	 */
	public static function render_homepage( $atts = array(), $content = '', $shortcode = '' ) {
		$pair = self::pair_from_shortcode( $shortcode );
		if ( ! $pair ) {
			return self::admin_notice( __( 'Shortcode rivista non riconosciuto.', 'llm-con-tabelle' ) );
		}
		return self::render_for_pair( $pair['known'], $pair['target'] );
	}

	/**
	 * @param string $shortcode Tag shortcode.
	 * @return array{known:string,target:string}|null
	 */
	private static function pair_from_shortcode( $shortcode ) {
		$shortcode = (string) $shortcode;
		if ( ! preg_match( '/^rivista-primapagina-([a-z]+)-([a-z]+)$/', $shortcode, $m ) ) {
			return null;
		}
		$known  = LLM_Magazine::slug_to_code( $m[1] );
		$target = LLM_Magazine::slug_to_code( $m[2] );
		if ( ! $known || ! $target || $known === $target ) {
			return null;
		}
		return array(
			'known'  => $known,
			'target' => $target,
		);
	}

	/**
	 * @param string $message Messaggio.
	 * @return string
	 */
	private static function admin_notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="llm-magazine-error">' . esc_html( $message ) . '</p>';
	}

	/**
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return string
	 */
	public static function render_for_pair( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) ) {
			return self::admin_notice( __( 'Coppia di lingue non valida per la rivista.', 'llm-con-tabelle' ) );
		}

		$mag_id = LLM_Magazine::find_homepage_id( $known, $target );
		if ( ! $mag_id ) {
			return self::admin_notice(
				sprintf(
					/* translators: 1: known language, 2: target language */
					__( 'Nessuna rivista di prima pagina per %1$s → %2$s.', 'llm-con-tabelle' ),
					LLM_Languages::label( $known ),
					LLM_Languages::label( $target )
				)
			);
		}

		return self::render_magazine( $mag_id );
	}

	/**
	 * @param int $mag_id ID rivista.
	 * @return string
	 */
	public static function render_magazine( $mag_id ) {
		$mag_id = absint( $mag_id );
		$post   = get_post( $mag_id );
		if ( ! $post || LLM_Magazine::CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return self::admin_notice( __( 'Rivista non disponibile.', 'llm-con-tabelle' ) );
		}

		self::enqueue();

		$title        = get_the_title( $mag_id );
		$date         = LLM_Magazine::get_date( $mag_id );
		$crossword_id = LLM_Magazine::get_crossword_id( $mag_id );
		$story_ids    = LLM_Magazine::get_story_ids( $mag_id );
		$music_ids    = LLM_Magazine::get_music_ids( $mag_id );
		$quiz_qs      = LLM_Magazine::get_quiz_questions_for_magazine( $mag_id );
		$date_label   = mysql2date( get_option( 'date_format' ), $date . ' 00:00:00' );

		ob_start();
		?>
		<div class="llm-magazine llm-ui-scope" data-magazine-id="<?php echo esc_attr( (string) $mag_id ); ?>">
			<header class="llm-magazine__header">
				<?php if ( $title ) : ?>
					<h2 class="llm-magazine__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $date_label ) : ?>
					<p class="llm-magazine__date"><?php echo esc_html( $date_label ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( $crossword_id ) : ?>
				<section class="llm-magazine__section llm-magazine__section--crossword">
					<h3 class="llm-magazine__section-title"><?php esc_html_e( 'Cruciverba del giorno', 'llm-con-tabelle' ); ?></h3>
					<?php echo do_shortcode( '[llm_crossword id="' . (int) $crossword_id . '"]' ); ?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $story_ids ) ) : ?>
				<section class="llm-magazine__section llm-magazine__section--stories">
					<h3 class="llm-magazine__section-title"><?php esc_html_e( 'Storie del giorno', 'llm-con-tabelle' ); ?></h3>
					<div class="llm-magazine__stories">
						<?php foreach ( $story_ids as $story_id ) : ?>
							<?php echo self::render_story_card( $story_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $music_ids ) ) : ?>
				<section class="llm-magazine__section llm-magazine__section--music">
					<h3 class="llm-magazine__section-title"><?php esc_html_e( 'Brani musicali', 'llm-con-tabelle' ); ?></h3>
					<div class="llm-magazine__music">
						<?php foreach ( $music_ids as $story_id ) : ?>
							<?php echo self::render_story_card( $story_id, 'horizontal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $quiz_qs ) ) : ?>
				<section class="llm-magazine__section llm-magazine__section--quiz">
					<h3 class="llm-magazine__section-title"><?php esc_html_e( 'Quiz del giorno', 'llm-con-tabelle' ); ?></h3>
					<?php echo self::render_quiz( $mag_id, $quiz_qs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Quiz interattivo: una domanda alla volta, click → spiegazione → avanti.
	 *
	 * @param int   $mag_id    ID rivista.
	 * @param array $questions Domande complete.
	 * @return string
	 */
	private static function render_quiz( $mag_id, array $questions ) {
		$payload = array(
			'magazineId' => absint( $mag_id ),
			'questions'  => array_values( $questions ),
			'i18n'       => array(
				'progress'      => __( 'Domanda %1$d di %2$d', 'llm-con-tabelle' ),
				'correct'       => __( 'Corretto!', 'llm-con-tabelle' ),
				'explanation'   => __( 'Approfondimento', 'llm-con-tabelle' ),
				'noExplanation' => __( 'Nessun approfondimento per questa risposta.', 'llm-con-tabelle' ),
				'next'          => __( 'Prossima domanda', 'llm-con-tabelle' ),
				'finish'        => __( 'Fine', 'llm-con-tabelle' ),
				'restart'       => __( 'Rigioca', 'llm-con-tabelle' ),
				'resultTitle'   => __( 'Quiz completato', 'llm-con-tabelle' ),
				'resultDone'    => __( 'Hai esplorato tutte le domande del giorno.', 'llm-con-tabelle' ),
				'pickAnswer'    => __( 'Scegli una risposta', 'llm-con-tabelle' ),
			),
		);

		ob_start();
		?>
		<div class="llm-mag-quiz llm-ui-scope llm-ui-scope--light" data-llm-mag-quiz>
			<script type="application/json" class="llm-mag-quiz__config"><?php echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
			<div class="llm-mag-quiz__progress" data-quiz-progress aria-live="polite"></div>
			<div class="llm-mag-quiz__stage" data-quiz-stage></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param int    $story_id ID storia.
	 * @param string $layout   vertical|horizontal.
	 * @return string
	 */
	private static function render_story_card( $story_id, $layout = 'vertical' ) {
		$story_id = absint( $story_id );
		$story    = get_post( $story_id );
		if ( ! $story || LLM_STORY_CPT !== $story->post_type || 'publish' !== $story->post_status ) {
			return '';
		}

		$layout        = 'horizontal' === $layout ? 'horizontal' : 'vertical';
		$url           = get_permalink( $story_id );
		$title_known   = get_the_title( $story_id );
		$title_target  = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::TITLE_TARGET, true ) );
		$card_text     = (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CARD_TEXT, true );
		$cefr          = (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
		$target        = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );
		$img_class     = 'horizontal' === $layout ? 'llm-magazine__card-img llm-magazine__card-img--h' : 'llm-magazine__card-img';
		$thumb         = get_the_post_thumbnail( $story_id, 'medium_large', array( 'class' => $img_class ) );

		if ( '' === trim( $card_text ) ) {
			$card_text = has_excerpt( $story_id ) ? get_the_excerpt( $story_id ) : '';
		}

		/* Brani: titolo in evidenza = lingua obiettivo; fallback al titolo post. */
		$featured_title = $title_target !== '' ? $title_target : $title_known;
		$secondary_title = '';
		if ( 'horizontal' === $layout && $title_target !== '' && $title_known !== '' && strcasecmp( $title_target, $title_known ) !== 0 ) {
			$secondary_title = $title_known;
		}

		$article_class = 'llm-magazine__card';
		if ( 'horizontal' === $layout ) {
			$article_class .= ' llm-magazine__card--horizontal';
		}

		ob_start();
		?>
		<article class="<?php echo esc_attr( $article_class ); ?>">
			<a class="llm-magazine__card-link" href="<?php echo esc_url( $url ); ?>">
				<?php if ( $thumb ) : ?>
					<div class="llm-magazine__card-media"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php else : ?>
					<div class="llm-magazine__card-media llm-magazine__card-media--empty" aria-hidden="true"></div>
				<?php endif; ?>
				<div class="llm-magazine__card-body">
					<?php if ( $cefr ) : ?>
						<span class="llm-magazine__card-level"><?php echo esc_html( sprintf( __( 'Parole Livello %s', 'llm-con-tabelle' ), $cefr ) ); ?></span>
					<?php elseif ( $target ) : ?>
						<span class="llm-magazine__card-level"><?php echo esc_html( LLM_Languages::label( $target ) ); ?></span>
					<?php endif; ?>

					<?php if ( 'horizontal' === $layout ) : ?>
						<div class="llm-magazine__music-title-row">
							<span class="llm-magazine__music-note" aria-hidden="true">
								<svg width="28" height="28" viewBox="0 0 24 24" focusable="false">
									<path fill="currentColor" d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/>
								</svg>
							</span>
							<h4 class="llm-magazine__card-title llm-magazine__card-title--music"><?php echo esc_html( $featured_title ); ?></h4>
						</div>
						<?php if ( $secondary_title ) : ?>
							<p class="llm-magazine__card-title-known"><?php echo esc_html( $secondary_title ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<h4 class="llm-magazine__card-title"><?php echo esc_html( $title_known ); ?></h4>
					<?php endif; ?>

					<?php if ( $card_text ) : ?>
						<p class="llm-magazine__card-text"><?php echo esc_html( wp_strip_all_tags( $card_text ) ); ?></p>
					<?php endif; ?>
				</div>
			</a>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private static function enqueue() {
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-magazine',
			LLM_TABELLE_URL . 'assets/llm-magazine.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-magazine-quiz',
			LLM_TABELLE_URL . 'assets/llm-magazine-quiz.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
	}
}
