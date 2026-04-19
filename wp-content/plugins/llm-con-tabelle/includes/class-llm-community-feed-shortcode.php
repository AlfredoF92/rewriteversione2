<?php
/**
 * Shortcode: feed Community — attività di tutti + Bravo (like) per voce.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Community_Feed_Shortcode
 */
class LLM_Community_Feed_Shortcode {

	const SHORTCODE   = 'llm_community_feed';
	const NONCE_ACTION = 'llm_community_feed';
	const QUERY_VAR   = 'llm_cf_paged';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_llm_community_bravo_toggle', array( __CLASS__, 'ajax_bravo_toggle' ) );
		add_action( 'wp_ajax_llm_community_feed_more', array( __CLASS__, 'ajax_feed_more' ) );
		add_action( 'wp_ajax_nopriv_llm_community_feed_more', array( __CLASS__, 'ajax_feed_more' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
	}

	/**
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * AJAX: aggiunge o rimuove Bravo sull’attività.
	 */
	public static function ajax_bravo_toggle() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array( 'message' => LLM_Community_Feed_I18n::get( 'feed_ajax_login_bravo' ) ),
				401
			);
		}

		$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => LLM_Community_Feed_I18n::get( 'feed_ajax_invalid' ) ), 400 );
		}

		$post = get_post( $activity_id );
		if ( ! $post || LLM_ACTIVITY_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => LLM_Community_Feed_I18n::get( 'feed_ajax_not_found' ) ), 404 );
		}

		$uid = get_current_user_id();
		$liked = LLM_Community::user_has_kudos( $activity_id, $uid );

		if ( $liked ) {
			LLM_Community::remove_bravo( $activity_id, $uid );
		} else {
			$ok = LLM_Community::add_bravo( $activity_id, $uid );
			if ( ! $ok ) {
				wp_send_json_error(
					array( 'message' => LLM_Community_Feed_I18n::get( 'feed_cannot_bravo' ) ),
					403
				);
			}
		}

		wp_send_json_success(
			array(
				'count' => LLM_Community::count_kudos( $activity_id ),
				'liked' => LLM_Community::user_has_kudos( $activity_id, $uid ),
			)
		);
	}

	/**
	 * AJAX: altre voci del feed (Carica altro).
	 */
	public static function ajax_feed_more() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 15;
		if ( $page < 1 ) {
			$page = 1;
		}
		if ( $per_page < 1 ) {
			$per_page = 15;
		}
		if ( $per_page > 50 ) {
			$per_page = 50;
		}

		$q = new WP_Query(
			array(
				'post_type'              => LLM_ACTIVITY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
			)
		);

		$viewer_id = is_user_logged_in() ? get_current_user_id() : 0;
		$html      = '';
		while ( $q->have_posts() ) {
			$q->the_post();
			$item_post = get_post();
			if ( $item_post instanceof WP_Post ) {
				$html .= self::render_activity_item( $item_post, $viewer_id );
			}
		}
		wp_reset_postdata();

		$max_pages = (int) $q->max_num_pages;
		$has_more  = ( $page < $max_pages );

		wp_send_json_success(
			array(
				'html'      => $html,
				'has_more'  => $has_more,
				'next_page' => $has_more ? $page + 1 : $page,
			)
		);
	}

	/**
	 * @param int $story_id ID storia.
	 * @return string URL pubblica o vuota.
	 */
	private static function story_url( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || LLM_STORY_CPT !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
			return '';
		}
		$url = get_permalink( $story_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Titolo storia: link cliccabile o testo in evidenza se non pubblica.
	 *
	 * @param string $story_url   URL o vuoto.
	 * @param string $story_title Titolo.
	 * @return string HTML sicuro.
	 */
	private static function render_story_title_element( $story_url, $story_title ) {
		$story_title = (string) $story_title;
		if ( $story_url !== '' ) {
			return '<a class="llm-ui-link llm-community-activity__story-link" href="' . esc_url( $story_url ) . '"><span class="llm-community-activity__story-title">' . esc_html( $story_title ) . '</span></a>';
		}
		return '<span class="llm-community-activity__story-title llm-community-activity__story-title--plain">' . esc_html( $story_title ) . '</span>';
	}

	/**
	 * Blocco frase principale (nome in grassetto, storia cliccabile, testo frase + traduzione).
	 *
	 * @param int    $aid         ID attività.
	 * @param string $name        Nome utente (plain).
	 * @param int    $story_id    ID storia.
	 * @param string $story_url   URL storia.
	 * @param string $story_title Titolo storia.
	 * @return string HTML.
	 */
	private static function render_activity_sentence_html( $aid, $name, $story_id, $story_url, $story_title ) {
		$aid         = absint( $aid );
		$story_id    = absint( $story_id );
		$story_url   = (string) $story_url;
		$story_title = (string) $story_title;
		$type        = (string) get_post_meta( $aid, LLM_Community::META_TYPE, true );
		$phrase_raw  = get_post_meta( $aid, LLM_Community::META_PHRASE, true );
		$phrase_ix   = ( $phrase_raw !== '' && false !== $phrase_raw ) ? (int) $phrase_raw : 0;

		$html  = '<div class="llm-community-activity__copy">';
		$html .= '<p class="llm-community-activity__sentence">';
		$html .= '<strong class="llm-community-activity__name">' . esc_html( $name ) . '</strong> ';

		if ( LLM_Community::TYPE_PHRASE === $type ) {
			$n = $phrase_ix + 1;
			$html .= esc_html( sprintf( LLM_Community_Feed_I18n::get( 'feed_completed_phrase_mid' ), $n ) );
			$html .= self::render_story_title_element( $story_url, $story_title );
			$html .= '</p>';
			$row    = $story_id ? LLM_Story_Repository::get_phrase_at( $story_id, $phrase_ix ) : null;
			$target = $row ? trim( (string) $row['target'] ) : '';
			$iface  = $row ? trim( (string) $row['interface'] ) : '';
			if ( $target !== '' || $iface !== '' ) {
				$html .= '<div class="llm-community-activity__phrase-stack">';
				if ( $target !== '' ) {
					$html .= '<p class="llm-community-activity__phrase-target">' . esc_html( $target ) . '</p>';
				}
				if ( $iface !== '' ) {
					$html .= '<p class="llm-community-activity__phrase-translation">' . esc_html( $iface ) . '</p>';
				}
				$html .= '</div>';
			}
		} else {
			if ( LLM_Community::TYPE_STORY_DONE === $type ) {
				$html .= esc_html( LLM_Community_Feed_I18n::get( 'feed_completed_story_mid' ) );
			} elseif ( LLM_Community::TYPE_STORY_START === $type ) {
				$html .= esc_html( LLM_Community_Feed_I18n::get( 'feed_unlocked_story_mid' ) );
			} else {
				$html .= esc_html( LLM_Community_Feed_I18n::get( 'feed_generic_story_mid' ) );
			}
			$html .= self::render_story_title_element( $story_url, $story_title );
			$html .= '</p>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Riga lingua studio utente → lingua obiettivo storia (etichette nella lingua UI del visitatore).
	 *
	 * @param int $author_id ID autore attività.
	 * @param int $story_id  ID storia.
	 * @return string HTML o vuoto.
	 */
	private static function render_language_row( $author_id, $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return '';
		}
		$learn  = sanitize_key( (string) get_user_meta( $author_id, LLM_User_Meta::LEARNING_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		$learn_l = LLM_Languages::is_valid( $learn )
			? LLM_Phrase_Game_I18n::target_lang_label_for_ui( $learn )
			: LLM_Community_Feed_I18n::get( 'feed_lang_unknown' );
		$target_l = LLM_Languages::is_valid( $target )
			? LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target )
			: LLM_Community_Feed_I18n::get( 'feed_lang_unknown' );
		$text = LLM_Community_Feed_I18n::format( 'feed_lang_row', $learn_l, $target_l );
		return '<p class="llm-community-activity__lang-row llm-ui-text--small llm-ui-text--muted">' . esc_html( $text ) . '</p>';
	}

	/**
	 * @param int $activity_id ID attività.
	 * @param int $viewer_id   Utente che guarda (0 ospite).
	 * @return string HTML pulsante like.
	 */
	private static function render_like_block( $activity_id, $viewer_id ) {
		$activity_id = absint( $activity_id );
		$count       = LLM_Community::count_kudos( $activity_id );
		$post        = get_post( $activity_id );
		$author_id   = $post ? (int) $post->post_author : 0;

		$bravo = LLM_Community_Feed_I18n::get( 'feed_bravo' );

		if ( ! $viewer_id ) {
			$inner = '<div class="llm-community-like llm-community-like--guest llm-community-like--prominent">'
				. '<span class="llm-community-like__label">' . esc_html( $bravo ) . '</span> '
				. '<span class="llm-community-like__count">' . esc_html( (string) $count ) . '</span>'
				. '<span class="llm-community-like__hint">' . esc_html( LLM_Community_Feed_I18n::get( 'feed_login_bravo_hint' ) ) . '</span>'
				. '</div>';
			return self::wrap_motivate_block( $inner, LLM_Community_Feed_I18n::get( 'feed_motivate_guest_caption' ) );
		}

		$is_own = ( $viewer_id === $author_id );
		if ( $is_own ) {
			$inner = '<div class="llm-community-like llm-community-like--readonly llm-community-like--prominent" role="status" aria-label="' . esc_attr( LLM_Community_Feed_I18n::get( 'feed_bravo_received_aria' ) ) . '">'
				. '<span class="llm-community-like__label llm-community-like__label--muted">' . esc_html( $bravo ) . '</span> '
				. '<span class="llm-community-like__count">' . esc_html( (string) $count ) . '</span>'
				. '</div>';
			return self::wrap_motivate_block( $inner, LLM_Community_Feed_I18n::get( 'feed_motivate_own_caption' ) );
		}

		$liked = LLM_Community::user_has_kudos( $activity_id, $viewer_id );
		$btn_classes = 'llm-ui-btn llm-community-like llm-community-like--prominent' . ( $liked ? ' is-active llm-ui-btn--primary' : ' llm-ui-btn--ghost' );
		$aria = LLM_Community_Feed_I18n::format( 'feed_bravo_aria', (int) $count );

		$inner = '<button type="button" class="' . esc_attr( $btn_classes ) . '" data-activity-id="' . esc_attr( (string) $activity_id ) . '" aria-pressed="' . ( $liked ? 'true' : 'false' ) . '" aria-label="' . esc_attr( $aria ) . '">'
			. '<span class="llm-community-like__label">' . esc_html( $bravo ) . '</span> '
			. '<span class="llm-community-like__count">' . esc_html( (string) $count ) . '</span>'
			. '</button>';
		return self::wrap_motivate_block( $inner, LLM_Community_Feed_I18n::get( 'feed_motivate_caption' ) );
	}

	/**
	 * Wrapper con didascalia sopra il blocco Bravo (allineato a destra).
	 *
	 * @param string $inner_html Markup interno (già esc_* dove serve).
	 * @param string $caption    Testo sopra.
	 * @return string
	 */
	private static function wrap_motivate_block( $inner_html, $caption ) {
		return '<div class="llm-community-motivate">'
			. '<span class="llm-community-motivate__caption">' . esc_html( $caption ) . '</span>'
			. '<div class="llm-community-motivate__action">' . $inner_html . '</div>'
			. '</div>';
	}

	/**
	 * @param \WP_Post $post       Attività.
	 * @param int      $viewer_id Utente corrente.
	 * @return string HTML articolo.
	 */
	private static function render_activity_item( WP_Post $post, $viewer_id ) {
		$aid    = (int) $post->ID;
		$author = (int) $post->post_author;
		$au     = get_userdata( $author );
		$story  = absint( get_post_meta( $aid, LLM_Community::META_STORY, true ) );
		$name   = $au ? $au->display_name : LLM_Community_Feed_I18n::format( 'feed_user_num', $author );

		$local_ts = (int) get_post_time( 'U', false, $post );
		$date_str = $local_ts > 0
			? wp_date( get_option( 'date_format' ) . ' — ' . get_option( 'time_format' ), $local_ts, wp_timezone() )
			: get_the_date( '', $post );
		$gmt_ts   = (int) get_post_time( 'U', true, $post );
		$iso_time = $gmt_ts > 0 ? gmdate( 'c', $gmt_ts ) : '';

		$story_url   = self::story_url( $story );
		$story_title = get_the_title( $story );
		if ( ! $story_title ) {
			$story_title = '#' . $story;
		}

		$sentence_html = self::render_activity_sentence_html( $aid, $name, $story, $story_url, $story_title );
		$lang_html     = self::render_language_row( $author, $story );

		ob_start();
		?>
		<li>
			<article class="llm-ui-card llm-community-activity" id="llm-activity-<?php echo esc_attr( (string) $aid ); ?>">
				<div class="llm-ui-card__body">
					<div class="llm-community-activity__grid">
						<div class="llm-community-activity__main">
							<?php echo $sentence_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup da helper con esc_* ?>
							<?php echo $lang_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<time class="llm-community-activity__when llm-ui-text--small llm-ui-text--muted" datetime="<?php echo esc_attr( $iso_time ); ?>"><?php echo esc_html( $date_str ); ?></time>
						</div>
						<div class="llm-community-activity__like">
							<?php echo self::render_like_block( $aid, $viewer_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup controllata ?>
						</div>
					</div>
				</div>
			</article>
		</li>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$raw = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts(
			array(
				'per_page' => '15',
			),
			$raw,
			self::SHORTCODE
		);

		if ( array_key_exists( 'title', $raw ) ) {
			$feed_title = (string) $raw['title'];
		} else {
			$feed_title = LLM_Community_Feed_I18n::get( 'feed_title_community' );
		}

		$per_page = absint( $atts['per_page'] );
		if ( $per_page < 1 ) {
			$per_page = 15;
		}
		if ( $per_page > 50 ) {
			$per_page = 50;
		}

		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-community-feed',
			LLM_TABELLE_URL . 'assets/llm-community-feed.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-community-feed',
			LLM_TABELLE_URL . 'assets/llm-community-feed.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-community-feed',
			'llmCommunityFeed',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'error'     => LLM_Community_Feed_I18n::get( 'feed_error' ),
					'loadMore'  => LLM_Community_Feed_I18n::get( 'feed_load_more' ),
					'loading'   => LLM_Community_Feed_I18n::get( 'feed_loading' ),
					'bravoAria' => LLM_Community_Feed_I18n::get( 'feed_bravo_aria' ),
				),
			)
		);

		$q = new WP_Query(
			array(
				'post_type'              => LLM_ACTIVITY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => 1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
			)
		);

		$viewer_id = is_user_logged_in() ? get_current_user_id() : 0;
		$max_pages = (int) $q->max_num_pages;
		$has_more  = $max_pages > 1;
		$next_page = $has_more ? 2 : 0;

		ob_start();
		?>
		<div
			class="llm-ui-scope llm-ui-scope--light llm-community-feed"
			data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
			data-next-page="<?php echo esc_attr( (string) $next_page ); ?>"
			data-has-more="<?php echo $has_more ? '1' : '0'; ?>"
		>
			<?php if ( $feed_title !== '' ) : ?>
				<h2 class="llm-ui-heading llm-ui-heading--section llm-community-feed__title"><?php echo esc_html( $feed_title ); ?></h2>
			<?php endif; ?>

			<?php if ( ! $q->have_posts() ) : ?>
				<p class="llm-ui-notice llm-community-feed__empty"><?php echo esc_html( LLM_Community_Feed_I18n::get( 'feed_empty' ) ); ?></p>
			<?php else : ?>
				<ul class="llm-ui-feed llm-community-feed__list">
					<?php
					while ( $q->have_posts() ) {
						$q->the_post();
						$item_post = get_post();
						if ( $item_post instanceof WP_Post ) {
							echo self::render_activity_item( $item_post, $viewer_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
					wp_reset_postdata();
					?>
				</ul>
				<?php if ( $has_more ) : ?>
					<div class="llm-community-feed__load-wrap">
						<button type="button" class="llm-ui-btn llm-ui-btn--ghost llm-community-feed__load-more" aria-busy="false">
							<?php echo esc_html( LLM_Community_Feed_I18n::get( 'feed_load_more' ) ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
