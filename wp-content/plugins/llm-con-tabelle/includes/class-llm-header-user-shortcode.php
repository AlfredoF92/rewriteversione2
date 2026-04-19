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
				'login_path'    => '/login',
				'account_path'  => '/area-personale',
				'login_label'   => '',
				'greeting'      => '',
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

		$login_path   = self::normalize_path( (string) $atts['login_path'] );
		$account_path = self::normalize_path( (string) $atts['account_path'] );
		$login_url    = esc_url( home_url( $login_path ) );
		$account_url  = esc_url( home_url( $account_path ) );

		$login_label = (string) $atts['login_label'];
		if ( $login_label === '' ) {
			$login_label = __( 'Accedi', 'llm-con-tabelle' );
		}

		$greeting_tpl = (string) $atts['greeting'];

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<span class="llm-header-user"><a class="llm-header-user__login" href="%1$s"><span class="llm-header-user__icon">%3$s</span><span class="llm-header-user__text">%2$s</span></a></span>',
				$login_url,
				esc_html( $login_label ),
				LLM_Header_UI_Icons::login() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
			);
		}

		$user = wp_get_current_user();
		$name = ( $user && $user->exists() ) ? $user->display_name : '';
		if ( $name === '' ) {
			$name = $user->user_login;
		}
		$name = trim( (string) $name );
		if ( $name === '' ) {
			$name = __( 'Utente', 'llm-con-tabelle' );
		}

		if ( $greeting_tpl !== '' && strpos( $greeting_tpl, '%s' ) !== false ) {
			$label = sprintf( $greeting_tpl, $name );
		} else {
			/* translators: %s: display name of the logged-in user */
			$label = sprintf( __( 'Ciao, %s', 'llm-con-tabelle' ), $name );
		}

		return sprintf(
			'<span class="llm-header-user"><a class="llm-header-user__account" href="%1$s"><span class="llm-header-user__icon">%3$s</span><span class="llm-header-user__text">%2$s</span></a></span>',
			$account_url,
			esc_html( $label ),
			LLM_Header_UI_Icons::user() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico.
		);
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
