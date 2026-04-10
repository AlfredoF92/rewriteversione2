<?php
/**
 * Shortcode: frasi completate e attività storia solo per l’utente loggato.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_User_Progress_Feed_Shortcode
 */
class LLM_User_Progress_Feed_Shortcode {

	const SHORTCODE = 'llm_user_progress_feed';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param int $story_id ID storia.
	 * @return string
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
	 * @param string $story_url   URL.
	 * @param string $story_title Titolo.
	 * @return string HTML sicuro.
	 */
	private static function render_story_title_element( $story_url, $story_title ) {
		$story_title = (string) $story_title;
		if ( $story_url !== '' ) {
			return '<a class="llm-ui-link llm-user-progress__story-link" href="' . esc_url( $story_url ) . '"><span class="llm-user-progress__story-title">' . esc_html( $story_title ) . '</span></a>';
		}
		return '<span class="llm-user-progress__story-title llm-user-progress__story-title--plain">' . esc_html( $story_title ) . '</span>';
	}

	/**
	 * @param int    $aid         ID attività.
	 * @param int    $story_id    ID storia.
	 * @param string $story_url   URL.
	 * @param string $story_title Titolo.
	 * @return string HTML.
	 */
	private static function render_sentence_block( $aid, $story_id, $story_url, $story_title ) {
		$aid         = absint( $aid );
		$story_id    = absint( $story_id );
		$story_url   = (string) $story_url;
		$story_title = (string) $story_title;
		$type        = (string) get_post_meta( $aid, LLM_Community::META_TYPE, true );
		$phrase_raw  = get_post_meta( $aid, LLM_Community::META_PHRASE, true );
		$phrase_ix   = ( $phrase_raw !== '' && false !== $phrase_raw ) ? (int) $phrase_raw : 0;

		$html  = '<div class="llm-user-progress__copy">';
		$html .= '<p class="llm-user-progress__sentence">';

		if ( LLM_Community::TYPE_PHRASE === $type ) {
			$n = $phrase_ix + 1;
			$html .= esc_html( sprintf( LLM_User_Progress_Feed_I18n::get( 'progress_phrase_mid' ), $n ) );
			$html .= self::render_story_title_element( $story_url, $story_title );
			$html .= '</p>';
			$row    = $story_id ? LLM_Story_Repository::get_phrase_at( $story_id, $phrase_ix ) : null;
			$target = $row ? trim( (string) $row['target'] ) : '';
			$iface  = $row ? trim( (string) $row['interface'] ) : '';
			if ( $target !== '' || $iface !== '' ) {
				$html .= '<div class="llm-user-progress__phrase-stack">';
				if ( $target !== '' ) {
					$html .= '<p class="llm-user-progress__phrase-target">' . esc_html( $target ) . '</p>';
				}
				if ( $iface !== '' ) {
					$html .= '<p class="llm-user-progress__phrase-translation">' . esc_html( $iface ) . '</p>';
				}
				$html .= '</div>';
			}
		} else {
			if ( LLM_Community::TYPE_STORY_DONE === $type ) {
				$html .= esc_html( LLM_User_Progress_Feed_I18n::get( 'progress_story_done_mid' ) );
			} elseif ( LLM_Community::TYPE_STORY_START === $type ) {
				$html .= esc_html( LLM_User_Progress_Feed_I18n::get( 'progress_story_unlock_mid' ) );
			} else {
				$html .= esc_html( LLM_User_Progress_Feed_I18n::get( 'progress_generic_mid' ) );
			}
			$html .= self::render_story_title_element( $story_url, $story_title );
			$html .= '</p>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * @param int $user_id  ID utente (autore attività = viewer).
	 * @param int $story_id ID storia.
	 * @return string HTML.
	 */
	private static function render_language_row( $user_id, $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return '';
		}
		$learn  = sanitize_key( (string) get_user_meta( $user_id, LLM_User_Meta::LEARNING_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		$learn_l = LLM_Languages::is_valid( $learn )
			? LLM_Phrase_Game_I18n::target_lang_label_for_ui( $learn )
			: LLM_Community_Feed_I18n::get( 'feed_lang_unknown' );
		$target_l = LLM_Languages::is_valid( $target )
			? LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target )
			: LLM_Community_Feed_I18n::get( 'feed_lang_unknown' );
		$text = LLM_Community_Feed_I18n::format( 'feed_lang_row', $learn_l, $target_l );
		return '<p class="llm-user-progress__lang-row llm-ui-text--small llm-ui-text--muted">' . esc_html( $text ) . '</p>';
	}

	/**
	 * @param \WP_Post $post    Attività.
	 * @param int      $user_id Utente (deve essere autore).
	 * @return string HTML.
	 */
	private static function render_item( WP_Post $post, $user_id ) {
		$aid   = (int) $post->ID;
		$story = absint( get_post_meta( $aid, LLM_Community::META_STORY, true ) );

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

		$sentence = self::render_sentence_block( $aid, $story, $story_url, $story_title );
		$lang_row = self::render_language_row( $user_id, $story );

		ob_start();
		?>
		<li>
			<article class="llm-ui-card llm-user-progress__card" id="llm-user-progress-<?php echo esc_attr( (string) $aid ); ?>">
				<div class="llm-ui-card__body llm-user-progress__body">
					<?php echo $sentence; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $lang_row; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<time class="llm-user-progress__when llm-ui-text--small llm-ui-text--muted" datetime="<?php echo esc_attr( $iso_time ); ?>"><?php echo esc_html( $date_str ); ?></time>
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
				'per_page'   => '30',
				'login_path' => '/login',
			),
			$raw,
			self::SHORTCODE
		);

		if ( array_key_exists( 'title', $raw ) ) {
			$title = (string) $raw['title'];
		} else {
			$title = LLM_User_Progress_Feed_I18n::get( 'progress_title' );
		}

		$per_page = absint( $atts['per_page'] );
		if ( $per_page < 1 ) {
			$per_page = 30;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-user-progress-feed',
			LLM_TABELLE_URL . 'assets/llm-user-progress-feed.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);

		$login_path = trim( (string) $atts['login_path'] );
		if ( $login_path === '' ) {
			$login_path = '/login';
		}
		if ( $login_path[0] !== '/' ) {
			$login_path = '/' . $login_path;
		}
		$login_url = esc_url( home_url( $login_path ) );

		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="llm-ui-scope llm-ui-scope--light llm-user-progress-feed llm-user-progress-feed--guest">
				<p class="llm-ui-notice llm-user-progress-feed__guest-msg"><?php echo esc_html( LLM_User_Progress_Feed_I18n::get( 'progress_guest' ) ); ?></p>
				<p><a class="llm-ui-link" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( LLM_User_Progress_Feed_I18n::get( 'progress_login' ) ); ?></a></p>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		$uid = get_current_user_id();
		if ( ! $uid ) {
			return '';
		}

		$q = new WP_Query(
			array(
				'post_type'              => LLM_ACTIVITY_CPT,
				'post_status'            => 'publish',
				'author'                 => $uid,
				'posts_per_page'         => $per_page,
				'paged'                  => 1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
			)
		);

		ob_start();
		?>
		<div class="llm-ui-scope llm-ui-scope--light llm-user-progress-feed">
			<?php if ( $title !== '' ) : ?>
				<h2 class="llm-ui-heading llm-ui-heading--section llm-user-progress-feed__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( ! $q->have_posts() ) : ?>
				<p class="llm-ui-notice llm-user-progress-feed__empty"><?php echo esc_html( LLM_User_Progress_Feed_I18n::get( 'progress_empty' ) ); ?></p>
			<?php else : ?>
				<ul class="llm-ui-feed llm-user-progress-feed__list">
					<?php
					while ( $q->have_posts() ) {
						$q->the_post();
						$p = get_post();
						if ( $p instanceof WP_Post ) {
							echo self::render_item( $p, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
					wp_reset_postdata();
					?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
