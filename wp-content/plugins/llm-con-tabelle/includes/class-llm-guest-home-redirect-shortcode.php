<?php
/**
 * Shortcode [llm_guest_home_redirect] — ospiti verso home (tranne se già in home).
 *
 * Mettilo nel footer (Elementor o widget): attiva il redirect su tutte le pagine
 * per utenti non loggati. Sulla home non fa nulla.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Guest_Home_Redirect_Shortcode {

	const SHORTCODE   = 'llm_guest_home_redirect';
	const OPTION_FLAG = 'llm_guest_home_redirect_active';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_guest' ), 5 );
	}

	/**
	 * Redirect server-side (dopo che lo shortcode è stato almeno una volta nel footer).
	 */
	public static function maybe_redirect_guest() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}
		if ( ! get_option( self::OPTION_FLAG ) ) {
			return;
		}
		if ( self::is_current_page_home() ) {
			return;
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Attiva il redirect e, se serve, script per la prima richiesta.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! get_option( self::OPTION_FLAG ) ) {
			update_option( self::OPTION_FLAG, '1', true );
		}

		if ( is_user_logged_in() || self::is_current_page_home() ) {
			return '';
		}

		$home = wp_json_encode( home_url( '/' ) );

		return '<script>(function(){var h=' . $home . ';try{var p=window.location.pathname.replace(/\/+$/,"")||"/";var t=new URL(h,window.location.origin).pathname.replace(/\/+$/,"")||"/";if(p===t){return;}}catch(e){}window.location.replace(h);})();</script>'
			. '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr( home_url( '/' ) ) . '"></noscript>';
	}

	/**
	 * @return bool
	 */
	private static function is_current_page_home() {
		if ( is_front_page() || is_home() ) {
			return true;
		}

		$request_path = '/';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$parsed = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$request_path = $parsed;
			}
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( ! is_string( $home_path ) || '' === $home_path ) {
			$home_path = '/';
		}

		return untrailingslashit( $request_path ) === untrailingslashit( $home_path );
	}
}
