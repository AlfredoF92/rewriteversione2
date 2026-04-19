<?php
/**
 * Shortcode: ultime attività utente (storie, frasi) + entrate coin dal ledger.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_User_Activity_Feed_Shortcode
 */
class LLM_User_Activity_Feed_Shortcode {

	const SHORTCODE = 'llm_user_activity_feed';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Timestamp GMT da un post attività.
	 *
	 * @param \WP_Post $post Post.
	 * @return string MySQL GMT.
	 */
	private static function post_ts_gmt( WP_Post $post ) {
		if ( ! empty( $post->post_date_gmt ) && '0000-00-00 00:00:00' !== $post->post_date_gmt ) {
			return (string) $post->post_date_gmt;
		}
		return get_gmt_from_date( $post->post_date );
	}

	/**
	 * @param int $user_id ID utente.
	 * @return array<string, true> Chiavi "phrase|story_id|index" per dedup ledger.
	 */
	private static function phrase_keys_from_activities( $user_id ) {
		$user_id = absint( $user_id );
		$keys    = array();
		$posts   = get_posts(
			array(
				'post_type'              => LLM_ACTIVITY_CPT,
				'post_status'            => 'publish',
				'author'                 => $user_id,
				'posts_per_page'         => 500,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'update_post_meta_cache' => true,
				'meta_query'             => array(
					array(
						'key'   => LLM_Community::META_TYPE,
						'value' => LLM_Community::TYPE_PHRASE,
					),
				),
			)
		);
		if ( ! is_array( $posts ) ) {
			return $keys;
		}
		foreach ( $posts as $pid ) {
			$sid = absint( get_post_meta( (int) $pid, LLM_Community::META_STORY, true ) );
			$pi  = get_post_meta( (int) $pid, LLM_Community::META_PHRASE, true );
			$pi  = ( $pi !== '' && false !== $pi ) ? (int) $pi : 0;
			$keys[ 'phrase|' . $sid . '|' . $pi ] = true;
		}
		return $keys;
	}

	/**
	 * @param array<string, mixed> $row Riga ledger.
	 * @return int Unix timestamp.
	 */
	private static function ledger_ts( array $row ) {
		$ts = isset( $row['ts'] ) ? (string) $row['ts'] : '';
		if ( $ts === '' ) {
			return 0;
		}
		$t = strtotime( $ts . ' GMT' );
		return $t ? $t : 0;
	}

	/**
	 * @param \WP_Post $post Post attività.
	 * @return int Unix timestamp.
	 */
	private static function post_ts( WP_Post $post ) {
		$gmt = self::post_ts_gmt( $post );
		$t   = strtotime( $gmt . ' GMT' );
		return $t ? $t : 0;
	}

	/**
	 * URL pubblico della storia, se esiste ed è pubblicata.
	 *
	 * @param int $story_id ID post storia.
	 * @return string URL o stringa vuota.
	 */
	private static function story_url( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return '';
		}
		if ( LLM_STORY_CPT !== get_post_type( $story_id ) ) {
			return '';
		}
		if ( 'publish' !== get_post_status( $story_id ) ) {
			return '';
		}
		$url = get_permalink( $story_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param string $text Testo del link.
	 * @param string $url  URL (vuoto = span).
	 * @return string HTML.
	 */
	private static function wrap_story_link( $text, $url ) {
		$text = (string) $text;
		$url  = (string) $url;
		if ( $url === '' ) {
			return '<span class="llm-activity-feed__text">' . esc_html( $text ) . '</span>';
		}
		return '<a class="llm-activity-feed__link" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'      => '30',
				'login_path' => '/login',
			),
			$atts,
			self::SHORTCODE
		);

		$limit = absint( $atts['limit'] );
		if ( $limit < 1 ) {
			$limit = 30;
		}
		if ( $limit > 100 ) {
			$limit = 100;
		}

		wp_enqueue_style(
			'llm-user-profile-fonts',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'llm-user-activity-feed',
			LLM_TABELLE_URL . 'assets/llm-user-activity-feed.css',
			array( 'llm-user-profile-fonts' ),
			LLM_TABELLE_VERSION
		);

		if ( ! is_user_logged_in() ) {
			$path = trim( (string) $atts['login_path'] );
			if ( $path === '' ) {
				$path = '/login';
			}
			if ( isset( $path[0] ) && $path[0] !== '/' ) {
				$path = '/' . $path;
			}
			$login_url = esc_url( home_url( $path ) );
			return '<div class="llm-activity-feed llm-activity-feed--guest"><p class="llm-activity-feed__guest">' .
				esc_html( __( 'Accedi per vedere le tue attività.', 'llm-con-tabelle' ) ) .
				'</p><p><a class="llm-activity-feed__guest-link" href="' . $login_url . '">' .
				esc_html( __( 'Vai al login', 'llm-con-tabelle' ) ) . '</a></p></div>';
		}

