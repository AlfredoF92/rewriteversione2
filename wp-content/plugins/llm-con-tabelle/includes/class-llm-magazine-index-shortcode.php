<?php
/**
 * Shortcode [llm_riviste_indice] — card riviste di prima pagina per coppia.
 *
 * Pulsanti lingua (IT / EN): le card si ricaricano sotto via JSON.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Magazine_Index_Shortcode {

	const SHORTCODE    = 'llm_riviste_indice';
	const AJAX_ACTION  = 'llm_riviste_indice_cards';
	const NONCE_ACTION = 'llm_riviste_indice';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_cards' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_cards' ) );
	}

	/**
	 * Gruppi per lingua conosciuta.
	 *
	 * @return array<int,array{known:string,ui:string,targets:string[],label:string}>
	 */
	private static function section_groups() {
		return array(
			array(
				'known'   => 'it',
				'ui'      => 'it',
				'targets' => array( 'en', 'pl' ),
				'label'   => 'Italiano',
			),
			array(
				'known'   => 'en',
				'ui'      => 'en',
				'targets' => array( 'it' ),
				'label'   => 'Inglese',
			),
		);
	}

	/**
	 * @param string $known Lingua conosciuta.
	 * @return array{known:string,ui:string,targets:string[],label:string}|null
	 */
	private static function group_for_known( $known ) {
		$known = sanitize_key( $known );
		foreach ( self::section_groups() as $group ) {
			if ( $group['known'] === $known ) {
				return $group;
			}
		}
		return null;
	}

	/**
	 * @param array<string,string> $atts Attributi (non usati).
	 * @return string
	 */
	public static function render( $atts = array() ) {
		unset( $atts );
		self::enqueue();

		$default_known = 'it';
		$payload       = self::payload_for_known( $default_known );
		$grid_id       = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'llm-mag-index-grid-' ) : uniqid( 'llm-mag-index-grid-', false );

		ob_start();
		?>
		<div
			class="llm-mag-index llm-ui-scope"
			data-llm-mag-index
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
			data-known="<?php echo esc_attr( $default_known ); ?>"
			role="region"
			aria-label="<?php echo esc_attr( __( 'Seleziona la lingua', 'llm-con-tabelle' ) ); ?>"
		>
			<div class="llm-mag-index__langs" role="tablist" aria-label="<?php echo esc_attr( __( 'Lingua', 'llm-con-tabelle' ) ); ?>">
				<?php foreach ( self::section_groups() as $group ) : ?>
					<?php
					$known     = $group['known'];
					$is_active = ( $known === $default_known );
					$flag      = LLM_Languages::flag_emoji( $known );
					?>
					<button
						type="button"
						class="llm-mag-index__lang<?php echo $is_active ? ' is-active' : ''; ?>"
						data-known="<?php echo esc_attr( $known ); ?>"
						aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
						aria-controls="<?php echo esc_attr( $grid_id ); ?>"
					>
						<?php if ( $flag ) : ?>
							<span class="llm-mag-index__lang-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span>
						<?php endif; ?>
						<span class="llm-mag-index__lang-label"><?php echo esc_html( $group['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="llm-mag-index__grid" id="<?php echo esc_attr( $grid_id ); ?>" data-llm-mag-grid>
				<?php echo self::render_grid_from_payload( $payload ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già escapato. ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: JSON con le card per la lingua selezionata.
	 */
	public static function ajax_cards() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$known   = isset( $_POST['known'] ) ? sanitize_key( wp_unslash( $_POST['known'] ) ) : 'it';
		$payload = self::payload_for_known( $known );
		if ( null === $payload ) {
			wp_send_json_error(
				array( 'message' => __( 'Lingua non valida.', 'llm-con-tabelle' ) ),
				400
			);
		}

		wp_send_json_success( $payload );
	}

	/**
	 * @param string $known Lingua conosciuta.
	 * @return array{known:string,cards:array<int,array<string,string>>,empty:string}|null
	 */
	private static function payload_for_known( $known ) {
		$group = self::group_for_known( $known );
		if ( ! $group ) {
			return null;
		}

		$known   = $group['known'];
		$ui      = $group['ui'];
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
			$card     = self::card_data( $mag_id, $pair_url, $ui, $known, $target );
			if ( $card ) {
				$cards[] = $card;
			}
		}

		return array(
			'known' => $known,
			'cards' => $cards,
			'empty' => sprintf(
				/* translators: %s: known language label */
				__( 'Nessuna rivista di prima pagina per chi conosce %s.', 'llm-con-tabelle' ),
				self::lang_name_in_ui( $known, $ui )
			),
		);
	}

	/**
	 * @param array{cards?:array<int,array<string,string>>,empty?:string} $payload Payload JSON.
	 * @return string
	 */
	private static function render_grid_from_payload( $payload ) {
		$cards = isset( $payload['cards'] ) && is_array( $payload['cards'] ) ? $payload['cards'] : array();
		if ( empty( $cards ) ) {
			$empty = isset( $payload['empty'] ) ? (string) $payload['empty'] : '';
			return '<p class="llm-mag-index__empty">' . esc_html( $empty ) . '</p>';
		}
		$html = '';
		foreach ( $cards as $card ) {
			$html .= self::render_card_html( $card );
		}
		return $html;
	}

	/**
	 * @param int    $mag_id   ID rivista.
	 * @param string $pair_url URL pagina coppia.
	 * @param string $ui       Lingua UI della sezione.
	 * @param string $known    Lingua conosciuta.
	 * @param string $target   Lingua target.
	 * @return array<string,string>|null
	 */
	private static function card_data( $mag_id, $pair_url, $ui, $known, $target ) {
		$mag_id = absint( $mag_id );
		$post   = get_post( $mag_id );
		if ( ! $post || LLM_Magazine::CPT !== $post->post_type ) {
			return null;
		}

		$known      = sanitize_key( $known );
		$target     = sanitize_key( $target );
		$title      = get_the_title( $mag_id );
		$date       = LLM_Magazine::get_date( $mag_id );
		$date_label = $date ? mysql2date( get_option( 'date_format' ), $date . ' 00:00:00' ) : '';
		$nav        = LLM_Magazine::get_nav_context( $mag_id );
		$issue_num  = ! empty( $nav['issue'] ) ? (int) $nav['issue'] : 0;
		$cover_id   = (int) get_post_thumbnail_id( $mag_id );
		$cover_url  = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium_large' ) : '';
		$subtitle   = $issue_num > 0
			? sprintf( self::edition_label( $ui ), sprintf( '%02d', $issue_num ) )
			: self::pair_label( $known, $target, $ui );

		return array(
			'url'        => (string) $pair_url,
			'title'      => (string) $title,
			'cover'      => (string) $cover_url,
			'date'       => (string) $date,
			'dateLabel'  => (string) $date_label,
			'learn'      => self::learn_label( $target, $ui ),
			'kicker'     => (string) $subtitle,
			'contents'   => self::contents_summary( $mag_id, $ui ),
			'knownFlag'  => LLM_Languages::flag_emoji( $known ),
			'targetFlag' => LLM_Languages::flag_emoji( $target ),
		);
	}

	/**
	 * @param array<string,string> $card Dati card.
	 * @return string
	 */
	private static function render_card_html( $card ) {
		$url         = isset( $card['url'] ) ? (string) $card['url'] : '';
		$title       = isset( $card['title'] ) ? (string) $card['title'] : '';
		$cover       = isset( $card['cover'] ) ? (string) $card['cover'] : '';
		$date        = isset( $card['date'] ) ? (string) $card['date'] : '';
		$date_label  = isset( $card['dateLabel'] ) ? (string) $card['dateLabel'] : '';
		$learn       = isset( $card['learn'] ) ? (string) $card['learn'] : '';
		$kicker      = isset( $card['kicker'] ) ? (string) $card['kicker'] : '';
		$contents    = isset( $card['contents'] ) ? (string) $card['contents'] : '';
		$known_flag  = isset( $card['knownFlag'] ) ? (string) $card['knownFlag'] : '';
		$target_flag = isset( $card['targetFlag'] ) ? (string) $card['targetFlag'] : '';
		$cover_aria  = $title ? $title : __( 'Copertina rivista', 'llm-con-tabelle' );

		$tag   = $url ? 'a' : 'div';
		$attrs = $url
			? ' href="' . esc_url( $url ) . '"'
			: ' role="group"';

		ob_start();
		?>
		<article class="llm-mag-index__card">
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a|div. ?> class="llm-mag-index__card-link"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già escapato. ?>>
				<div
					class="llm-mag-index__cover<?php echo $cover ? '' : ' llm-mag-index__cover--empty'; ?>"
					<?php if ( $cover ) : ?>
						style="background-image:url('<?php echo esc_url( $cover ); ?>');"
					<?php endif; ?>
					role="img"
					aria-label="<?php echo esc_attr( $cover_aria ); ?>"
				>
					<span class="llm-mag-index__flags" aria-hidden="true">
						<span class="llm-mag-index__flag"><?php echo esc_html( $known_flag ); ?></span>
						<span class="llm-mag-index__flags-arrow">→</span>
						<span class="llm-mag-index__flag"><?php echo esc_html( $target_flag ); ?></span>
					</span>
				</div>
				<div class="llm-mag-index__body">
					<?php if ( $learn ) : ?>
						<p class="llm-mag-index__learn"><?php echo esc_html( $learn ); ?></p>
					<?php endif; ?>
					<?php if ( $kicker ) : ?>
						<p class="llm-mag-index__kicker"><?php echo esc_html( $kicker ); ?></p>
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
		wp_enqueue_script(
			'llm-magazine-index',
			LLM_TABELLE_URL . 'assets/llm-magazine-index.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
	}
}
