<?php
/**
 * Shortcode [home-page-uscite] — ultime storie, calendario uscite, coppie lingue.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Home_Page_Uscite_Shortcode {

	const SHORTCODE = 'home-page-uscite';
	const LATEST_N  = 8;

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Preconnect Google Fonts (quando la home uscite è in pagina).
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

	/**
	 * @param array<string,string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		unset( $atts );

		add_action( 'wp_head', array( __CLASS__, 'print_font_preconnect' ), 2 );
		wp_enqueue_style(
			'llm-uscite-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Bebas+Neue&family=Elms+Sans:ital,wght@0,100..900;1,100..900&family=Lora:ital,wght@0,400..700;1,400..700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-home-page-uscite',
			LLM_TABELLE_URL . 'assets/llm-home-page-uscite.css',
			array( 'llm-ui', 'llm-uscite-fonts' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-guest-browser-store',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-store.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_enqueue_script(
			'llm-home-page-uscite',
			LLM_TABELLE_URL . 'assets/llm-home-page-uscite.js',
			array( 'llm-guest-browser-store' ),
			LLM_TABELLE_VERSION,
			true
		);

		$ui      = self::ui_lang();
		$from    = self::requested_from();
		$latest_all   = self::latest_stories( $from );
		$upcoming_all = self::upcoming_stories( $from );
		$pairs        = class_exists( 'LLM_Nav_Menu_Shortcode' )
			? LLM_Nav_Menu_Shortcode::directory_pairs()
			: array();
		if ( '' !== $from ) {
			$pairs = array_values(
				array_filter(
					$pairs,
					static function ( $pair ) use ( $from ) {
						return isset( $pair['known'] ) && $pair['known'] === $from;
					}
				)
			);
		}
		$pairs = self::append_soon_preview_pairs( $pairs, $from );
		$pairs = array_slice( array_values( $pairs ), 0, 10 );

		$sel_known  = isset( $pairs[0]['known'] ) ? (string) $pairs[0]['known'] : $from;
		$sel_target = isset( $pairs[0]['target'] ) ? (string) $pairs[0]['target'] : '';
		$sel_url    = isset( $pairs[0]['url'] ) ? (string) $pairs[0]['url'] : '';
		$latest     = array_slice( self::filter_stories_by_pair( $latest_all, $sel_known, $sel_target ), 0, self::LATEST_N );
		$upcoming   = self::filter_stories_by_pair( $upcoming_all, $sel_known, $sel_target );
		$events     = self::events_by_day( $upcoming );

		$today = current_time( 'Y-m-d' );
		$init  = explode( '-', $today );

		$hello_name = '';
		$is_guest   = ! is_user_logged_in();
		if ( ! $is_guest ) {
			$user = wp_get_current_user();
			$hello_name = ( $user && $user->exists() ) ? trim( (string) $user->display_name ) : '';
			if ( '' === $hello_name ) {
				$hello_name = trim( (string) $user->user_login );
			}
		}

		ob_start();
		self::print_font_preconnect();
		?>
		<div
			class="llm-uscite llm-ui-scope"
			data-llm-uscite
			data-events="<?php echo esc_attr( wp_json_encode( $events ) ); ?>"
			data-latest="<?php echo esc_attr( wp_json_encode( $latest_all ) ); ?>"
			data-upcoming="<?php echo esc_attr( wp_json_encode( $upcoming_all ) ); ?>"
			data-year="<?php echo esc_attr( $init[0] ); ?>"
			data-month="<?php echo esc_attr( (string) (int) $init[1] ); ?>"
			data-selected="<?php echo esc_attr( $today ); ?>"
			data-today="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
			data-i18n="<?php echo esc_attr( wp_json_encode( self::js_i18n( $from ) ) ); ?>"
			data-guest="<?php echo $is_guest ? '1' : '0'; ?>"
			data-hello-name="<?php echo esc_attr( $hello_name ); ?>"
			data-greetings="<?php echo esc_attr( wp_json_encode( self::greeting_cycle() ) ); ?>"
		>
			<header class="llm-uscite__intro">
				<div class="llm-uscite__intro-copy">
					<p class="llm-uscite__hello" data-llm-uscite-hello></p>
					<p class="llm-uscite__hello-sub" data-llm-uscite-hello-sub></p>
				</div>
			</header>

			<?php echo self::render_from_switcher( $from, $from ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>

			<section class="llm-uscite__section llm-uscite__section--pairs" aria-label="<?php echo esc_attr( self::t( $from, 'pairs' ) ); ?>">
				<div class="llm-uscite__pairs-wrap">
					<div class="llm-uscite__nav llm-uscite__nav--pairs" role="group" aria-label="<?php echo esc_attr( self::t( $from, 'carousel_nav' ) ); ?>">
						<button type="button" class="llm-uscite__arrow llm-uscite__arrow--pairs is-hidden" data-llm-uscite-pairs-prev aria-label="<?php echo esc_attr( self::t( $from, 'prev' ) ); ?>">
							<span aria-hidden="true">&#8249;</span>
						</button>
						<button type="button" class="llm-uscite__arrow llm-uscite__arrow--pairs" data-llm-uscite-pairs-next aria-label="<?php echo esc_attr( self::t( $from, 'next' ) ); ?>">
							<span aria-hidden="true">&#8250;</span>
						</button>
					</div>
					<div class="llm-uscite__pairs" data-llm-uscite-pairs-track tabindex="0">
						<?php
						foreach ( $pairs as $i => $pair ) {
							echo self::render_pair_card( $pair, $from, 0 === (int) $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</div>
				</div>
			</section>

			<section class="llm-uscite__section" aria-labelledby="llm-uscite-latest-title">
				<div class="llm-uscite__head">
					<div class="llm-uscite__head-main">
						<h2 id="llm-uscite-latest-title" class="llm-uscite__title"><?php echo esc_html( self::month_releases_title( $from ) ); ?></h2>
						<?php echo self::render_pair_chip( $sel_known, $sel_target, $sel_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato nel metodo. ?>
					</div>
					<div class="llm-uscite__nav" role="group" aria-label="<?php echo esc_attr( self::t( $from, 'carousel_nav' ) ); ?>">
						<button type="button" class="llm-uscite__arrow" data-llm-uscite-prev aria-label="<?php echo esc_attr( self::t( $from, 'prev' ) ); ?>">
							<span aria-hidden="true">&#8249;</span>
						</button>
						<button type="button" class="llm-uscite__arrow" data-llm-uscite-next aria-label="<?php echo esc_attr( self::t( $from, 'next' ) ); ?>">
							<span aria-hidden="true">&#8250;</span>
						</button>
					</div>
				</div>
				<div class="llm-uscite__carousel" data-llm-uscite-track tabindex="0">
					<?php
					if ( empty( $latest ) ) {
						echo '<p class="llm-uscite__empty">' . esc_html( self::t( $from, 'no_stories' ) ) . '</p>';
					} else {
						foreach ( $latest as $card ) {
							echo self::render_story_card( $card, $ui, 'carousel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
					?>
				</div>
			</section>

			<section class="llm-uscite__section" aria-labelledby="llm-uscite-cal-title">
				<div class="llm-uscite__head">
					<div class="llm-uscite__head-main">
						<h2 id="llm-uscite-cal-title" class="llm-uscite__title"><?php echo esc_html( self::t( $from, 'calendar' ) ); ?></h2>
						<?php echo self::render_pair_chip( $sel_known, $sel_target, $sel_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato nel metodo. ?>
					</div>
				</div>
				<div class="llm-uscite__cal-box">
					<div class="llm-uscite__cal-grid-wrap">
						<div class="llm-uscite__cal-toolbar">
							<button type="button" class="llm-uscite__cal-shift" data-llm-uscite-cal-prev></button>
							<p class="llm-uscite__cal-month" data-llm-uscite-cal-label></p>
							<button type="button" class="llm-uscite__cal-shift" data-llm-uscite-cal-next></button>
						</div>
						<div class="llm-uscite__cal" data-llm-uscite-cal></div>
					</div>
					<div class="llm-uscite__cal-detail">
						<p class="llm-uscite__cal-detail-title" data-llm-uscite-detail-title></p>
						<div class="llm-uscite__cal-detail-list" data-llm-uscite-detail-list></div>
					</div>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Filtro lingua di partenza dalla query string (?llm_from=it|en).
	 *
	 * @return string it|en|''
	 */
	private static function requested_from() {
		$from = isset( $_GET['llm_from'] ) ? sanitize_key( wp_unslash( $_GET['llm_from'] ) ) : '';
		return in_array( $from, array( 'it', 'en' ), true ) ? $from : 'it';
	}

	/**
	 * @param string $from Lingua nota (it|en|'').
	 * @return array<string,mixed>
	 */
	private static function known_lang_meta_query( $from ) {
		$from = sanitize_key( (string) $from );
		if ( '' === $from || ! class_exists( 'LLM_Story_Meta' ) ) {
			return array();
		}
		return array(
			'meta_query' => array(
				array(
					'key'   => LLM_Story_Meta::KNOWN_LANG,
					'value' => $from,
				),
			),
		);
	}

	/**
	 * Due riquadri in cima: italiano → / inglese →.
	 *
	 * @param string $ui   Lingua UI.
	 * @param string $from Filtro attivo.
	 * @return string
	 */
	private static function render_from_switcher( $ui, $from ) {
		$choices = array( 'it', 'en' );
		ob_start();
		?>
		<nav class="llm-uscite__from" aria-label="<?php echo esc_attr( self::t( $ui, 'from_nav' ) ); ?>">
			<?php foreach ( $choices as $code ) : ?>
				<?php
				$url    = add_query_arg( 'llm_from', $code );
				$active = ( $from === $code );
				$cls    = 'llm-uscite__from-card' . ( $active ? ' is-active' : '' );
				$flag   = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $code ) : '';
				$label  = self::t( $ui, 'from_' . $code );
				$know   = self::t( $ui, 'from_know_' . $code );
				?>
				<div class="llm-uscite__from-item">
					<p class="llm-uscite__from-label"><?php echo esc_html( $know ); ?></p>
					<a
						class="<?php echo esc_attr( $cls ); ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $active ? ' aria-current="page"' : ''; ?>
					>
						<span class="llm-uscite__from-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span>
						<span class="llm-uscite__from-name"><?php echo esc_html( $label ); ?></span>
						<span class="llm-uscite__from-arrow" aria-hidden="true">→</span>
					</a>
				</div>
			<?php endforeach; ?>
		</nav>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string $from Lingua nota (it|en|'').
	 * @return array<int,array<string,mixed>>
	 */
	private static function latest_stories( $from = '' ) {
		$after = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 30 * DAY_IN_SECONDS ) );
		$args = array_merge(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 80,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'date_query'             => array(
					array(
						'after'     => $after,
						'inclusive' => true,
					),
				),
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
			),
			self::known_lang_meta_query( $from )
		);
		$q = new WP_Query( $args );
		return self::pack_stories( $q->posts );
	}

	/**
	 * @param string $from Lingua nota (it|en|'').
	 * @return array<int,array<string,mixed>>
	 */
	private static function upcoming_stories( $from = '' ) {
		$args = array_merge(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => array( 'publish', 'future' ),
				'posts_per_page'         => 500,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
			),
			self::known_lang_meta_query( $from )
		);
		$q = new WP_Query( $args );
		return self::pack_stories( $q->posts );
	}

	/**
	 * @param WP_Post[] $posts Post.
	 * @return array<int,array<string,mixed>>
	 */
	private static function pack_stories( array $posts ) {
		$ids = array();
		foreach ( $posts as $post ) {
			$ids[] = (int) $post->ID;
		}
		$counts = self::phrase_counts( $ids );
		$out    = array();
		foreach ( $posts as $post ) {
			$id         = (int) $post->ID;
			$title_it   = get_the_title( $id );
			$title_en   = class_exists( 'LLM_Story_Meta' )
				? trim( (string) get_post_meta( $id, LLM_Story_Meta::TITLE_TARGET, true ) )
				: '';
			$title      = $title_en !== '' ? $title_en : $title_it;
			$subtitle   = ( $title_en !== '' && $title_it !== '' && strcasecmp( $title_en, $title_it ) !== 0 )
				? $title_it
				: '';
			$known      = class_exists( 'LLM_Story_Meta' ) ? sanitize_key( (string) get_post_meta( $id, LLM_Story_Meta::KNOWN_LANG, true ) ) : '';
			$target     = class_exists( 'LLM_Story_Meta' ) ? sanitize_key( (string) get_post_meta( $id, LLM_Story_Meta::TARGET_LANG, true ) ) : '';
			$cefr_raw   = class_exists( 'LLM_Story_Meta' ) ? (string) get_post_meta( $id, LLM_Story_Meta::STORY_CEFR_LEVEL, true ) : '';
			$cefr       = '';
			if ( preg_match( '/\b([ABC][12])\b/i', $cefr_raw, $m ) ) {
				$cefr = strtoupper( $m[1] );
			}
			$thumb = get_the_post_thumbnail_url( $id, 'medium_large' );
			$n     = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
			$day   = substr( (string) $post->post_date, 0, 10 );
			$time  = substr( (string) $post->post_date, 11, 5 );
			$out[] = array(
				'id'        => $id,
				'title'     => $title,
				'subtitle'  => $subtitle,
				'url'       => (string) get_permalink( $id ),
				'cover'     => $thumb ? (string) $thumb : '',
				'category'  => self::story_category( $id ),
				'cefr'      => $cefr,
				'phrases'   => $n,
				'day'       => $day,
				'dateLabel' => self::date_meta_label( (string) $post->post_date ),
				'timeLabel' => $time,
				'pair'      => self::pair_line( $known, $target ),
				'known'     => $known,
				'target'    => $target,
			);
		}
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $stories Storie.
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private static function events_by_day( array $stories ) {
		$by = array();
		foreach ( $stories as $s ) {
			$day = isset( $s['day'] ) ? (string) $s['day'] : '';
			if ( '' === $day ) {
				continue;
			}
			if ( ! isset( $by[ $day ] ) ) {
				$by[ $day ] = array();
			}
			$by[ $day ][] = $s;
		}
		return $by;
	}

	/**
	 * @param array<int,array<string,mixed>> $stories Storie.
	 * @param string                         $known   Lingua nota.
	 * @param string                         $target  Lingua obiettivo.
	 * @return array<int,array<string,mixed>>
	 */
	private static function filter_stories_by_pair( array $stories, $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( '' === $known || '' === $target ) {
			return array();
		}
		$out = array();
		foreach ( $stories as $s ) {
			if ( ( isset( $s['known'] ) ? (string) $s['known'] : '' ) === $known
				&& ( isset( $s['target'] ) ? (string) $s['target'] : '' ) === $target ) {
				$out[] = $s;
			}
		}
		return $out;
	}

	/**
	 * @param int[] $story_ids ID.
	 * @return array<int,int>
	 */
	private static function phrase_counts( array $story_ids ) {
		global $wpdb;
		$story_ids = array_values( array_unique( array_filter( array_map( 'absint', $story_ids ) ) ) );
		if ( empty( $story_ids ) || ! class_exists( 'LLM_Tabelle_Database' ) ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		$in    = implode( ',', $story_ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- solo ID interi.
		$rows = $wpdb->get_results( "SELECT story_id, COUNT(*) AS n FROM {$table} WHERE story_id IN ({$in}) GROUP BY story_id", ARRAY_A );
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (int) $row['story_id'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * @param int $id ID storia.
	 * @return string
	 */
	private static function story_category( $id ) {
		$terms = get_the_terms( $id, 'category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		foreach ( $terms as $term ) {
			if ( preg_match( '/^[a-z]{2,3}-[a-z]{2,10}$/', (string) $term->slug ) ) {
				continue;
			}
			if ( class_exists( 'LLM_Category_Translations' ) ) {
				return LLM_Category_Translations::get_translated_name( $term, self::ui_lang() );
			}
			return $term->name;
		}
		return $terms[0]->name;
	}

	/**
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @return string
	 */
	private static function pair_line( $known, $target ) {
		if ( '' === $known || '' === $target || ! class_exists( 'LLM_Languages' ) ) {
			return '';
		}
		return LLM_Languages::flag_emoji( $known ) . ' → ' . LLM_Languages::flag_emoji( $target ) . '  ' . LLM_Languages::label( $target );
	}

	/**
	 * @param string $post_date Datetime locale WP.
	 * @return string
	 */
	private static function date_meta_label( $post_date ) {
		$post_date = (string) $post_date;
		if ( strlen( $post_date ) < 16 ) {
			return '';
		}
		$ui    = self::ui_lang();
		$month = self::month_name( (int) substr( $post_date, 5, 2 ), $ui );
		$day   = (int) substr( $post_date, 8, 2 );
		$time  = substr( $post_date, 11, 5 );
		$ore   = self::t( $ui, 'at_time' );
		return wp_strip_all_tags( sprintf( '%s %s · %s %s', $month, $day, $ore, $time ) );
	}

	/**
	 * @param array<string,mixed> $card Dati.
	 * @param string              $ui   Lingua UI.
	 * @param string              $kind carousel|row.
	 * @return string
	 */
	private static function render_story_card( array $card, $ui, $kind = 'carousel' ) {
		$url      = isset( $card['url'] ) ? (string) $card['url'] : '';
		$title    = isset( $card['title'] ) ? (string) $card['title'] : '';
		$subtitle = isset( $card['subtitle'] ) ? (string) $card['subtitle'] : '';
		$cover    = isset( $card['cover'] ) ? (string) $card['cover'] : '';
		$cat      = isset( $card['category'] ) ? (string) $card['category'] : '';
		$cefr     = isset( $card['cefr'] ) ? (string) $card['cefr'] : '';
		$phrases  = isset( $card['phrases'] ) ? (int) $card['phrases'] : 0;
		$date_l   = isset( $card['dateLabel'] ) ? (string) $card['dateLabel'] : '';
		$pair     = isset( $card['pair'] ) ? (string) $card['pair'] : '';
		$cls      = 'llm-uscite__card llm-uscite__card--' . $kind;
		$tag      = $url ? 'a' : 'article';
		$unit     = 1 === $phrases ? self::t( $ui, 'phrase' ) : self::t( $ui, 'phrases' );

		ob_start();
		?>
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			class="<?php echo esc_attr( $cls ); ?>"
			<?php if ( $url ) : ?>
				href="<?php echo esc_url( $url ); ?>"
			<?php endif; ?>
		>
			<div
				class="llm-uscite__cover<?php echo $cover ? '' : ' llm-uscite__cover--empty'; ?>"
				<?php if ( $cover ) : ?>
					style="background-image:url('<?php echo esc_url( $cover ); ?>');"
				<?php endif; ?>
				aria-hidden="true"
			>
				<?php if ( $cat ) : ?>
					<span class="llm-uscite__badge"><?php echo esc_html( $cat ); ?></span>
				<?php endif; ?>
				<?php if ( $phrases > 0 ) : ?>
					<span class="llm-uscite__duration">🕒 <?php echo esc_html( $phrases . ' ' . $unit ); ?></span>
				<?php endif; ?>
			</div>
			<div class="llm-uscite__body">
				<?php if ( $date_l ) : ?>
					<p class="llm-uscite__meta"><?php echo esc_html( $date_l ); ?></p>
				<?php endif; ?>
				<h3 class="llm-uscite__card-title"><?php echo esc_html( $title ); ?></h3>
				<?php if ( $subtitle ) : ?>
					<p class="llm-uscite__card-sub"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
				<?php if ( $pair ) : ?>
					<p class="llm-uscite__pair"><?php echo esc_html( $pair ); ?></p>
				<?php endif; ?>
				<div class="llm-uscite__foot">
					<?php if ( $cefr ) : ?>
						<span class="llm-uscite__cefr"><?php echo esc_html( $cefr ); ?></span>
					<?php endif; ?>
					<?php if ( $url ) : ?>
						<span class="llm-uscite__more"><?php echo esc_html( self::t( $ui, 'more' ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Nome lingua con articolo, nella lingua UI.
	 *
	 * @param string $ui   Lingua UI.
	 * @param string $code Codice lingua.
	 * @return string
	 */
	private static function lang_in_phrase( $ui, $code ) {
		$names = array(
			'it' => array(
				'it' => 'l\'italiano',
				'en' => 'l\'inglese',
				'pl' => 'il polacco',
				'es' => 'lo spagnolo',
				'de' => 'il tedesco',
				'fr' => 'il francese',
				'pt' => 'il portoghese',
				'nl' => 'l\'olandese',
				'ja' => 'il giapponese',
				'ru' => 'il russo',
				'zh' => 'il cinese',
				'ko' => 'il coreano',
				'ar' => 'l\'arabo',
			),
			'en' => array(
				'it' => 'Italian',
				'en' => 'English',
				'pl' => 'Polish',
				'es' => 'Spanish',
				'de' => 'German',
				'fr' => 'French',
				'pt' => 'Portuguese',
				'nl' => 'Dutch',
				'ja' => 'Japanese',
				'ru' => 'Russian',
				'zh' => 'Chinese',
				'ko' => 'Korean',
				'ar' => 'Arabic',
			),
			'pl' => array(
				'it' => 'włoskiego',
				'en' => 'angielskiego',
				'pl' => 'polskiego',
				'es' => 'hiszpańskiego',
				'de' => 'niemieckiego',
				'fr' => 'francuskiego',
				'pt' => 'portugalskiego',
				'nl' => 'niderlandzkiego',
				'ja' => 'japońskiego',
			),
			'es' => array(
				'it' => 'italiano',
				'en' => 'inglés',
				'pl' => 'polaco',
				'es' => 'español',
				'de' => 'alemán',
				'fr' => 'francés',
				'pt' => 'portugués',
				'nl' => 'neerlandés',
				'ja' => 'japonés',
			),
			'de' => array(
				'it' => 'Italienisch',
				'en' => 'Englisch',
				'pl' => 'Polnisch',
				'es' => 'Spanisch',
				'de' => 'Deutsch',
				'fr' => 'Französisch',
				'pt' => 'Portugiesisch',
				'nl' => 'Niederländisch',
				'ja' => 'Japanisch',
			),
			'fr' => array(
				'it' => 'l\'italien',
				'en' => 'l\'anglais',
				'pl' => 'le polonais',
				'es' => 'l\'espagnol',
				'de' => 'l\'allemand',
				'fr' => 'le français',
				'pt' => 'le portugais',
				'nl' => 'le néerlandais',
				'ja' => 'le japonais',
			),
		);
		if ( ! isset( $names[ $ui ] ) ) {
			$ui = 'it';
		}
		return isset( $names[ $ui ][ $code ] ) ? $names[ $ui ][ $code ] : $code;
	}

	/**
	 * Descrizione card: storie dedicate in base a lingua nota e obiettivo.
	 *
	 * @param string $ui     Lingua UI.
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @return string
	 */
	private static function pair_card_desc( $ui, $known, $target ) {
		$from = array(
			'it' => array(
				'it' => 'dall\'italiano',
				'en' => 'dall\'inglese',
				'pl' => 'dal polacco',
				'es' => 'dallo spagnolo',
			),
			'en' => array(
				'it' => 'Italian',
				'en' => 'English',
				'pl' => 'Polish',
				'es' => 'Spanish',
			),
		);
		$tpl = array(
			'it' => 'Scopri le storie per imparare %1$s partendo %2$s',
			'en' => 'Discover the stories to learn %1$s starting from %2$s',
			'pl' => 'Odkryj historie, żeby uczyć się %1$s zaczynając od %2$s',
			'es' => 'Descubre las historias para aprender %1$s partiendo de %2$s',
			'de' => 'Entdecke die Geschichten, um %1$s zu lernen, ausgehend von %2$s',
			'fr' => 'Découvre les histoires pour apprendre %1$s en partant de %2$s',
		);
		if ( ! isset( $tpl[ $ui ] ) ) {
			$ui = 'it';
		}
		$from_map = isset( $from[ $ui ] ) ? $from[ $ui ] : $from['it'];
		$from_txt = isset( $from_map[ $known ] ) ? $from_map[ $known ] : self::lang_in_phrase( $ui, $known );
		return sprintf(
			$tpl[ $ui ],
			self::lang_in_phrase( $ui, $target ),
			$from_txt
		);
	}

	/**
	 * Lingue di prova in homepage: visibili, non cliccabili.
	 *
	 * @param array<int,array{known:string,target:string,url:string}> $pairs Coppie già presenti.
	 * @param string                                                 $from  Lingua nota.
	 * @return array<int,array{known:string,target:string,url:string}>
	 */
	private static function append_soon_preview_pairs( array $pairs, $from ) {
		$known = $from ? $from : 'it';
		$seen  = array();
		foreach ( $pairs as $pair ) {
			if ( isset( $pair['known'], $pair['target'] ) ) {
				$seen[ $pair['known'] . '_' . $pair['target'] ] = true;
			}
		}
		foreach ( array( 'it', 'en', 'pl', 'es', 'fr', 'de', 'pt', 'nl', 'ja', 'ru', 'zh', 'ko', 'ar' ) as $target ) {
			if ( count( $pairs ) >= 10 ) {
				break;
			}
			if ( $target === $known ) {
				continue;
			}
			$key = $known . '_' . $target;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$pairs[] = array(
				'known'  => $known,
				'target' => $target,
				'url'    => '',
			);
			$seen[ $key ] = true;
		}
		return $pairs;
	}

	/**
	 * Titolo card: «Impara lo spagnolo».
	 *
	 * @param string $ui     Lingua UI.
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @return string
	 */
	private static function pair_card_title( $ui, $known, $target ) {
		unset( $known );
		$map = array(
			'it' => array(
				'en' => 'Storie per imparare l\'inglese',
				'pl' => 'Storie per imparare il polacco',
				'es' => 'Storie per imparare lo spagnolo',
				'fr' => 'Storie per imparare il francese',
				'de' => 'Storie per imparare il tedesco',
				'pt' => 'Storie per imparare il portoghese',
				'nl' => 'Storie per imparare l\'olandese',
				'ja' => 'Storie per imparare il giapponese',
				'ru' => 'Storie per imparare il russo',
				'zh' => 'Storie per imparare il cinese',
				'ko' => 'Storie per imparare il coreano',
				'ar' => 'Storie per imparare l\'arabo',
				'it' => 'Storie per imparare l\'italiano',
			),
			'en' => array(
				'it' => 'Stories to learn Italian',
				'pl' => 'Stories to learn Polish',
				'es' => 'Stories to learn Spanish',
				'fr' => 'Stories to learn French',
				'de' => 'Stories to learn German',
				'pt' => 'Stories to learn Portuguese',
				'nl' => 'Stories to learn Dutch',
				'ja' => 'Stories to learn Japanese',
				'ru' => 'Stories to learn Russian',
				'zh' => 'Stories to learn Chinese',
				'ko' => 'Stories to learn Korean',
				'ar' => 'Stories to learn Arabic',
				'en' => 'Stories to learn English',
			),
		);
		if ( ! isset( $map[ $ui ] ) ) {
			$ui = 'it';
		}
		return isset( $map[ $ui ][ $target ] ) ? $map[ $ui ][ $target ] : '';
	}

	/**
	 * @param array{known:string,target:string,url:string} $pair      Coppia.
	 * @param string                                       $ui        Lingua UI.
	 * @param bool                                         $is_active Selezionata.
	 * @return string
	 */
	private static function render_pair_card( array $pair, $ui, $is_active = false ) {
		$known  = isset( $pair['known'] ) ? $pair['known'] : $ui;
		$target = $pair['target'];
		$url    = $pair['url'];
		$title  = self::pair_card_title( $ui, $known, $target );
		$flag_t = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $target ) : '';
		$cover  = self::pair_cover_url( $target );
		$is_soon = ( '' === $url );
		$cls     = 'llm-uscite__pair-card' . ( $is_soon ? ' llm-uscite__pair-card--soon' : '' );
		if ( $cover ) {
			$cls .= ' llm-uscite__pair-card--has-cover';
		}
		if ( $is_active && ! $is_soon ) {
			$cls .= ' is-active';
		}

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( $cls ); ?>"
			<?php if ( $is_soon ) : ?>
				aria-disabled="true"
			<?php else : ?>
				role="button"
				tabindex="0"
				aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
			<?php endif; ?>
			data-llm-uscite-pair
			data-known="<?php echo esc_attr( $known ); ?>"
			data-target="<?php echo esc_attr( $target ); ?>"
			data-url="<?php echo esc_attr( $url ); ?>"
			<?php if ( $cover ) : ?>
				style="background-image: url('<?php echo esc_url( $cover ); ?>')"
			<?php endif; ?>
		>
			<span class="llm-uscite__pair-flag llm-uscite__pair-flag--to" aria-hidden="true"><?php echo esc_html( $flag_t ); ?></span>
			<div class="llm-uscite__pair-copy">
				<h3 class="llm-uscite__pair-title"><?php echo esc_html( $title ); ?></h3>
				<?php if ( $url ) : ?>
					<a class="llm-uscite__pair-cta" href="<?php echo esc_url( $url ); ?>" data-llm-uscite-pair-link><?php echo esc_html( self::t( $ui, 'all_stories' ) ); ?></a>
				<?php else : ?>
					<span class="llm-uscite__soon"><?php echo esc_html( self::t( $ui, 'soon' ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Badge-link accanto ai titoli: idle «Storie IT - PL», hover «Vai a tutte le storie →».
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @param string $url    Catalogo coppia, o vuoto se coming soon.
	 * @return string
	 */
	private static function render_pair_chip( $known, $target, $url ) {
		$k     = strtoupper( sanitize_key( (string) $known ) );
		$t     = strtoupper( sanitize_key( (string) $target ) );
		$idle  = 'Storie ' . $k . ' - ' . $t;
		$hover = 'Vai a tutte le storie →';
		$url   = (string) $url;
		$cls   = 'llm-uscite__pair-chip' . ( '' === $url ? ' is-disabled' : '' );

		ob_start();
		?>
		<a
			class="<?php echo esc_attr( $cls ); ?>"
			data-llm-uscite-pair-chip
			<?php if ( '' !== $url ) : ?>
				href="<?php echo esc_url( $url ); ?>"
			<?php else : ?>
				aria-disabled="true"
			<?php endif; ?>
		>
			<span class="llm-uscite__pair-chip-idle"><?php echo esc_html( $idle ); ?></span>
			<span class="llm-uscite__pair-chip-hover"><?php echo esc_html( $hover ); ?></span>
		</a>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Copertina locandina per lingua obiettivo, se il file esiste.
	 *
	 * @param string $target Codice lingua.
	 * @return string
	 */
	private static function pair_cover_url( $target ) {
		$target = sanitize_key( (string) $target );
		if ( '' === $target ) {
			return '';
		}
		$rel = 'assets/pair-covers/pair-cover-' . $target . '.jpg';
		if ( ! is_readable( LLM_TABELLE_DIR . $rel ) ) {
			return '';
		}
		return LLM_TABELLE_URL . $rel . '?ver=' . rawurlencode( LLM_TABELLE_VERSION );
	}

	/**
	 * @return string
	 */
	private static function ui_lang() {
		if ( class_exists( 'LLM_Visitor_Lang' ) ) {
			$code = LLM_Visitor_Lang::known();
			if ( $code ) {
				return $code;
			}
		}
		return 'it';
	}

	/**
	 * @param string $lang Lingua.
	 * @param string $key  Chiave.
	 * @return string
	 */
	private static function t( $lang, $key ) {
		$all = array(
			'it' => array(
				'hello'        => 'Ciao',
				'hello_name'   => 'Ciao, %s',
				'hello_sub'    => 'Piacere di vederti',
				'from_nav'     => 'Parti dalla lingua che conosci',
				'from_it'      => 'Italiano',
				'from_en'      => 'Inglese',
				'from_know_it' => 'Conosco l\'Italiano',
				'from_know_en' => 'I know English',
				'all_stories'  => 'Vai a tutte le storie →',
				'latest'       => 'Ultime aggiunte',
				'latest_month' => 'Storie uscite negli ultimi 30 giorni',
				'calendar'     => 'Calendario prossime uscite',
				'pairs'        => 'Vai alle storie dedicate per…',
				'more'         => 'Scopri di più →',
				'prev'         => 'Precedente',
				'next'         => 'Successivo',
				'carousel_nav' => 'Scorri le storie',
				'no_stories'   => 'Nessuna storia al momento.',
				'no_pairs'     => 'Nessuna coppia disponibile.',
				'no_day'       => 'Nessuna uscita in questo giorno.',
				'events_on'    => 'Uscite %s',
				'at_time'      => 'ore',
				'phrase'       => 'frase',
				'phrases'      => 'frasi',
				'soon'         => 'Coming soon',
			),
			'en' => array(
				'hello'        => 'Hello',
				'hello_name'   => 'Hello, %s',
				'hello_sub'    => 'Nice to see you',
				'from_nav'     => 'Start from the language you know',
				'from_it'      => 'Italian',
				'from_en'      => 'English',
				'from_know_it' => 'Conosco l\'Italiano',
				'from_know_en' => 'I know English',
				'all_stories'  => 'Go to all stories →',
				'latest'       => 'Latest additions',
				'latest_month' => 'Stories released in the last 30 days',
				'calendar'     => 'Upcoming releases calendar',
				'pairs'        => 'Go to the stories dedicated to…',
				'more'         => 'Find out more →',
				'prev'         => 'Previous',
				'next'         => 'Next',
				'carousel_nav' => 'Browse stories',
				'no_stories'   => 'No stories yet.',
				'no_pairs'     => 'No language pairs available.',
				'no_day'       => 'No releases on this day.',
				'events_on'    => 'Releases %s',
				'at_time'      => 'at',
				'phrase'       => 'phrase',
				'phrases'      => 'phrases',
				'soon'         => 'Coming soon',
			),
			'pl' => array(
				'hello'        => 'Cześć',
				'hello_name'   => 'Cześć, %s',
				'hello_sub'    => 'Miło cię widzieć',
				'from_nav'     => 'Zacznij od języka, który znasz',
				'from_it'      => 'Włoski',
				'from_en'      => 'Angielski',
				'from_know_it' => 'Conosco l\'Italiano',
				'from_know_en' => 'I know English',
				'all_stories'  => 'Przejdź do wszystkich historii →',
				'latest'       => 'Ostatnio dodane',
				'latest_month' => 'Historie z ostatnich 30 dni',
				'calendar'     => 'Kalendarz najbliższych publikacji',
				'pairs'        => 'Przejdź do historii poświęconych…',
				'more'         => 'Zobacz więcej →',
				'prev'         => 'Poprzednie',
				'next'         => 'Następne',
				'carousel_nav' => 'Przeglądaj historie',
				'no_stories'   => 'Brak historii.',
				'no_pairs'     => 'Brak par językowych.',
				'no_day'       => 'Brak publikacji w tym dniu.',
				'events_on'    => 'Publikacje %s',
				'at_time'      => 'godz.',
				'phrase'       => 'zdanie',
				'phrases'      => 'zdania',
				'soon'         => 'Wkrótce',
			),
			'es' => array(
				'hello'        => 'Hola',
				'hello_name'   => 'Hola, %s',
				'hello_sub'    => 'Encantado de verte',
				'from_nav'     => 'Empieza por el idioma que conoces',
				'from_it'      => 'Italiano',
				'from_en'      => 'Inglés',
				'from_know_it' => 'Conosco l\'Italiano',
				'from_know_en' => 'I know English',
				'all_stories'  => 'Ir a todas las historias →',
				'latest'       => 'Últimas añadiduras',
				'latest_month' => 'Historias publicadas en los últimos 30 días',
				'calendar'     => 'Calendario de próximas salidas',
				'pairs'        => 'Ve a las historias dedicadas a…',
				'more'         => 'Descubre más →',
				'prev'         => 'Anterior',
				'next'         => 'Siguiente',
				'carousel_nav' => 'Desplazarse por las historias',
				'no_stories'   => 'Todavía no hay historias.',
				'no_pairs'     => 'No hay parejas disponibles.',
				'no_day'       => 'Ninguna salida este día.',
				'events_on'    => 'Salidas %s',
				'at_time'      => 'a las',
				'phrase'       => 'frase',
				'phrases'      => 'frases',
				'soon'         => 'Próximamente',
			),
			'de' => array(
				'hello'        => 'Hallo',
				'hello_name'   => 'Hallo, %s',
				'hello_sub'    => 'Schön, dich zu sehen',
				'from_nav'     => 'Starte mit der Sprache, die du kennst',
				'from_it'      => 'Italienisch',
				'from_en'      => 'Englisch',
				'from_know_it' => 'Conosco l\'Italiano',
				'from_know_en' => 'I know English',
				'all_stories'  => 'Zu allen Geschichten →',
				'latest'       => 'Neueste Ergänzungen',
				'latest_month' => 'Geschichten der letzten 30 Tage',
				'calendar'     => 'Kalender der nächsten Veröffentlichungen',
				'pairs'        => 'Zu den Geschichten, um…',
				'more'         => 'Mehr erfahren →',
				'prev'         => 'Zurück',
				'next'         => 'Weiter',
				'carousel_nav' => 'Geschichten durchblättern',
				'no_stories'   => 'Noch keine Geschichten.',
				'no_pairs'     => 'Keine Sprachpaare verfügbar.',
				'no_day'       => 'Keine Veröffentlichungen an diesem Tag.',
				'events_on'    => 'Veröffentlichungen %s',
				'at_time'      => 'um',
				'phrase'       => 'Satz',
				'phrases'      => 'Sätze',
				'soon'         => 'Demnächst',
			),
			'fr' => array(
				'hello'        => 'Bonjour',
				'hello_name'   => 'Bonjour, %s',
				'hello_sub'    => 'Ravi de te voir',
				'from_nav'     => 'Pars de la langue que tu connais',
				'from_it'      => 'Italien',
				'from_en'      => 'Anglais',
				'from_know_it' => 'Conosco l\'Italiano',
				'from_know_en' => 'I know English',
				'all_stories'  => 'Voir toutes les histoires →',
				'latest'       => 'Derniers ajouts',
				'latest_month' => 'Histoires sorties ces 30 derniers jours',
				'calendar'     => 'Calendrier des prochaines sorties',
				'pairs'        => 'Va aux histoires dédiées pour…',
				'more'         => 'En savoir plus →',
				'prev'         => 'Précédent',
				'next'         => 'Suivant',
				'carousel_nav' => 'Parcourir les histoires',
				'no_stories'   => 'Pas encore d’histoires.',
				'no_pairs'     => 'Aucune paire disponible.',
				'no_day'       => 'Aucune sortie ce jour-là.',
				'events_on'    => 'Sorties %s',
				'at_time'      => 'à',
				'phrase'       => 'phrase',
				'phrases'      => 'phrases',
				'soon'         => 'Bientôt',
			),
			'pt' => array(
				'hello'      => 'Olá',
				'hello_name' => 'Olá, %s',
				'hello_sub'  => 'Prazer em te ver',
			),
			'nl' => array(
				'hello'      => 'Hoi',
				'hello_name' => 'Hoi, %s',
				'hello_sub'  => 'Leuk je te zien',
			),
			'ja' => array(
				'hello'      => 'こんにちは',
				'hello_name' => 'こんにちは、%s',
				'hello_sub'  => '会えてうれしいです',
			),
			'ru' => array(
				'hello'      => 'Привет',
				'hello_name' => 'Привет, %s',
				'hello_sub'  => 'Рад тебя видеть',
			),
			'zh' => array(
				'hello'      => '你好',
				'hello_name' => '你好，%s',
				'hello_sub'  => '很高兴见到你',
			),
			'ko' => array(
				'hello'      => '안녕',
				'hello_name' => '안녕, %s',
				'hello_sub'  => '만나서 반가워',
			),
		);
		if ( ! isset( $all[ $lang ] ) ) {
			$lang = 'it';
		}
		return isset( $all[ $lang ][ $key ] ) ? $all[ $lang ][ $key ] : $all['it'][ $key ];
	}

	/**
	 * Ciclo saluto + titoli sezione: it, es, pl, en, de, fr.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function greeting_cycle() {
		$flags = array(
			'it' => '🇮🇹',
			'en' => '🇬🇧',
			'pl' => '🇵🇱',
			'es' => '🇪🇸',
			'fr' => '🇫🇷',
			'de' => '🇩🇪',
			'pt' => '🇵🇹',
			'nl' => '🇳🇱',
			'ja' => '🇯🇵',
			'ru' => '🇷🇺',
			'zh' => '🇨🇳',
			'ko' => '🇰🇷',
		);
		$dows = array(
			'it' => array( 'LUN', 'MAR', 'MER', 'GIO', 'VEN', 'SAB', 'DOM' ),
			'en' => array( 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN' ),
			'pl' => array( 'PON', 'WT', 'ŚR', 'CZW', 'PT', 'SOB', 'ND' ),
			'es' => array( 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM' ),
			'de' => array( 'MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO' ),
			'fr' => array( 'LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM' ),
		);
		$out = array();
		foreach ( $flags as $lang => $flag ) {
			$months = array();
			for ( $i = 1; $i <= 12; $i++ ) {
				$months[] = self::month_name( $i, $lang );
			}
			$out[] = array(
				'lang'        => $lang,
				'flag'        => $flag,
				'hello'       => self::t( $lang, 'hello' ),
				'helloName'   => self::t( $lang, 'hello_name' ),
				'sub'         => self::t( $lang, 'hello_sub' ),
				'latest'      => self::month_releases_title( $lang ),
				'calendar'    => self::t( $lang, 'calendar' ),
				'pairs'       => self::t( $lang, 'pairs' ),
				'months'      => $months,
				'dows'        => isset( $dows[ $lang ] ) ? $dows[ $lang ] : $dows['it'],
				'eventsOn'    => self::t( $lang, 'events_on' ),
				'noDay'       => self::t( $lang, 'no_day' ),
				'more'        => self::t( $lang, 'more' ),
				'prev'        => self::t( $lang, 'prev' ),
				'next'        => self::t( $lang, 'next' ),
				'carouselNav' => self::t( $lang, 'carousel_nav' ),
			);
		}
		return $out;
	}

	/**
	 * @param string $lang Lingua UI.
	 * @return array<string,mixed>
	 */
	private static function js_i18n( $lang ) {
		$months = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$months[] = self::month_name( $i, $lang );
		}
		$dows = array(
			'it' => array( 'LUN', 'MAR', 'MER', 'GIO', 'VEN', 'SAB', 'DOM' ),
			'en' => array( 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN' ),
			'pl' => array( 'PON', 'WT', 'ŚR', 'CZW', 'PT', 'SOB', 'ND' ),
			'es' => array( 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM' ),
			'de' => array( 'MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO' ),
			'fr' => array( 'LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM' ),
		);
		return array(
			'months'    => $months,
			'dows'      => isset( $dows[ $lang ] ) ? $dows[ $lang ] : $dows['it'],
			'eventsOn'  => self::t( $lang, 'events_on' ),
			'noDay'     => self::t( $lang, 'no_day' ),
			'more'      => self::t( $lang, 'more' ),
			'noStories' => self::t( $lang, 'no_stories' ),
			'phrase'    => self::t( $lang, 'phrase' ),
			'phrases'   => self::t( $lang, 'phrases' ),
			'prevMonth' => self::t( $lang, 'prev' ),
			'nextMonth' => self::t( $lang, 'next' ),
			'helloName' => self::t( $lang, 'hello_name' ),
			'hello'     => self::t( $lang, 'hello' ),
		);
	}

	/**
	 * Titolo carosello: uscite degli ultimi 30 giorni.
	 *
	 * @param string $lang Lingua UI.
	 * @return string
	 */
	private static function month_releases_title( $lang ) {
		return self::t( $lang, 'latest_month' );
	}

	/**
	 * @param int    $n    1–12.
	 * @param string $lang Lingua.
	 * @return string
	 */
	private static function month_name( $n, $lang ) {
		$map = array(
			'it' => array( '', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre' ),
			'en' => array( '', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
			'pl' => array( '', 'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień' ),
			'es' => array( '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre' ),
			'de' => array( '', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ),
			'fr' => array( '', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' ),
		);
		if ( ! isset( $map[ $lang ] ) ) {
			$lang = 'it';
		}
		return isset( $map[ $lang ][ $n ] ) ? $map[ $lang ][ $n ] : '';
	}
}