		$uid = get_current_user_id();
		if ( ! $uid ) {
			return '';
		}

		$fetch_n = min( 200, max( $limit * 4, $limit + 20 ) );

		$activity_posts = get_posts(
			array(
				'post_type'              => LLM_ACTIVITY_CPT,
				'post_status'            => 'publish',
				'author'                 => $uid,
				'posts_per_page'         => $fetch_n,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);

		$phrase_keys = self::phrase_keys_from_activities( $uid );

		$ledger = LLM_User_Stats::get_ledger( $uid );
		$items  = array();

		foreach ( $activity_posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$items[] = array(
				'ts'   => self::post_ts( $post ),
				'kind' => 'activity',
				'post' => $post,
			);
		}

		foreach ( $ledger as $row ) {
			$amount = isset( $row['amount'] ) ? (int) $row['amount'] : 0;
			if ( $amount <= 0 ) {
				continue;
			}
			$type = isset( $row['type'] ) ? (string) $row['type'] : '';
			if ( 'phrase' === $type ) {
				$sid = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
				$pi  = isset( $row['phrase_index'] ) && null !== $row['phrase_index'] ? (int) $row['phrase_index'] : -1;
				if ( $sid > 0 && $pi >= 0 && isset( $phrase_keys[ 'phrase|' . $sid . '|' . $pi ] ) ) {
					continue;
				}
			}
			$items[] = array(
				'ts'   => self::ledger_ts( $row ),
				'kind' => 'coin',
				'row'  => $row,
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return (int) $b['ts'] <=> (int) $a['ts'];
			}
		);

		$items = array_slice( $items, 0, $limit );

		if ( empty( $items ) ) {
			return '<div class="llm-activity-feed"><p class="llm-activity-feed__empty">' .
				esc_html( __( 'Nessuna attività recente.', 'llm-con-tabelle' ) ) . '</p></div>';
		}

		$df = get_option( 'date_format' );
		$tf = get_option( 'time_format' );

		ob_start();
		echo '<div class="llm-activity-feed"><ul class="llm-activity-feed__list">';
		foreach ( $items as $it ) {
			if ( 'activity' === $it['kind'] && isset( $it['post'] ) && $it['post'] instanceof WP_Post ) {
				$p     = $it['post'];
				$gmt    = self::post_ts_gmt( $p );
				$local  = get_date_from_gmt( $gmt, $df . ' ' . $tf );
				$ts_act = strtotime( $gmt . ' GMT' );
				$iso    = $ts_act ? gmdate( 'c', $ts_act ) : '';
				echo '<li class="llm-activity-feed__item llm-activity-feed__item--activity">';
				echo '<time class="llm-activity-feed__time" datetime="' . esc_attr( $iso ) . '">';
				echo esc_html( $local );
				echo '</time>';
				$sid = absint( get_post_meta( $p->ID, LLM_Community::META_STORY, true ) );
				echo self::wrap_story_link( get_the_title( $p ), self::story_url( $sid ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrap_story_link escapa.
				echo '</li>';
				continue;
			}
			if ( 'coin' === $it['kind'] && isset( $it['row'] ) ) {
				$row    = $it['row'];
				$amount = isset( $row['amount'] ) ? (int) $row['amount'] : 0;
				$label  = isset( $row['label'] ) ? (string) $row['label'] : '';
				if ( $label === '' ) {
					$label = isset( $row['type'] ) ? (string) $row['type'] : '';
				}
				$ts_raw = isset( $row['ts'] ) ? (string) $row['ts'] : '';
				$local  = $ts_raw !== '' ? get_date_from_gmt( $ts_raw, $df . ' ' . $tf ) : '';
				$ts_led = $ts_raw !== '' ? strtotime( $ts_raw . ' GMT' ) : 0;
				$iso    = $ts_led ? gmdate( 'c', $ts_led ) : '';
				/* translators: 1: positive coin amount, 2: description */
				$line = sprintf( __( '+%1$d coin — %2$s', 'llm-con-tabelle' ), $amount, $label );
				$sid  = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
				echo '<li class="llm-activity-feed__item llm-activity-feed__item--coin">';
				echo '<time class="llm-activity-feed__time" datetime="' . esc_attr( $iso ) . '">';
				echo esc_html( $local );
				echo '</time>';
				echo self::wrap_story_link( $line, self::story_url( $sid ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</li>';
			}
		}
		echo '</ul></div>';
		return (string) ob_get_clean();
	}
}
