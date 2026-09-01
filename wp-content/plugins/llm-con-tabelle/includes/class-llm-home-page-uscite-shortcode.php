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
	 * @param array<string,string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		unset( $atts );

		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-home-page-uscite',
			LLM_TABELLE_URL . 'assets/llm-home-page-uscite.css',
			array( 'llm-ui' ),
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
		$latest  = self::latest_stories();
		$upcoming = self::upcoming_stories();
		$events  = self::events_by_day( $upcoming );
		$pairs   = class_exists( 'LLM_Nav_Menu_Shortcode' )
			? LLM_Nav_Menu_Shortcode::directory_pairs()
			: array();

		$first_key = '';
		if ( ! empty( $events ) ) {
			$keys      = array_keys( $events );
			sort( $keys );
			$first_key = $keys[0];
		}
		$init = $first_key ? explode( '-', $first_key ) : explode( '-', current_time( 'Y-m-d' ) );

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
		?>
		<div
			class="llm-uscite llm-ui-scope"
			data-llm-uscite
			data-events="<?php echo esc_attr( wp_json_encode( $events ) ); ?>"
			data-year="<?php echo esc_attr( $init[0] ); ?>"
			data-month="<?php echo esc_attr( (string) (int) $init[1] ); ?>"
			data-selected="<?php echo esc_attr( $first_key ); ?>"
			data-i18n="<?php echo esc_attr( wp_json_encode( self::js_i18n( $ui ) ) ); ?>"
			data-guest="<?php echo $is_guest ? '1' : '0'; ?>"
			data-hello-name="<?php echo esc_attr( $hello_name ); ?>"
			data-greetings="<?php echo esc_attr( wp_json_encode( self::greeting_cycle() ) ); ?>"
		>
			<header class="llm-uscite__intro">
				<div class="llm-uscite__intro-copy">
					<p class="llm-uscite__hello" data-llm-uscite-hello></p>
					<p class="llm-uscite__hello-sub" data-llm-uscite-hello-sub></p>
				</div>
				<span class="llm-uscite__intro-flag" data-llm-uscite-flag aria-hidden="true"></span>
			</header>

			<section class="llm-uscite__section" aria-labelledby="llm-uscite-latest-title">
				<div class="llm-uscite__head">
					<h2 id="llm-uscite-latest-title" class="llm-uscite__title" data-llm-uscite-title="latest"><?php echo esc_html( self::t( 'it', 'latest' ) ); ?></h2>
					<div class="llm-uscite__nav" role="group" aria-label="<?php echo esc_attr( self::t( $ui, 'carousel_nav' ) ); ?>">
						<button type="button" class="llm-uscite__arrow" data-llm-uscite-prev aria-label="<?php echo esc_attr( self::t( $ui, 'prev' ) ); ?>">
							<span aria-hidden="true">&#8249;</span>
						</button>
						<button type="button" class="llm-uscite__arrow" data-llm-uscite-next aria-label="<?php echo esc_attr( self::t( $ui, 'next' ) ); ?>">
							<span aria-hidden="true">&#8250;</span>
						</button>
					</div>
				</div>
				<div class="llm-uscite__carousel" data-llm-uscite-track tabindex="0">
					<?php
					if ( empty( $latest ) ) {
						echo '<p class="llm-uscite__empty">' . esc_html( self::t( $ui, 'no_stories' ) ) . '</p>';
					} else {
						foreach ( $latest as $card ) {
							echo self::render_story_card( $card, $ui, 'carousel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
					?>
				</div>
			</section>

			<section class="llm-uscite__section" aria-labelledby="llm-uscite-cal-title">
				<h2 id="llm-uscite-cal-title" class="llm-uscite__title" data-llm-uscite-title="calendar"><?php echo esc_html( self::t( 'it', 'calendar' ) ); ?></h2>
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

			<section class="llm-uscite__section" aria-labelledby="llm-uscite-pairs-title">
				<h2 id="llm-uscite-pairs-title" class="llm-uscite__title" data-llm-uscite-title="pairs"><?php echo esc_html( self::t( 'it', 'pairs' ) ); ?></h2>
				<div class="llm-uscite__pairs">
					<?php
					foreach ( $pairs as $pair ) {
						echo self::render_pair_card( $pair, $ui ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					$empty = max( 0, 6 - count( $pairs ) );
					for ( $i = 0; $i < $empty; $i++ ) {
						echo '<div class="llm-uscite__pair-card llm-uscite__pair-card--empty" aria-hidden="true"></div>';
					}
					?>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function latest_stories() {
		$q = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => self::LATEST_N,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
			)
		);
		return self::pack_stories( $q->posts );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function upcoming_stories() {
		$today = current_time( 'Y-m-d' ) . ' 00:00:00';
		$q     = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => array( 'publish', 'future' ),
				'posts_per_page'         => 80,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'date_query'             => array(
					array(
						'after'     => $today,
						'inclusive' => true,
					),
				),
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
			)
		);
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
	 * @param array{known:string,target:string,url:string} $pair Coppia.
	 * @param string                                       $ui   Lingua UI.
	 * @return string
	 */
	private static function render_pair_card( array $pair, $ui ) {
		$known  = $pair['known'];
		$target = $pair['target'];
		$url    = $pair['url'];
		$title  = class_exists( 'LLM_Nav_Menu_Shortcode' )
			? LLM_Nav_Menu_Shortcode::pair_title( $known, $target )
			: '';
		$flag_k = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $known ) : '';
		$flag_t = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $target ) : '';
		$tag    = $url ? 'a' : 'div';
		$cls    = 'llm-uscite__pair-card' . ( $url ? '' : ' llm-uscite__pair-card--soon' );

		ob_start();
		?>
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			class="<?php echo esc_attr( $cls ); ?>"
			<?php if ( $url ) : ?>
				href="<?php echo esc_url( $url ); ?>"
			<?php endif; ?>
		>
			<div class="llm-uscite__pair-cover" aria-hidden="true">
				<span class="llm-uscite__pair-flag llm-uscite__pair-flag--from"><?php echo esc_html( $flag_k ); ?></span>
				<span class="llm-uscite__pair-arrow">→</span>
				<span class="llm-uscite__pair-flag llm-uscite__pair-flag--to"><?php echo esc_html( $flag_t ); ?></span>
			</div>
			<div class="llm-uscite__pair-body">
				<h3 class="llm-uscite__pair-title"><?php echo esc_html( $title ); ?></h3>
				<?php if ( ! $url ) : ?>
					<span class="llm-uscite__soon"><?php echo esc_html( self::t( $ui, 'soon' ) ); ?></span>
				<?php endif; ?>
			</div>
		</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		return (string) ob_get_clean();
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
				'latest'       => 'Ultime aggiunte',
				'calendar'     => 'Calendario Eventi Uscite',
				'pairs'        => 'Coppie di lingue',
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
				'soon'         => 'In arrivo',
			),
			'en' => array(
				'hello'        => 'Hello',
				'hello_name'   => 'Hello, %s',
				'hello_sub'    => 'Nice to see you',
				'latest'       => 'Latest additions',
				'calendar'     => 'Release calendar',
				'pairs'        => 'Language pairs',
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
				'latest'       => 'Ostatnio dodane',
				'calendar'     => 'Kalendarz publikacji',
				'pairs'        => 'Pary językowe',
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
				'latest'       => 'Últimas añadiduras',
				'calendar'     => 'Calendario de salidas',
				'pairs'        => 'Parejas de idiomas',
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
				'latest'       => 'Neueste Ergänzungen',
				'calendar'     => 'Veranstaltungskalender',
				'pairs'        => 'Sprachpaare',
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
				'latest'       => 'Derniers ajouts',
				'calendar'     => 'Calendrier des sorties',
				'pairs'        => 'Paires de langues',
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
			'es' => '🇪🇸',
			'pl' => '🇵🇱',
			'en' => '🇬🇧',
			'de' => '🇩🇪',
			'fr' => '🇫🇷',
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
				'latest'      => self::t( $lang, 'latest' ),
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
			'phrase'    => self::t( $lang, 'phrase' ),
			'phrases'   => self::t( $lang, 'phrases' ),
			'prevMonth' => self::t( $lang, 'prev' ),
			'nextMonth' => self::t( $lang, 'next' ),
			'helloName' => self::t( $lang, 'hello_name' ),
			'hello'     => self::t( $lang, 'hello' ),
		);
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
