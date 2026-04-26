<?php
/**
 * Shortcode header: coin, frasi completate, bravi ricevuti, lingua di studio.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_User_Stat_Shortcodes
 */
class LLM_User_Stat_Shortcodes {

	const COINS    = 'llm_user_coins';
	const PHRASES  = 'llm_user_phrases';
	const BRAVI    = 'llm_user_bravi';
	const LANG     = 'llm_user_learning_lang';

	/**
	 * Avvio hook.
	 */
	public static function init() {
		add_shortcode( self::COINS, array( __CLASS__, 'render_coins' ) );
		add_shortcode( self::PHRASES, array( __CLASS__, 'render_phrases' ) );
		add_shortcode( self::BRAVI, array( __CLASS__, 'render_bravi' ) );
		add_shortcode( self::LANG, array( __CLASS__, 'render_learning_lang' ) );
	}

	/**
	 * Carica CSS condiviso con [llm_header_user].
	 */
	private static function enqueue_style() {
		wp_enqueue_style(
			'llm-header-user',
			LLM_TABELLE_URL . 'assets/llm-header-user.css',
			array(),
			LLM_TABELLE_VERSION
		);
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = trim( $path );
		if ( $path === '' ) {
			return '/';
		}
		if ( $path[0] !== '/' ) {
			return '/' . $path;
		}
		return $path;
	}

	/**
	 * @param array<string, string> $atts Attributi.
	 * @return array{login_path:string, login_url:string, login_label:string}
	 */
	private static function login_context( $atts ) {
		$login_path = self::normalize_path( isset( $atts['login_path'] ) ? (string) $atts['login_path'] : '/login' );
		$login_url  = esc_url( home_url( $login_path ) );
		$label      = isset( $atts['login_label'] ) ? (string) $atts['login_label'] : '';
		if ( $label === '' ) {
			$label = __( 'Accedi', 'llm-con-tabelle' );
		}
		return array(
			'login_path'  => $login_path,
			'login_url'   => $login_url,
			'login_label' => $label,
		);
	}

