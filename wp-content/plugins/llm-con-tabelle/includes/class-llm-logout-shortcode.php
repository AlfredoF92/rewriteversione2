<?php
/**
 * Shortcode pulsante logout.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Logout_Shortcode {

	const SHORTCODE = 'llm_logout_button';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'redirect_path' => '/',
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'llm-user-profile',
			LLM_TABELLE_URL . 'assets/llm-user-profile.css',
			array(),
			LLM_TABELLE_VERSION
		);

		$path = trim( (string) $atts['redirect_path'] );
		if ( '' === $path ) {
			$path = '/';
		}
		if ( isset( $path[0] ) && '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		$lang       = class_exists( 'LLM_User_Settings_I18n' ) ? LLM_User_Settings_I18n::lang() : 'it';
		$label      = class_exists( 'LLM_User_Settings_I18n' ) ? LLM_User_Settings_I18n::get( 'logout', $lang ) : 'Logout';
		$logout_url = esc_url( wp_logout_url( home_url( $path ) ) );

		return sprintf(
			'<span class="llm-user-profile__actions"><a class="llm-user-profile__btn llm-user-profile__btn--ghost" href="%1$s">%2$s</a></span>',
			$logout_url,
			esc_html( $label )
		);
	}
}
