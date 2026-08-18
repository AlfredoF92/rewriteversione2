<?php
/**
 * Shortcode [llm_riviste_indice] — card riviste di prima pagina per coppia.
 *
 * Sezioni raggruppate per lingua conosciuta (IT, EN, PL).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Magazine_Index_Shortcode {

	const SHORTCODE = 'llm_riviste_indice';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Gruppi per lingua conosciuta.
	 *
	 * @return array<int,array{known:string,ui:string,targets:string[]}>
	 */
	private static function section_groups() {
		return array(
			array(
				'known'   => 'it',
				'ui'      => 'it',
				'targets' => array( 'en', 'pl' ),
			),
			array(
				'known'   => 'en',
				'ui'      => 'en',
				'targets' => array( 'it' ),
			),
			array(
				'known'   => 'pl',
				'ui'      => 'pl',
				'targets' => array( 'it' ),
			),
		);
	}

	/**
	 * @param array<string,string> $atts Attributi (non usati).
	 * @return string
	 */
	public static function render( $atts = array() ) {
		unset( $atts );
		self::enqueue();

		ob_start();
		?>
		<div class="llm-mag-index llm-ui-scope">
			<?php foreach ( self::section_groups() as $group ) : ?>
				<?php echo self::render_section_group( $group ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già escapato. ?>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array{known:string,ui:string,targets:string[]} $group Gruppo sezione.
	 * @return string
	 */
	private static function render_section_group( $group ) {
		$known   = sanitize_key( $group['known'] );
		$ui      = sanitize_key( $group['ui'] );
		$targets = array_map( 'sanitize_key', (array) $group['targets'] );
		$cards   = array();

		foreach ( $targets as $target ) {
			if ( ! LLM_Languages::is_valid( $target ) || $known === $target ) {
				continue;
			}
			$mag_id = LLM_Magazine::find_homepage_id( $known, $target );
			if ( ! $mag_id ) {
				continue;
			}
			$pair_url = class_exists( 'LLM_Home_Redirect' ) ? LLM_Home_Redirect::pair_url( $known, $target ) : '';
			$card     = self::render_card( $mag_id, $pair_url, $ui, $known, $target );
			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}

		if ( empty( $cards ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return '';
			}
			$cards[] = '<p class="llm-mag-index__empty">' . esc_html(
				sprintf(
					/* translators: %s: known language label */
					__( 'Nessuna rivista di prima pagina per chi conosce %s.', 'llm-con-tabelle' ),
					self::lang_name_in_ui( $known, $ui )
				)
			) . '</p>';
		}

		ob_start();
		?>
		<section class="llm-mag-index__section" data-known="<?php echo esc_attr( $known ); ?>">
			<h2 class="llm-mag-index__heading"><?php echo esc_html( self::section_heading( $known, $ui ) ); ?></h2>
			<div class="llm-mag-index__grid">
				<?php echo implode( '', $cards ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già escapato. ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param int    $mag_id   ID rivista.
	 * @param string $pair_url URL pagina coppia.
	 * @param string $ui       Lingua UI della sezione.
	 * @param string $known    Lingua conosciuta.
	 * @param string $target   Lingua target.
	 * @return string
	 */
	private static function render_card( $mag_id, $pair_url, $ui, $known, $target ) {
		$mag_id = absint( $mag_id );
		$post   = get_post( $mag_id );
		if ( ! $post || LLM_Magazine::CPT !== $post->post_type ) {
			return '';
		}

		$known        = sanitize_key( $known );
		$target       = sanitize_key( $target );
		$title        = get_the_title( $mag_id );
		$date         = LLM_Magazine::get_date( $mag_id );
		$date_label   = $date ? mysql2date( get_option( 'date_format' ), $date . ' 00:00:00' ) : '';
		$nav          = LLM_Magazine::get_nav_context( $mag_id );
		$issue_num    = ! empty( $nav['issue'] ) ? (int) $nav['issue'] : 0;
		$cover_id     = (int) get_post_thumbnail_id( $mag_id );
		$cover_url    = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium_large' ) : '';
		$known_flag   = LLM_Languages::flag_emoji( $known );
		$target_flag  = LLM_Languages::flag_emoji( $target );
		$contents     = self::contents_summary( $mag_id, $ui );
		$learn_label  = self::learn_label( $target, $ui );
		$subtitle     = $issue_num > 0
			? sprintf( self::edition_label( $ui ), sprintf( '%02d', $issue_num ) )
			: self::pair_label( $known, $target, $ui );

		$tag   = $pair_url ? 'a' : 'div';
		$attrs = $pair_url
			? ' href="' . esc_url( $pair_url ) . '"'
			: ' role="group"';

		ob_start();
		?>
		<article class="llm-mag-index__card">
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a|div. ?> class="llm-mag-index__card-link"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già escapato. ?>>
				<div
					class="llm-mag-index__cover<?php echo $cover_url ? '' : ' llm-mag-index__cover--empty'; ?>"
					<?php if ( $cover_url ) : ?>
						style="background-image:url('<?php echo esc_url( $cover_url ); ?>');"
					<?php endif; ?>
					role="img"
					aria-label="<?php echo esc_attr( $title ? $title : __( 'Copertina rivista', 'llm-con-tabelle' ) ); ?>"
				>
					<span class="llm-mag-index__flags" aria-hidden="true">
						<span class="llm-mag-index__flag"><?php echo esc_html( $known_flag ); ?></span>
						<span class="llm-mag-index__flags-arrow">→</span>
						<span class="llm-mag-index__flag"><?php echo esc_html( $target_flag ); ?></span>
					</span>
				</div>
				<div class="llm-mag-index__body">
					<?php if ( $learn_label ) : ?>
						<p class="llm-mag-index__learn"><?php echo esc_html( $learn_label ); ?></p>
					<?php endif; ?>
					<?php if ( $subtitle ) : ?>
						<p class="llm-mag-index__kicker"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
					<?php if ( $title ) : ?>
						<h3 class="llm-mag-index__title"><?php echo esc_html( $title ); ?></h3>
					<?php endif; ?>
					<?php if ( $date_label ) : ?>
						<p class="llm-mag-index__date">
							<time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( $date_label ); ?></time>
						</p>
					<?php endif; ?>
					<?php if ( $contents ) : ?>
						<p class="llm-mag-index__contents"><?php echo esc_html( $contents ); ?></p>
					<?php endif; ?>
				</div>
			</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a|div. ?>>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param int    $mag_id ID rivista.
	 * @param string $ui     Lingua UI.
	 * @return string
	 */
	private static function contents_summary( $mag_id, $ui ) {
		$crossword = LLM_Magazine::get_crossword_id( $mag_id ) ? 1 : 0;
		$stories   = count( LLM_Magazine::get_story_ids( $mag_id ) );
		$music     = count( LLM_Magazine::get_music_ids( $mag_id ) );
		$quiz      = count( LLM_Magazine::get_quiz_question_ids( $mag_id ) );
		$videos    = count( LLM_Magazine::get_videos( $mag_id ) );
		$idiom     = LLM_Magazine::get_idiom_id( $mag_id ) ? 1 : 0;

		$parts = array();
		if ( $crossword ) {
			$parts[] = self::count_label( $crossword, 'crossword', $ui );
		}
		if ( $stories ) {
			$parts[] = self::count_label( $stories, 'stories', $ui );
		}
		if ( $music ) {
			$parts[] = self::count_label( $music, 'music', $ui );
		}
		if ( $quiz ) {
			$parts[] = self::count_label( $quiz, 'quiz', $ui );
		}
		if ( $videos ) {
			$parts[] = self::count_label( $videos, 'videos', $ui );
		}
		if ( $idiom ) {
			$parts[] = self::count_label( $idiom, 'idiom', $ui );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * @param int    $n    Quantità.
	 * @param string $type Tipo contenuto.
	 * @param string $ui   Lingua UI.
	 * @return string
	 */
	private static function count_label( $n, $type, $ui ) {
		$n = absint( $n );
		$map = array(
			'it' => array(
				'crossword' => array( '1 cruciverba', '%d cruciverba' ),
				'stories'   => array( '1 storia', '%d storie' ),
				'music'     => array( '1 brano', '%d brani' ),
				'quiz'      => array( '1 quiz', '%d quiz' ),
				'videos'    => array( '1 video', '%d video' ),
				'idiom'     => array( '1 espressione', '%d espressioni' ),
			),
			'pl' => array(
				'crossword' => array( '1 krzyżówka', '%d krzyżówki' ),
				'stories'   => array( '1 historia', '%d historie' ),
				'music'     => array( '1 utwór', '%d utwory' ),
				'quiz'      => array( '1 quiz', '%d quizy' ),
				'videos'    => array( '1 film', '%d filmy' ),
				'idiom'     => array( '1 wyrażenie', '%d wyrażenia' ),
			),
			'en' => array(
				'crossword' => array( '1 crossword', '%d crosswords' ),
				'stories'   => array( '1 story', '%d stories' ),
				'music'     => array( '1 song', '%d songs' ),
				'quiz'      => array( '1 quiz', '%d quizzes' ),
				'videos'    => array( '1 video', '%d videos' ),
				'idiom'     => array( '1 idiom', '%d idioms' ),
			),
		);
		$ui   = isset( $map[ $ui ] ) ? $ui : 'it';
		$pair = isset( $map[ $ui ][ $type ] ) ? $map[ $ui ][ $type ] : array( (string) $n, '%d' );
		if ( 1 === $n ) {
			return $pair[0];
		}
		return sprintf( $pair[1], $n );
	}

	/**
	 * @param string $known Lingua conosciuta.
	 * @param string $ui    Lingua UI.
	 * @return string
	 */
	private static function section_heading( $known, $ui ) {
		$known_name = self::lang_name_in_ui( $known, $ui );
		$templates  = array(
			'it' => 'Riviste se conosci %s:',
			'en' => 'Magazines if you know %s:',
			'pl' => 'Czasopisma, jeśli znasz %s:',
		);
		$tpl = isset( $templates[ $ui ] ) ? $templates[ $ui ] : $templates['it'];

		if ( 'it' === $ui && 'it' === $known ) {
			return 'Riviste se conosci l\'italiano:';
		}
		if ( 'it' === $ui && 'en' === $known ) {
			return 'Riviste se conosci l\'inglese:';
		}
		if ( 'it' === $ui && 'pl' === $known ) {
			return 'Riviste se conosci il polacco:';
		}

		return sprintf( $tpl, $known_name );
	}

	/**
	 * @param string $target Lingua da imparare.
	 * @param string $ui     Lingua UI.
	 * @return string
	 */
	private static function learn_label( $target, $ui ) {
		$target_name = self::lang_name_in_ui( $target, $ui );
		$templates   = array(
			'it' => 'Impara %s',
			'en' => 'Learn %s',
			'pl' => 'Ucz się %s',
		);
		$tpl = isset( $templates[ $ui ] ) ? $templates[ $ui ] : $templates['it'];

		if ( 'it' === $ui ) {
			$articles = array(
				'it' => "l'italiano",
				'en' => "l'inglese",
				'pl' => 'il polacco',
				'es' => 'lo spagnolo',
			);
			if ( isset( $articles[ $target ] ) ) {
				return 'Impara ' . $articles[ $target ];
			}
		}

		return sprintf( $tpl, $target_name );
	}

	/**
	 * @param string $code Codice lingua.
	 * @param string $ui   Lingua UI.
	 * @return string
	 */
	private static function lang_name_in_ui( $code, $ui ) {
		$names = array(
			'it' => array(
				'it' => 'italiano',
				'en' => 'inglese',
				'pl' => 'polacco',
				'es' => 'spagnolo',
			),
			'pl' => array(
				'it' => 'włoski',
				'en' => 'angielski',
				'pl' => 'polski',
				'es' => 'hiszpański',
			),
			'en' => array(
				'it' => 'Italian',
				'en' => 'English',
				'pl' => 'Polish',
				'es' => 'Spanish',
			),
		);
		$ui = isset( $names[ $ui ] ) ? $ui : 'it';
		return isset( $names[ $ui ][ $code ] ) ? $names[ $ui ][ $code ] : LLM_Languages::label( $code );
	}

	/**
	 * @param string $ui Lingua UI.
	 * @return string Printf template con %s.
	 */
	private static function edition_label( $ui ) {
		$t = array(
			'it' => 'Edizione #%s',
			'pl' => 'Wydanie #%s',
			'en' => 'Issue #%s',
		);
		return isset( $t[ $ui ] ) ? $t[ $ui ] : $t['it'];
	}

	/**
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua target.
	 * @param string $ui     Lingua UI.
	 * @return string
	 */
	private static function pair_label( $known, $target, $ui ) {
		return self::lang_name_in_ui( $known, $ui ) . ' → ' . self::lang_name_in_ui( $target, $ui );
	}

	private static function enqueue() {
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-magazine-index',
			LLM_TABELLE_URL . 'assets/llm-magazine-index.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
	}
}
