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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_fonts' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_sync_visitor_langs' ), 5 );
	}

	/**
	 * Click card: forza la coppia. Link diretto alla rivista: imposta solo se manca.
	 */
	public static function maybe_sync_visitor_langs() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( is_customize_preview() || is_preview() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['elementor-preview'] ) ) {
			return;
		}
		if ( ! class_exists( 'LLM_Visitor_Lang' ) || ! class_exists( 'LLM_Languages' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$force_known = isset( $_GET['llm_set_known'] ) ? sanitize_key( wp_unslash( $_GET['llm_set_known'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$force_learn = isset( $_GET['llm_set_learn'] ) ? sanitize_key( wp_unslash( $_GET['llm_set_learn'] ) ) : '';

		if (
			LLM_Languages::is_valid( $force_known )
			&& LLM_Languages::is_valid( $force_learn )
			&& $force_known !== $force_learn
		) {
			LLM_Visitor_Lang::set_pair( $force_known, $force_learn );
			$clean = remove_query_arg( array( 'llm_set_known', 'llm_set_learn' ) );
			wp_safe_redirect( $clean );
			exit;
		}

		$stored_known = LLM_Visitor_Lang::stored_known();
		$stored_learn = LLM_Visitor_Lang::stored_learning();
		if ( '' !== $stored_known && '' !== $stored_learn ) {
			return;
		}

		$pair = self::pair_from_current_request();
		if ( ! $pair ) {
			return;
		}

		LLM_Visitor_Lang::set_pair( $pair['known'], $pair['target'] );
	}

	/**
	 * Coppia della rivista nella richiesta corrente.
	 *
	 * @return array{known:string,target:string}|null
	 */
	private static function pair_from_current_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$req_id = isset( $_GET['llm_rivista'] ) ? absint( wp_unslash( $_GET['llm_rivista'] ) ) : 0;
		if ( $req_id ) {
			$known  = LLM_Magazine::get_known( $req_id );
			$target = LLM_Magazine::get_target( $req_id );
			if ( LLM_Languages::is_valid( $known ) && LLM_Languages::is_valid( $target ) && $known !== $target ) {
				return array(
					'known'  => $known,
					'target' => $target,
				);
			}
		}

		if ( class_exists( 'LLM_Home_Redirect' ) ) {
			$page_id = get_queried_object_id();
			$pairs   = (array) get_option( LLM_Home_Redirect::OPT_PAIRS, array() );
			foreach ( $pairs as $key => $dest_id ) {
				if ( (int) $dest_id !== (int) $page_id ) {
					continue;
				}
				$parts = explode( '_', (string) $key, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				$known  = sanitize_key( $parts[0] );
				$target = sanitize_key( $parts[1] );
				if ( LLM_Languages::is_valid( $known ) && LLM_Languages::is_valid( $target ) && $known !== $target ) {
					return array(
						'known'  => $known,
						'target' => $target,
					);
				}
			}
		}

		$post = get_post();
		if ( ! $post ) {
			return null;
		}
		$content = (string) $post->post_content;
		if ( preg_match( '/\[rivista-primapagina-([a-z]+)-([a-z]+)\]/', $content, $m ) ) {
			$known  = LLM_Magazine::slug_to_code( $m[1] );
			$target = LLM_Magazine::slug_to_code( $m[2] );
			if ( $known && $target && $known !== $target ) {
				return array(
					'known'  => $known,
					'target' => $target,
				);
			}
		}

		if ( preg_match( '/\[llm_rivista_primapagina([^\]]*)\]/', $content, $gm ) ) {
			$attrs  = $gm[1];
			$known  = preg_match( '/known=["\']([a-z]+)["\']/', $attrs, $km ) ? sanitize_key( $km[1] ) : '';
			$target = preg_match( '/target=["\']([a-z]+)["\']/', $attrs, $tm ) ? sanitize_key( $tm[1] ) : '';
			if ( LLM_Languages::is_valid( $known ) && LLM_Languages::is_valid( $target ) && $known !== $target ) {
				return array(
					'known'  => $known,
					'target' => $target,
				);
			}
		}

		return null;
	}

	/**
	 * Carica i font in <head> se la pagina contiene lo shortcode rivista.
	 */
	public static function maybe_enqueue_fonts() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		$content = (string) $post->post_content;
		if ( false === strpos( $content, 'rivista-primapagina' ) && false === strpos( $content, 'llm_rivista_primapagina' ) ) {
			return;
		}
		self::enqueue();
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

		// Navigazione: ?llm_rivista=ID sulla stessa pagina (CPT non pubblico).
		$req_id = isset( $_GET['llm_rivista'] ) ? absint( wp_unslash( $_GET['llm_rivista'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $req_id ) {
			$req_post = get_post( $req_id );
			if (
				$req_post
				&& LLM_Magazine::CPT === $req_post->post_type
				&& 'publish' === $req_post->post_status
				&& LLM_Magazine::get_known( $req_id ) === $known
				&& LLM_Magazine::get_target( $req_id ) === $target
			) {
				$mag_id = $req_id;
			}
		}

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
		$known        = LLM_Magazine::get_known( $mag_id );
		$target       = LLM_Magazine::get_target( $mag_id );
		$nav          = LLM_Magazine::get_nav_context( $mag_id );
		$crossword_id = LLM_Magazine::get_crossword_id( $mag_id );
		$author_note  = LLM_Magazine::get_author_note( $mag_id );
		$has_note     = ( '' !== trim( $author_note['title'] ) || '' !== trim( $author_note['name'] ) || '' !== trim( $author_note['comment'] ) );
		$story_ids    = LLM_Magazine::get_story_ids( $mag_id );
		$music_ids    = LLM_Magazine::get_music_ids( $mag_id );
		$quiz_qs      = LLM_Magazine::get_quiz_questions_for_magazine( $mag_id );
		$videos       = LLM_Magazine::get_videos( $mag_id );
		$idiom        = LLM_Magazine::get_idiom( $mag_id );
		$date_label   = mysql2date( get_option( 'date_format' ), $date . ' 00:00:00' );
		$known_flag   = LLM_Languages::flag_emoji( $known );
		$target_flag  = LLM_Languages::flag_emoji( $target );
		$known_label  = LLM_Languages::label( $known );
		$target_label = LLM_Languages::label( $target );
		$cover_id     = (int) get_post_thumbnail_id( $mag_id );
		$cover_url    = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';

		$issue_num = ! empty( $nav['issue'] ) ? (int) $nav['issue'] : 0;

		// Contatore sezioni progressivo.
		$sec        = 0;
		$sec_cross  = $crossword_id ? ++$sec : 0;
		$sec_story  = ! empty( $story_ids ) ? ++$sec : 0;
		$sec_music  = ! empty( $music_ids ) ? ++$sec : 0;
		$sec_video  = ! empty( $videos ) ? ++$sec : 0;
		$has_idiom  = $idiom && ( ! empty( $idiom['phrase'] ) || ! empty( $idiom['explanation'] ) || ! empty( $idiom['category'] ) );
		$sec_idiom  = $has_idiom ? ++$sec : 0;
		$sec_quiz   = ! empty( $quiz_qs ) ? ++$sec : 0;

		// Totale frasi per il badge hero.
		$total_phrases = 0;
		foreach ( $story_ids as $sid ) {
			$total_phrases += count( LLM_Story_Repository::get_phrases( $sid ) );
		}
		foreach ( $music_ids as $mid ) {
			$total_phrases += count( LLM_Story_Repository::get_phrases( $mid ) );
		}

		$cefr_level   = self::magazine_cefr( array_merge( $story_ids, $music_ids ) );
		$welcome_name = '';
		if ( is_user_logged_in() ) {
			$current      = wp_get_current_user();
			$welcome_name = $current && $current->display_name ? $current->display_name : '';
		}
		$learn_tagline   = self::learn_tagline( $target );
		$change_lang_url = home_url( '/language-to-learn/' );

		ob_start();
		?>
		<div class="llm-magazine llm-ui-scope llm-magazine--target-<?php echo esc_attr( sanitize_key( $target ) ); ?>" data-magazine-id="<?php echo esc_attr( (string) $mag_id ); ?>">

			<div class="llm-magazine__welcome-wrap">
				<div class="llm-magazine__welcome">
					<p class="llm-magazine__welcome-text">
						<?php
						if ( $welcome_name ) {
							echo esc_html( sprintf( /* translators: %s: user display name */ __( 'Ciao %s,', 'llm-con-tabelle' ), $welcome_name ) );
						} else {
							esc_html_e( 'Ciao,', 'llm-con-tabelle' );
						}
						?>
						<?php if ( $known_flag ) : ?>
							<span class="llm-magazine__welcome-flag" aria-hidden="true"><?php echo esc_html( $known_flag ); ?></span>
						<?php endif; ?>
						<span class="llm-magazine__welcome-brand"><?php esc_html_e( 'Bentornato su LoveRewrite', 'llm-con-tabelle' ); ?></span>
					</p>
					<a class="llm-magazine__welcome-change" href="<?php echo esc_url( $change_lang_url ); ?>"><?php esc_html_e( 'Cambia lingua', 'llm-con-tabelle' ); ?></a>
				</div>
			</div>

			<!-- ═══ HERO ═══ -->
			<div class="llm-magazine__hero">

				<!-- Sidebar sinistra -->
				<aside class="llm-magazine__sidebar">
					<?php
					$brand_aria = $issue_num > 0
						? sprintf( /* translators: %d: magazine issue number */ __( 'LoveRewrite, edizione %d', 'llm-con-tabelle' ), $issue_num )
						: 'LoveRewrite';
					?>
					<div class="llm-magazine__sidebar-brand" aria-label="<?php echo esc_attr( $brand_aria ); ?>">
						<p class="llm-magazine__sidebar-brand-name">LoveRewrite</p>
						<?php if ( $issue_num > 0 ) : ?>
							<p class="llm-magazine__sidebar-brand-label"><?php esc_html_e( 'Edizione n:', 'llm-con-tabelle' ); ?></p>
							<p class="llm-magazine__sidebar-num"><?php echo esc_html( sprintf( '%02d', $issue_num ) ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $date_label ) : ?>
						<time class="llm-magazine__sidebar-date" datetime="<?php echo esc_attr( $date ); ?>">
							<?php echo esc_html( strtoupper( $date_label ) ); ?>
						</time>
					<?php endif; ?>

					<?php if ( $known && $target ) : ?>
						<div class="llm-magazine__sidebar-pair">
							<span class="llm-magazine__sidebar-flags" aria-hidden="true">
								<?php echo esc_html( $known_flag ); ?> → <?php echo esc_html( $target_flag ); ?>
							</span>
							<span class="llm-magazine__sidebar-langs">
								<?php echo esc_html( strtoupper( $known_label ) ); ?> → <?php echo esc_html( strtoupper( $target_label ) ); ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( $cefr_level ) : ?>
						<div class="llm-magazine__sidebar-level-wrap">
							<p class="llm-magazine__sidebar-level-label"><?php esc_html_e( 'Livello consigliato', 'llm-con-tabelle' ); ?></p>
							<span class="llm-magazine__sidebar-level"><?php echo esc_html( $cefr_level ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $title ) : ?>
						<p class="llm-magazine__sidebar-title"><?php echo esc_html( $title ); ?></p>
					<?php endif; ?>

					<?php if ( $total_phrases > 0 ) : ?>
						<?php
						$target_word = array(
							'en' => 'INGLESE',
							'it' => 'ITALIANO',
							'pl' => 'POLACCO',
							'es' => 'SPAGNOLO',
						);
						$target_word = isset( $target_word[ $target ] ) ? $target_word[ $target ] : strtoupper( (string) $target_label );
						?>
						<div class="llm-magazine__sidebar-count">
							<span class="llm-magazine__sidebar-count-num"><?php echo esc_html( $total_phrases ); ?></span>
							<span class="llm-magazine__sidebar-count-label">
								<?php if ( $target_flag ) : ?>
									<span class="llm-magazine__sidebar-count-flag" aria-hidden="true"><?php echo esc_html( $target_flag ); ?></span>
								<?php endif; ?>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: target language in uppercase, e.g. INGLESE */
										__( 'FRASI DI %s DA IMPARARE', 'llm-con-tabelle' ),
										$target_word
									)
								);
								?>
							</span>
						</div>
					<?php endif; ?>
				</aside>

				<!-- Cover principale con titolo sovrapposto -->
				<div class="llm-magazine__hero-main">
					<div
						class="llm-magazine__cover<?php echo $cover_url ? '' : ' llm-magazine__cover--empty'; ?>"
						<?php if ( $cover_url ) : ?>
							style="background-image:url('<?php echo esc_url( $cover_url ); ?>');"
						<?php endif; ?>
						role="img"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: magazine title */ __( 'Copertina: %s', 'llm-con-tabelle' ), $title ) ); ?>"
					>
						<div class="llm-magazine__cover-overlay" aria-hidden="true"></div>
						<p class="llm-magazine__cover-ribbon"><?php echo esc_html( $learn_tagline ); ?></p>
						<?php if ( $title ) : ?>
							<h1 class="llm-magazine__cover-title"><?php echo esc_html( $title ); ?></h1>
						<?php endif; ?>
					</div>
				</div>
			</div><!-- /.llm-magazine__hero -->

			<!-- ═══ CORPO SEZIONI ═══ -->
			<div class="llm-magazine__body" id="llm-mag-content">

				<!-- RIGA 1: Commento+cruciverba (sx) + Storie+brani (dx) -->
				<?php
				$has_left  = $has_note || $crossword_id;
				$has_right = ! empty( $story_ids ) || ! empty( $music_ids );
				$row_class = 'llm-magazine__row llm-magazine__row--top';
				if ( $has_left && $has_right ) {
					$row_class .= ' llm-magazine__row--top-split';
				} else {
					$row_class .= ' llm-magazine__row--top-single';
				}
				?>
				<?php if ( $has_left || $has_right ) : ?>
					<div class="<?php echo esc_attr( $row_class ); ?>">

						<?php if ( $has_left ) : ?>
							<div class="llm-magazine__col llm-magazine__col--left">
								<?php if ( $has_note ) : ?>
									<?php echo self::render_author_note( $author_note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>

								<?php if ( $crossword_id ) : ?>
									<section class="llm-magazine__section llm-magazine__section--crossword">
										<header class="llm-magazine__section-head">
											<?php echo self::render_section_head( $sec_cross, __( 'Crucitarsio - Gioca con le parole', 'llm-con-tabelle' ), __( 'Amplia il vocabolario, tutte le parole sono estratte dalle frasi da imparare', 'llm-con-tabelle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</header>
										<?php echo do_shortcode( '[llm_crossword id="' . (int) $crossword_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</section>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( $has_right ) : ?>
							<div class="llm-magazine__col llm-magazine__col--right">
								<?php if ( ! empty( $story_ids ) ) : ?>
									<section class="llm-magazine__section llm-magazine__section--stories">
										<header class="llm-magazine__section-head">
											<?php echo self::render_section_head( $sec_story, __( 'Storie e curiosità', 'llm-con-tabelle' ), __( 'Storie, cultura, attualità e dialoghi per imparare', 'llm-con-tabelle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</header>
										<div class="llm-magazine__stories">
											<?php foreach ( $story_ids as $i => $story_id ) : ?>
												<?php echo self::render_story_card( $story_id, 'vertical', 0 === $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php endforeach; ?>
										</div>
									</section>
								<?php endif; ?>

								<?php if ( ! empty( $music_ids ) ) : ?>
									<section class="llm-magazine__section llm-magazine__section--music">
										<header class="llm-magazine__section-head">
											<?php echo self::render_section_head( $sec_music, __( 'Brani musicali', 'llm-con-tabelle' ), __( 'Scopri canzoni indimenticabili, impara i versi e poi ascolta il brano.', 'llm-con-tabelle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</header>
										<div class="llm-magazine__music">
											<?php foreach ( $music_ids as $story_id ) : ?>
												<?php echo self::render_story_card( $story_id, 'horizontal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php endforeach; ?>
										</div>
									</section>
								<?php endif; ?>
							</div>
						<?php endif; ?>

					</div><!-- /.llm-magazine__row--top -->
				<?php endif; ?>

				<!-- RIGA 3: Video + espressione; quiz compatto sotto i video -->
				<?php if ( ! empty( $quiz_qs ) || ! empty( $videos ) || $has_idiom ) : ?>
					<?php
					$has_bottom_left  = ! empty( $videos ) || ! empty( $quiz_qs );
					$has_bottom_right = $has_idiom;
					$bottom_class     = 'llm-magazine__row llm-magazine__row--bottom';
					if ( $has_bottom_left && $has_bottom_right ) {
						$bottom_class .= ' llm-magazine__row--bottom-split';
					} else {
						$bottom_class .= ' llm-magazine__row--bottom-single';
					}
					?>
					<div class="<?php echo esc_attr( $bottom_class ); ?>">

						<?php if ( $has_bottom_left ) : ?>
							<div class="llm-magazine__col llm-magazine__col--bottom-left">
								<?php if ( ! empty( $videos ) ) : ?>
									<section class="llm-magazine__section llm-magazine__section--videos">
										<header class="llm-magazine__section-head">
											<?php echo self::render_section_head( $sec_video, __( 'Video da ascoltare', 'llm-con-tabelle' ), __( 'Una selezione di video per allenare l\'ascolto e imparare dai madrelingua.', 'llm-con-tabelle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</header>
										<div class="llm-magazine__videos">
											<?php foreach ( $videos as $video ) : ?>
												<?php echo self::render_video_card( $video ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php endforeach; ?>
										</div>
									</section>
								<?php endif; ?>

								<?php if ( ! empty( $quiz_qs ) ) : ?>
									<section class="llm-magazine__section llm-magazine__section--quiz">
										<header class="llm-magazine__section-head">
											<?php echo self::render_section_head( $sec_quiz, __( 'Quiz veloce', 'llm-con-tabelle' ), __( 'piccoli quiz per immergerti nella cultura di questa lingua', 'llm-con-tabelle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</header>
										<?php echo self::render_quiz( $mag_id, $quiz_qs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</section>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( $has_bottom_right ) : ?>
							<div class="llm-magazine__col llm-magazine__col--bottom-right">
								<section class="llm-magazine__section llm-magazine__section--idiom">
									<header class="llm-magazine__section-head">
										<?php echo self::render_section_head( $sec_idiom, __( 'Espressioni da conoscere', 'llm-con-tabelle' ), __( 'Espressioni autentiche da capire, ascoltare e usare nelle conversazioni.', 'llm-con-tabelle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</header>
									<?php echo self::render_idiom( $idiom ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</section>
							</div>
						<?php endif; ?>

					</div><!-- /.llm-magazine__row--bottom -->
				<?php endif; ?>

			</div><!-- /.llm-magazine__body -->
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Commento dell'autore in stile giornale (sopra il cruciverba).
	 *
	 * @param array{title:string,name:string,comment:string} $note Campi admin.
	 * @return string
	 */
	private static function render_author_note( array $note ) {
		$title   = trim( (string) $note['title'] );
		$name    = trim( (string) $note['name'] );
		$comment = trim( (string) $note['comment'] );
		if ( '' === $title && '' === $name && '' === $comment ) {
			return '';
		}

		ob_start();
		?>
		<aside class="llm-magazine__author-note">
			<p class="llm-magazine__author-note-kicker"><?php esc_html_e( 'Un commento dallo staff', 'llm-con-tabelle' ); ?></p>
			<?php if ( '' !== $title ) : ?>
				<h2 class="llm-magazine__author-note-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $name ) : ?>
				<p class="llm-magazine__author-note-byline">
					<span class="llm-magazine__author-note-mark" aria-hidden="true">»</span>
					<span class="llm-magazine__author-note-name"><?php echo esc_html( $name ); ?></span>
				</p>
			<?php endif; ?>
			<?php if ( '' !== $comment ) : ?>
				<div class="llm-magazine__author-note-text">
					<?php echo wpautop( esc_html( $comment ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</aside>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Espressione / modo di dire.
	 *
	 * @param array{category:string,phrase:string,explanation:string} $idiom Dati.
	 * @return string
	 */
	private static function render_idiom( array $idiom ) {
		$has_explain = ! empty( $idiom['explanation'] );
		ob_start();
		?>
		<article class="llm-magazine__idiom">
			<?php if ( ! empty( $idiom['phrase'] ) ) : ?>
				<p class="llm-magazine__idiom-phrase"><?php echo esc_html( $idiom['phrase'] ); ?></p>
			<?php endif; ?>
			<?php if ( $has_explain ) : ?>
				<p class="llm-magazine__idiom-meaning"><?php echo nl2br( esc_html( $idiom['explanation'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $idiom['category'] ) ) : ?>
				<p class="llm-magazine__idiom-category"><?php echo esc_html( $idiom['category'] ); ?></p>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Card video YouTube incorporato (resta nella rivista).
	 *
	 * @param array{title?:string,url?:string,youtube_id?:string,description?:string} $video Video.
	 * @return string
	 */
	private static function render_video_card( array $video ) {
		$yt_id = isset( $video['youtube_id'] ) ? (string) $video['youtube_id'] : '';
		if ( '' === $yt_id ) {
			return '';
		}
		$title = isset( $video['title'] ) ? (string) $video['title'] : '';
		$desc  = isset( $video['description'] ) ? (string) $video['description'] : '';
		$embed = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $yt_id );
		$thumb = 'https://i.ytimg.com/vi/' . rawurlencode( $yt_id ) . '/mqdefault.jpg';

		ob_start();
		?>
		<details class="llm-magazine__video">
			<summary class="llm-magazine__video-summary">
				<span class="llm-magazine__video-thumb">
					<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="160" height="90" loading="lazy">
					<span class="llm-magazine__video-play" aria-hidden="true"></span>
				</span>
				<span class="llm-magazine__video-copy">
					<?php if ( $title ) : ?>
						<span class="llm-magazine__video-title"><?php echo esc_html( $title ); ?></span>
					<?php endif; ?>
					<?php if ( $desc ) : ?>
						<span class="llm-magazine__video-desc"><?php echo esc_html( $desc ); ?></span>
					<?php endif; ?>
				</span>
			</summary>
			<div class="llm-magazine__video-frame">
				<iframe
					src="<?php echo esc_url( $embed ); ?>"
					title="<?php echo esc_attr( $title ? $title : __( 'Video YouTube', 'llm-con-tabelle' ) ); ?>"
					loading="lazy"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					allowfullscreen
					referrerpolicy="strict-origin-when-cross-origin"
				></iframe>
			</div>
		</details>
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
				'aboutAnswer'   => __( 'Approfondimento — risposta %s', 'llm-con-tabelle' ),
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
	 * @param bool   $featured Prima card in evidenza (più grande).
	 * @return string
	 */
	private static function render_story_card( $story_id, $layout = 'vertical', $featured = false ) {
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
		$thumb_id  = get_post_thumbnail_id( $story_id );
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';

		// Frasi/versi: conta totale + prime 3 per anteprima.
		$is_music = ( 'horizontal' === $layout );

		// Categorie contenuto: tutte tranne coppie lingua (es. it-english) e quelle di sistema.
		$content_cats = array();
		$raw_cats     = get_the_terms( $story_id, 'category' );
		if ( ! empty( $raw_cats ) && ! is_wp_error( $raw_cats ) ) {
			$skip_slugs = array( 'uncategorized', 'senza-categoria' );
			foreach ( $raw_cats as $cat ) {
				// Salta categoria sistema e coppie lingua (pattern: due codici separati da trattino, es. it-english, pl-wloski).
				if ( in_array( $cat->slug, $skip_slugs, true ) ) {
					continue;
				}
				if ( preg_match( '/^[a-z]{2,3}-[a-z]{2,10}$/', $cat->slug ) ) {
					continue;
				}
				$content_cats[] = $cat->name;
			}
		}
		$all_phrases     = LLM_Story_Repository::get_phrases( $story_id );
		$phrase_count    = count( $all_phrases );
		$preview_phrases = array_slice( $all_phrases, 0, 2 );
		$preview_more    = $phrase_count > 2;

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
		if ( $featured ) {
			$article_class .= ' llm-magazine__card--featured';
		}

		ob_start();
		?>
		<article class="<?php echo esc_attr( $article_class ); ?>">
			<a class="llm-magazine__card-link" href="<?php echo esc_url( $url ); ?>">
				<?php if ( $thumb_url ) : ?>
					<div class="llm-magazine__card-media" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>');" role="img" aria-label="<?php echo esc_attr( $title_known ); ?>"></div>
				<?php else : ?>
					<div class="llm-magazine__card-media llm-magazine__card-media--empty" aria-hidden="true"></div>
				<?php endif; ?>
				<div class="llm-magazine__card-body">
					<?php
					// Estrae solo il codice CEFR (A1, A2, B1, B2, C1, C2) dal campo che può contenere testo extra.
					$cefr_code = '';
					if ( $cefr ) {
						if ( preg_match( '/\b([ABC][12])\b/i', $cefr, $m ) ) {
							$cefr_code = strtolower( $m[1] );
						}
					}
					?>
					<div class="llm-magazine__card-badges">
						<?php if ( $cefr_code ) : ?>
							<span class="llm-magazine__card-level llm-magazine__card-level--<?php echo esc_attr( $cefr_code ); ?>"><?php echo esc_html( strtoupper( $cefr_code ) ); ?></span>
						<?php elseif ( $cefr ) : ?>
							<span class="llm-magazine__card-level"><?php echo esc_html( $cefr ); ?></span>
						<?php elseif ( $target ) : ?>
							<span class="llm-magazine__card-level"><?php echo esc_html( LLM_Languages::label( $target ) ); ?></span>
						<?php endif; ?>
						<?php foreach ( $content_cats as $cat_name ) : ?>
							<span class="llm-magazine__card-cat<?php echo $cefr_code ? ' llm-magazine__card-level--' . esc_attr( $cefr_code ) : ''; ?>"><?php echo esc_html( $cat_name ); ?></span>
						<?php endforeach; ?>
					</div>

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

					<?php if ( ! empty( $preview_phrases ) ) : ?>
						<ul class="llm-magazine__card-phrases">
							<?php foreach ( $preview_phrases as $phrase ) : ?>
								<li class="llm-magazine__card-phrase">
									<?php if ( ! empty( $phrase['target'] ) ) : ?>
										<span class="llm-magazine__card-phrase-target"><?php echo esc_html( wp_strip_all_tags( $phrase['target'] ) ); ?></span>
									<?php endif; ?>
									<span class="llm-magazine__card-phrase-iface"><?php echo esc_html( wp_strip_all_tags( $phrase['interface'] ) ); ?></span>
								</li>
							<?php endforeach; ?>
							<?php if ( $preview_more ) : ?>
								<li class="llm-magazine__card-phrases-more" aria-hidden="true">...</li>
							<?php endif; ?>
						</ul>
					<?php endif; ?>
				</div>
				<?php if ( $phrase_count > 0 ) : ?>
					<div class="llm-magazine__card-cta" aria-hidden="true">
						<span class="llm-magazine__card-cta-label"><?php esc_html_e( 'Impara', 'llm-con-tabelle' ); ?></span>
						<span class="llm-magazine__card-cta-count">
							<?php
							if ( $is_music ) {
								echo esc_html( sprintf( _n( '%d verso', '%d versi', $phrase_count, 'llm-con-tabelle' ), $phrase_count ) );
							} else {
								$cta_count = sprintf( _n( '%d frase', '%d frasi', $phrase_count, 'llm-con-tabelle' ), $phrase_count );
								if ( $cefr_code ) {
									$cta_count .= ' ' . strtoupper( $cefr_code );
								}
								echo esc_html( $cta_count );
							}
							?>
						</span>
						<span class="llm-magazine__card-cta-arrow" aria-hidden="true">→</span>
					</div>
				<?php endif; ?>
			</a>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Livello CEFR della rivista: singolo codice o range (es. A1–B1).
	 *
	 * @param array<int,int> $story_ids ID storie/brani.
	 * @return string
	 */
	private static function magazine_cefr( array $story_ids ) {
		$order = array(
			'a1' => 1,
			'a2' => 2,
			'b1' => 3,
			'b2' => 4,
			'c1' => 5,
			'c2' => 6,
		);
		$found = array();
		foreach ( $story_ids as $sid ) {
			$cefr = (string) get_post_meta( absint( $sid ), LLM_Story_Meta::STORY_CEFR_LEVEL, true );
			if ( preg_match( '/\b([ABC][12])\b/i', $cefr, $m ) ) {
				$code = strtolower( $m[1] );
				if ( isset( $order[ $code ] ) ) {
					$found[ $code ] = $order[ $code ];
				}
			}
		}
		if ( empty( $found ) ) {
			return '';
		}
		asort( $found, SORT_NUMERIC );
		$codes = array_keys( $found );
		$min   = strtoupper( (string) $codes[0] );
		$max   = strtoupper( (string) end( $codes ) );
		return $min === $max ? $min : $min . '–' . $max;
	}

	/**
	 * Tagline sulla copertina, in base alla lingua da imparare.
	 *
	 * @param string $target Lingua target.
	 * @return string
	 */
	private static function learn_tagline( $target ) {
		$target = sanitize_key( $target );
		$map    = array(
			'en' => "Impara l'inglese una frase alla volta",
			'it' => "Impara l'italiano una frase alla volta",
			'pl' => 'Impara il polacco una frase alla volta',
			'es' => 'Impara lo spagnolo una frase alla volta',
		);
		return isset( $map[ $target ] ) ? $map[ $target ] : __( 'Impara una frase alla volta', 'llm-con-tabelle' );
	}

	/**
	 * Numero + titolo + sottotitolo di sezione.
	 *
	 * @param int    $num   Numero sezione.
	 * @param string $title Titolo.
	 * @param string $lede  Sottotitolo.
	 * @return string
	 */
	private static function render_section_head( $num, $title, $lede = '' ) {
		ob_start();
		?>
		<span class="llm-magazine__section-num"><?php echo esc_html( sprintf( '%02d', (int) $num ) ); ?></span>
		<div class="llm-magazine__section-head-text">
			<h2 class="llm-magazine__section-title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== trim( (string) $lede ) ) : ?>
				<p class="llm-magazine__section-lede"><?php echo esc_html( $lede ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function enqueue() {
		add_action( 'wp_head', array( __CLASS__, 'print_font_preconnect' ), 2 );
		wp_enqueue_style(
			'llm-magazine-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Bebas+Neue&family=Elms+Sans:ital,wght@0,100..900;1,100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-magazine',
			LLM_TABELLE_URL . 'assets/llm-magazine.css',
			array( 'llm-ui', 'llm-magazine-fonts' ),
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

	/**
	 * Preconnect Google Fonts (solo quando la rivista è in pagina).
	 */
	public static function print_font_preconnect() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	}
}
