<?php
/**
 * Shortcode [menù] / [menu] — pulsante header + popup dark con le coppie di storie.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Nav_Menu_Shortcode {

	const SHORTCODE = 'menù';

	/** Coppie da mostrare per prime (anche se la pagina non c’è ancora). */
	const FEATURED = array(
		array( 'it', 'en' ),
		array( 'it', 'pl' ),
	);

	/** Coppie nascoste per ora. */
	const HIDDEN = array(
		'en_pl',
		'pl_it',
	);

	const CEFR_ORDER = array( 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' );

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_shortcode( 'menu', array( __CLASS__, 'render' ) );
		add_shortcode( 'llm_menu', array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string,string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'account_path' => '/area-personale',
				'label'        => '',
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'llm-nav-menu',
			LLM_TABELLE_URL . 'assets/llm-nav-menu.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-nav-menu',
			LLM_TABELLE_URL . 'assets/llm-nav-menu.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);

		$ui = self::ui_lang();
		$label = trim( (string) $atts['label'] );
		if ( '' === $label ) {
			$label = self::t( $ui, 'menu' );
		}

		$account_path = (string) $atts['account_path'];
		if ( '' === $account_path || '/' !== $account_path[0] ) {
			$account_path = '/' . ltrim( $account_path, '/' );
		}
		$account_url = home_url( $account_path );

		$pairs = self::pairs_to_show();
		$stats = self::story_stats();
		$uid   = 'llm-nav-menu-' . uniqid( '', false );

		ob_start();
		?>
		<div class="llm-nav-menu" data-llm-nav-menu>
			<button
				type="button"
				class="llm-nav-menu__btn"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $uid ); ?>"
				aria-haspopup="dialog"
			>
				<?php
				if ( class_exists( 'LLM_Header_UI_Icons' ) ) {
					echo LLM_Header_UI_Icons::menu(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
				<span class="llm-nav-menu__btn-label"><?php echo esc_html( $label ); ?></span>
			</button>
			<div class="llm-nav-menu__backdrop" hidden></div>
			<div
				id="<?php echo esc_attr( $uid ); ?>"
				class="llm-nav-menu__popup"
				role="dialog"
				aria-modal="true"
				hidden
				aria-labelledby="<?php echo esc_attr( $uid ); ?>-title"
			>
				<button type="button" class="llm-nav-menu__close" aria-label="<?php echo esc_attr( self::t( $ui, 'close' ) ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
				<div class="llm-nav-menu__head">
					<p class="llm-nav-menu__kicker"><?php echo esc_html( self::t( $ui, 'kicker' ) ); ?></p>
					<h2 id="<?php echo esc_attr( $uid ); ?>-title" class="llm-nav-menu__title"><?php echo esc_html( self::t( $ui, 'title' ) ); ?></h2>
				</div>
				<div class="llm-nav-menu__grid">
					<?php foreach ( $pairs as $pair ) : ?>
						<?php
						$known  = $pair['known'];
						$target = $pair['target'];
						$key    = $known . '_' . $target;
						$url    = $pair['url'];
						$st     = isset( $stats[ $key ] ) ? $stats[ $key ] : array( 'cefr' => array(), 'week' => 0, 'total' => 0 );
						$tag    = $url ? 'a' : 'div';
						$cls    = 'llm-nav-menu__card' . ( $url ? '' : ' llm-nav-menu__card--soon' );
						?>
						<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							class="<?php echo esc_attr( $cls ); ?>"
							<?php if ( $url ) : ?>
								href="<?php echo esc_url( $url ); ?>"
							<?php endif; ?>
						>
							<p class="llm-nav-menu__flags">
								<span class="llm-nav-menu__flag llm-nav-menu__flag--from" aria-hidden="true"><?php echo esc_html( LLM_Languages::flag_emoji( $known ) ); ?></span>
								<span class="llm-nav-menu__flags-arrow" aria-hidden="true">→</span>
								<span class="llm-nav-menu__flag llm-nav-menu__flag--to" aria-hidden="true"><?php echo esc_html( LLM_Languages::flag_emoji( $target ) ); ?></span>
							</p>
							<h3 class="llm-nav-menu__card-title"><?php echo esc_html( self::pair_title( $known, $target ) ); ?></h3>
							<p class="llm-nav-menu__card-desc"><?php echo esc_html( self::pair_desc( $known, $target ) ); ?></p>
							<?php if ( ! empty( $st['cefr'] ) ) : ?>
								<ul class="llm-nav-menu__levels">
									<?php foreach ( self::CEFR_ORDER as $lvl ) : ?>
										<?php if ( empty( $st['cefr'][ $lvl ] ) ) { continue; } ?>
										<li><?php echo esc_html( self::level_line( $known, $lvl, (int) $st['cefr'][ $lvl ] ) ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php elseif ( (int) $st['total'] > 0 ) : ?>
								<p class="llm-nav-menu__total"><?php echo esc_html( self::total_line( $known, (int) $st['total'] ) ); ?></p>
							<?php endif; ?>
							<p class="llm-nav-menu__week"><?php echo esc_html( self::week_line( $known, (int) $st['week'] ) ); ?></p>
							<?php if ( ! $url ) : ?>
								<span class="llm-nav-menu__soon"><?php echo esc_html( self::t( $known, 'soon' ) ); ?></span>
							<?php endif; ?>
						</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php endforeach; ?>
				</div>
				<a class="llm-nav-menu__account" href="<?php echo esc_url( $account_url ); ?>">
					<?php
					if ( class_exists( 'LLM_Header_UI_Icons' ) ) {
						echo LLM_Header_UI_Icons::user(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
					<span><?php echo esc_html( self::t( $ui, 'account' ) ); ?></span>
				</a>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Coppie visibili in menù / homepage uscite.
	 *
	 * @return array<int,array{known:string,target:string,url:string}>
	 */
	public static function directory_pairs() {
		return self::pairs_to_show();
	}

	/**
	 * @return array<int,array{known:string,target:string,url:string}>
	 */
	private static function pairs_to_show() {
		$hidden = array_fill_keys( self::HIDDEN, true );
		$seen   = array();
		$out    = array();
		foreach ( self::FEATURED as $pair ) {
			$known  = $pair[0];
			$target = $pair[1];
			$key    = $known . '_' . $target;
			if ( isset( $hidden[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[] = array(
				'known'  => $known,
				'target' => $target,
				'url'    => class_exists( 'LLM_Home_Redirect' ) ? LLM_Home_Redirect::pair_url( $known, $target ) : '',
			);
		}

		$saved = class_exists( 'LLM_Home_Redirect' ) ? (array) get_option( LLM_Home_Redirect::OPT_PAIRS, array() ) : array();
		foreach ( $saved as $key => $page_id ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $seen[ $key ] ) || isset( $hidden[ $key ] ) || absint( $page_id ) <= 0 ) {
				continue;
			}
			$parts = explode( '_', $key );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$known  = $parts[0];
			$target = $parts[1];
			if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) || $known === $target ) {
				continue;
			}
			$url = LLM_Home_Redirect::pair_url( $known, $target );
			if ( '' === $url ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = array(
				'known'  => $known,
				'target' => $target,
				'url'    => $url,
			);
		}

		return $out;
	}

	/**
	 * @return array<string,array{cefr:array<string,int>,week:int,total:int}>
	 */
	private static function story_stats() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array();
		if ( ! defined( 'LLM_STORY_CPT' ) ) {
			return $cache;
		}

		$q = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 800,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - WEEK_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- data locale WP.

		foreach ( $q->posts as $id ) {
			$id     = (int) $id;
			$known  = sanitize_key( (string) get_post_meta( $id, LLM_Story_Meta::KNOWN_LANG, true ) );
			$target = sanitize_key( (string) get_post_meta( $id, LLM_Story_Meta::TARGET_LANG, true ) );
			if ( '' === $known || '' === $target ) {
				continue;
			}
			$key = $known . '_' . $target;
			if ( ! isset( $cache[ $key ] ) ) {
				$cache[ $key ] = array(
					'cefr'  => array(),
					'week'  => 0,
					'total' => 0,
				);
			}
			$cache[ $key ]['total']++;

			$cefr_raw = (string) get_post_meta( $id, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
			if ( preg_match( '/\b([ABC][12])\b/i', $cefr_raw, $m ) ) {
				$lvl = strtoupper( $m[1] );
				if ( ! isset( $cache[ $key ]['cefr'][ $lvl ] ) ) {
					$cache[ $key ]['cefr'][ $lvl ] = 0;
				}
				$cache[ $key ]['cefr'][ $lvl ]++;
			}

			$post = get_post( $id );
			if ( $post && $post->post_date >= $cutoff ) {
				$cache[ $key ]['week']++;
			}
		}

		return $cache;
	}

	/**
	 * @return string
	 */
	private static function ui_lang() {
		$code = class_exists( 'LLM_Visitor_Lang' ) ? LLM_Visitor_Lang::known() : 'it';
		$code = sanitize_key( (string) $code );
		return ( class_exists( 'LLM_Languages' ) && LLM_Languages::is_valid( $code ) ) ? $code : 'it';
	}

	/**
	 * @param string $lang Chiave UI.
	 * @param string $key  Chiave stringa.
	 * @return string
	 */
	private static function t( $lang, $key ) {
		$all = array(
			'it' => array(
				'menu'    => 'Menù',
				'close'   => 'Chiudi',
				'kicker'  => 'LoveRewrite',
				'title'   => 'Scegli le storie',
				'account' => 'Area personale',
				'soon'    => 'In arrivo',
			),
			'en' => array(
				'menu'    => 'Menu',
				'close'   => 'Close',
				'kicker'  => 'LoveRewrite',
				'title'   => 'Choose your stories',
				'account' => 'Your account',
				'soon'    => 'Coming soon',
			),
			'pl' => array(
				'menu'    => 'Menu',
				'close'   => 'Zamknij',
				'kicker'  => 'LoveRewrite',
				'title'   => 'Wybierz historie',
				'account' => 'Strefa osobista',
				'soon'    => 'Wkrótce',
			),
			'es' => array(
				'menu'    => 'Menú',
				'close'   => 'Cerrar',
				'kicker'  => 'LoveRewrite',
				'title'   => 'Elige las historias',
				'account' => 'Área personal',
				'soon'    => 'Próximamente',
			),
		);
		return isset( $all[ $lang ][ $key ] ) ? $all[ $lang ][ $key ] : $all['it'][ $key ];
	}

	/**
	 * Titolo card: «Impara l'inglese», nella lingua 1 (conosciuta).
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @return string
	 */
	public static function pair_title( $known, $target ) {
		$t = array(
			'it' => array(
				'en' => 'Impara l\'inglese',
				'pl' => 'Impara il polacco',
				'es' => 'Impara lo spagnolo',
				'it' => 'Impara l\'italiano',
			),
			'en' => array(
				'it' => 'Learn Italian',
				'pl' => 'Learn Polish',
				'es' => 'Learn Spanish',
				'en' => 'Learn English',
			),
			'pl' => array(
				'it' => 'Ucz się włoskiego',
				'en' => 'Ucz się angielskiego',
				'es' => 'Ucz się hiszpańskiego',
				'pl' => 'Ucz się polskiego',
			),
			'es' => array(
				'it' => 'Aprende italiano',
				'en' => 'Aprende inglés',
				'pl' => 'Aprende polaco',
				'es' => 'Aprende español',
			),
		);
		return isset( $t[ $known ][ $target ] ) ? $t[ $known ][ $target ] : '';
	}

	/**
	 * Descrizione card, nella lingua 1.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @return string
	 */
	public static function pair_desc( $known, $target ) {
		$tpl = array(
			'it' => 'Storie brevi, adatte per tutti i livelli per chi conosce %1$s e vuole imparare %2$s',
			'en' => 'Short stories for all levels, for those who know %1$s and want to learn %2$s',
			'pl' => 'Krótkie historie na wszystkie poziomy, dla tych, którzy znają %1$s i chcą się uczyć %2$s',
			'es' => 'Historias cortas para todos los niveles, para quien conoce %1$s y quiere aprender %2$s',
		);
		$fmt = isset( $tpl[ $known ] ) ? $tpl[ $known ] : $tpl['it'];
		return sprintf( $fmt, self::lang_in_sentence( $known, $known ), self::lang_in_sentence( $known, $target ) );
	}

	/**
	 * Nome lingua da inserire nella frase, nella lingua 1.
	 *
	 * @param string $ui   Lingua 1.
	 * @param string $code Lingua da nominare.
	 * @return string
	 */
	private static function lang_in_sentence( $ui, $code ) {
		$names = array(
			'it' => array(
				'it' => 'l\'italiano',
				'en' => 'l\'inglese',
				'pl' => 'il polacco',
				'es' => 'lo spagnolo',
			),
			'en' => array(
				'it' => 'Italian',
				'en' => 'English',
				'pl' => 'Polish',
				'es' => 'Spanish',
			),
			'pl' => array(
				'it' => 'włoski',
				'en' => 'angielski',
				'pl' => 'polski',
				'es' => 'hiszpański',
			),
			'es' => array(
				'it' => 'el italiano',
				'en' => 'el inglés',
				'pl' => 'el polaco',
				'es' => 'el español',
			),
		);
		return isset( $names[ $ui ][ $code ] ) ? $names[ $ui ][ $code ] : $code;
	}

	/**
	 * @param string $ui    Lingua UI.
	 * @param string $level CEFR.
	 * @param int    $n     Conteggio.
	 * @return string
	 */
	private static function level_line( $ui, $level, $n ) {
		$tpl = array(
			'it' => 'Livello %1$s: %2$d %3$s',
			'en' => 'Level %1$s: %2$d %3$s',
			'pl' => 'Poziom %1$s: %2$d %3$s',
			'es' => 'Nivel %1$s: %2$d %3$s',
		);
		$word = self::stories_word( $ui, $n );
		$fmt  = isset( $tpl[ $ui ] ) ? $tpl[ $ui ] : $tpl['it'];
		return sprintf( $fmt, $level, $n, $word );
	}

	/**
	 * @param string $ui Lingua UI.
	 * @param int    $n  Conteggio.
	 * @return string
	 */
	private static function total_line( $ui, $n ) {
		$tpl = array(
			'it' => '%d %s disponibili',
			'en' => '%d %s available',
			'pl' => '%d %s dostępnych',
			'es' => '%d %s disponibles',
		);
		$fmt = isset( $tpl[ $ui ] ) ? $tpl[ $ui ] : $tpl['it'];
		return sprintf( $fmt, $n, self::stories_word( $ui, $n ) );
	}

	/**
	 * @param string $ui Lingua UI.
	 * @param int    $n  Conteggio.
	 * @return string
	 */
	private static function week_line( $ui, $n ) {
		$tpl = array(
			'it' => 'Ultime storie aggiunte nell\'ultima settimana: %d',
			'en' => 'Stories added in the last week: %d',
			'pl' => 'Historie dodane w ostatnim tygodniu: %d',
			'es' => 'Historias añadidas en la última semana: %d',
		);
		$fmt = isset( $tpl[ $ui ] ) ? $tpl[ $ui ] : $tpl['it'];
		return sprintf( $fmt, $n );
	}

	/**
	 * @param string $ui Lingua UI.
	 * @param int    $n  Conteggio.
	 * @return string
	 */
	private static function stories_word( $ui, $n ) {
		$n = (int) $n;
		$map = array(
			'it' => ( 1 === $n ) ? 'storia' : 'storie',
			'en' => ( 1 === $n ) ? 'story' : 'stories',
			'pl' => ( 1 === $n ) ? 'historia' : 'historii',
			'es' => ( 1 === $n ) ? 'historia' : 'historias',
		);
		return isset( $map[ $ui ] ) ? $map[ $ui ] : 'storie';
	}
}