	/**
	 * @param string               $target_url URL destinazione (coin/frasi/bravi).
	 * @param array{login_url:string, login_label:string} $login_ctx Contesto login.
	 * @param int                  $value Valore numerico.
	 * @param string               $label Etichetta con due punti (es. "Coin:" / "Bravi ricevuti:"), tradotta.
	 * @param string               $icon_svg Markup SVG da LLM_Header_UI_Icons.
	 * @return string
	 */
	private static function render_number_chip( $target_url, array $login_ctx, $value, $label, $icon_svg ) {
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<span class="llm-stat-chip-wrap"><a class="llm-stat-chip llm-stat-chip--guest" href="%1$s"><span class="llm-stat-chip__icon">%3$s</span><span class="llm-stat-chip__text">%2$s</span></a></span>',
				$login_ctx['login_url'],
				esc_html( $login_ctx['login_label'] ),
				$icon_svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico da classe icone.
			);
		}

		$n        = max( 0, (int) $value );
		$label    = trim( (string) $label );
		$aria_txt = trim( $label . ' ' . (string) $n );

		return sprintf(
			'<span class="llm-stat-chip-wrap"><a class="llm-stat-chip llm-stat-chip--kv" href="%1$s" aria-label="%2$s"><span class="llm-stat-chip__icon">%3$s</span><span class="llm-stat-chip__body"><span class="llm-stat-chip__label">%4$s</span><span class="llm-stat-chip__value">%5$d</span></span></a></span>',
			esc_url( $target_url ),
			esc_attr( $aria_txt ),
			$icon_svg, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
			esc_html( $label ),
			$n
		);
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_coins( $atts ) {
		self::enqueue_style();
		$atts = shortcode_atts(
			array(
				'coin_path'   => '/coin',
				'login_path'  => '/login',
				'login_label' => '',
			),
			$atts,
			self::COINS
		);
		$ctx    = self::login_context( $atts );
		$target = esc_url( home_url( self::normalize_path( (string) $atts['coin_path'] ) ) );
		$uid    = get_current_user_id();
		$bal = $uid ? LLM_User_Stats::get_balance( $uid ) : 0;

		return self::render_number_chip( $target, $ctx, $bal, 'Points:', LLM_Header_UI_Icons::coin() );
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_phrases( $atts ) {
		self::enqueue_style();
		$atts = shortcode_atts(
			array(
				'phrases_path' => '/frasi',
				'login_path'   => '/login',
				'login_label'  => '',
			),
			$atts,
			self::PHRASES
		);
		$ctx    = self::login_context( $atts );
		$target = esc_url( home_url( self::normalize_path( (string) $atts['phrases_path'] ) ) );
		$uid    = get_current_user_id();
		$n = $uid ? LLM_User_Stats::count_completed_phrases( $uid ) : 0;

		return self::render_number_chip( $target, $ctx, $n, 'Minephrases:', LLM_Header_UI_Icons::phrases() );
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_bravi( $atts ) {
		self::enqueue_style();
		$atts = shortcode_atts(
			array(
				'bravi_path'  => '/bravi',
				'login_path'  => '/login',
				'login_label' => '',
			),
			$atts,
			self::BRAVI
		);
		$ctx    = self::login_context( $atts );
		$target = esc_url( home_url( self::normalize_path( (string) $atts['bravi_path'] ) ) );
		$uid    = get_current_user_id();
		$n = $uid ? LLM_Community::count_bravi_received( $uid ) : 0;

		return self::render_number_chip( $target, $ctx, $n, 'Likes:', LLM_Header_UI_Icons::bravo() );
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_learning_lang( $atts ) {
		self::enqueue_style();
		$atts = shortcode_atts(
			array(
				'link_path'   => '/area-personale',
				'login_path'  => '/login',
				'login_label' => '',
			),
			$atts,
			self::LANG
		);
		$ctx = self::login_context( $atts );

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<span class="llm-stat-chip-wrap"><a class="llm-stat-chip llm-stat-chip--guest llm-stat-chip--lang" href="%1$s"><span class="llm-stat-chip__icon">%3$s</span><span class="llm-stat-chip__text">%2$s</span></a></span>',
				$ctx['login_url'],
				esc_html( $ctx['login_label'] ),
				LLM_Header_UI_Icons::login() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
			);
		}

		$uid  = get_current_user_id();
		$code = (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true );
		$code = sanitize_key( $code );

		if ( $code === '' || ! LLM_Languages::is_valid( $code ) ) {
			$label = __( 'Lingua non impostata', 'llm-con-tabelle' );
		} else {
			$label = LLM_Languages::label( $code );
		}

		$link_path   = trim( (string) $atts['link_path'] );
		$lang_label  = 'Learn:';
		$icon        = LLM_Header_UI_Icons::language();
		$inner       = sprintf(
			'<span class="llm-stat-chip__body"><span class="llm-stat-chip__label">%1$s</span><span class="llm-stat-chip__value">%2$s</span></span>',
			esc_html( $lang_label ),
			esc_html( $label )
		);
		$aria_full = trim( $lang_label . ' ' . $label );

		if ( $link_path !== '' ) {
			$url = esc_url( home_url( self::normalize_path( $link_path ) ) );
			return sprintf(
				'<span class="llm-stat-chip-wrap"><a class="llm-stat-chip llm-stat-chip--lang llm-stat-chip--kv" href="%1$s" aria-label="%2$s"><span class="llm-stat-chip__icon">%3$s</span>%4$s</a></span>',
				$url,
				esc_attr( $aria_full ),
				$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
				$inner
			);
		}

		return sprintf(
			'<span class="llm-stat-chip-wrap"><span class="llm-stat-chip llm-stat-chip--lang llm-stat-chip--kv llm-stat-chip--static" aria-label="%1$s"><span class="llm-stat-chip__icon">%2$s</span>%3$s</span></span>',
			esc_attr( $aria_full ),
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
			$inner
		);
	}
}
