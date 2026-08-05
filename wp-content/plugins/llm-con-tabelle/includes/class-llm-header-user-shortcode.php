<?php
/**
 * Shortcode saluto utente / pulsante Accedi per header.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Header_User_Shortcode
 */
class LLM_Header_User_Shortcode {

	const SHORTCODE = 'llm_header_user';

	/**
	 * Avvio hook.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts Attributi shortcode.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'login_path'   => '/login',
				'account_path' => '/area-personale',
				'guest_path'   => '/',
				'guest_label'  => '',
				'login_label'  => '',
				'greeting'     => '',
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'llm-header-user',
			LLM_TABELLE_URL . 'assets/llm-header-user.css',
			array(),
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
			'llm-header-user',
			LLM_TABELLE_URL . 'assets/llm-header-user.js',
			array( 'llm-guest-browser-store' ),
			LLM_TABELLE_VERSION,
			true
		);

		$ui_lang = class_exists( 'LLM_Visitor_Lang' )
			? LLM_Visitor_Lang::known()
			: ( class_exists( 'LLM_Category_Translations' ) ? LLM_Category_Translations::current_user_lang() : 'it' );
		$ui_lang = sanitize_key( (string) $ui_lang );
		if ( ! class_exists( 'LLM_Languages' ) || ! LLM_Languages::is_valid( $ui_lang ) ) {
			$ui_lang = 'it';
		}

		$flag = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $ui_lang ) : '';

		$account_path = self::normalize_path( (string) $atts['account_path'] );
		$guest_path   = self::normalize_path( (string) $atts['guest_path'] );
		$login_path   = self::normalize_path( (string) $atts['login_path'] );
		$guest_url    = esc_url( home_url( $login_path ? $login_path : $guest_path ) );
		$account_url  = esc_url( home_url( $account_path ) );

		$guest_label = trim( (string) $atts['guest_label'] );
		if ( '' === $guest_label ) {
			$guest_label = self::guest_label_for_lang( $ui_lang );
		}

		$greeting_tpl = trim( (string) $atts['greeting'] );
		if ( '' === $greeting_tpl || false === strpos( $greeting_tpl, '%s' ) ) {
			$greeting_tpl = self::greeting_template_for_lang( $ui_lang );
		}

		$flag_map = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_map() : array();

		$flag_html = '' !== $flag
			? '<span class="llm-header-user__flag" data-llm-header-user-flag aria-hidden="true">' . esc_html( $flag ) . '</span>'
			: '<span class="llm-header-user__flag" data-llm-header-user-flag aria-hidden="true" hidden></span>';

		$display_name = '';
		$is_guest     = ! is_user_logged_in();

		if ( ! $is_guest ) {
			$user = wp_get_current_user();
			$display_name = ( $user && $user->exists() ) ? trim( (string) $user->display_name ) : '';
			if ( '' === $display_name ) {
				$display_name = trim( (string) $user->user_login );
			}
			if ( '' === $display_name ) {
				$display_name = __( 'Utente', 'llm-con-tabelle' );
			}
		}

		$browser_user_label = self::browser_user_label_for_lang( $ui_lang );

		wp_localize_script(
			'llm-header-user',
			'llmHeaderUser',
			array(
				'isGuest'           => $is_guest,
				'uiLang'            => $ui_lang,
				'guestLabel'        => $guest_label,
				'greetingTpl'       => $greeting_tpl,
				'greetings'         => self::greeting_templates(),
				'guestLabels'       => self::guest_labels(),
				'browserUserLabels' => self::browser_user_labels(),
				'browserUserLabel'  => $browser_user_label,
				'flagMap'           => $flag_map,
				'displayName'       => $display_name,
			)
		);

		if ( $is_guest ) {
			return sprintf(
				'<span class="llm-header-user" data-llm-header-user data-is-guest="1">'
				. '<a class="llm-header-user__login" href="%1$s">'
				. '%4$s'
				. '<span class="llm-header-user__icon">%3$s</span>'
				. '<span class="llm-header-user__text" data-llm-header-user-text>%2$s</span>'
				. '<span class="llm-header-user__browser-tag" data-llm-header-user-browser-tag hidden>%5$s</span>'
				. '</a></span>',
				$guest_url,
				esc_html( $guest_label ),
				LLM_Header_UI_Icons::user(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
				$flag_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup controllato.
				esc_html( $browser_user_label )
			);
		}

		$label = sprintf( $greeting_tpl, $display_name );

		$badge = '';
		if ( current_user_can( 'manage_options' ) ) {
			$badge = '<span class="llm-header-user__badge" title="Administrator">ADMIN</span>';
		}

		return sprintf(
			'<span class="llm-header-user" data-llm-header-user data-is-guest="0">'
			. '<a class="llm-header-user__account" href="%1$s">'
			. '%5$s'
			. '<span class="llm-header-user__icon">%3$s</span>'
			. '<span class="llm-header-user__text" data-llm-header-user-text>%2$s</span>%4$s'
			. '</a></span>',
			$account_url,
			esc_html( $label ),
			LLM_Header_UI_Icons::user(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
			$badge, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup statico controllato.
			$flag_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup controllato.
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function greeting_templates() {
		return array(
			'it' => 'Ciao, %s',
			'en' => 'Hi, %s',
			'pl' => 'Cześć, %s',
			'es' => 'Hola, %s',
		);
	}

	/**
	 * @param string $lang Codice lingua.
	 * @return string
	 */
	private static function greeting_template_for_lang( $lang ) {
		$all = self::greeting_templates();
		$lang = sanitize_key( (string) $lang );
		return isset( $all[ $lang ] ) ? $all[ $lang ] : $all['it'];
	}

	/**
	 * @return array<string, string>
	 */
	private static function guest_labels() {
		return array(
			'it' => 'Accedi',
			'en' => 'Sign in',
			'pl' => 'Zaloguj',
			'es' => 'Acceder',
		);
	}

	/**
	 * @param string $lang Codice lingua.
	 * @return string
	 */
	private static function guest_label_for_lang( $lang ) {
		$all  = self::guest_labels();
		$lang = sanitize_key( (string) $lang );
		return isset( $all[ $lang ] ) ? $all[ $lang ] : $all['it'];
	}

	/**
	 * @return array<string, string>
	 */
	private static function browser_user_labels() {
		return array(
			'it' => 'Utente browser',
			'en' => 'Browser user',
			'pl' => 'Uzytkownik przegladarki',
			'es' => 'Usuario del navegador',
		);
	}

	/**
	 * @param string $lang Codice lingua.
	 * @return string
	 */
	private static function browser_user_label_for_lang( $lang ) {
		$all  = self::browser_user_labels();
		$lang = sanitize_key( (string) $lang );
		return isset( $all[ $lang ] ) ? $all[ $lang ] : $all['it'];
	}

	/**
	 * Percorso con slash iniziale.
	 *
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
}
