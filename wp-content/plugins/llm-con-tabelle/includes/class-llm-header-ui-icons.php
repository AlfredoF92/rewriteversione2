<?php
/**
 * Icone SVG minimal (stroke, currentColor) per header / shortcode utente.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Header_UI_Icons
 */
class LLM_Header_UI_Icons {

	/**
	 * @param string $paths Contenuto interno SVG (markup statico).
	 * @return string
	 */
	private static function svg( $paths ) {
		return '<svg class="llm-header-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}

	/**
	 * @return string
	 */
	public static function coin() {
		return self::svg( '<circle cx="12" cy="12" r="7.5"/><path d="M9 12h6"/>' );
	}

	/**
	 * @return string
	 */
	public static function phrases() {
		return self::svg(
			'<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h7"/><circle cx="5" cy="6" r="1.5"/><circle cx="5" cy="12" r="1.5"/><circle cx="5" cy="18" r="1.5"/>'
		);
	}

	/**
	 * @return string
	 */
	public static function bravo() {
		return self::svg(
			'<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>'
		);
	}

	/**
	 * @return string
	 */
	public static function language() {
		return self::svg(
			'<circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3c3.5 3 3.5 15 0 18"/><path d="M12 3c-3.5 3-3.5 15 0 18"/>'
		);
	}

	/**
	 * @return string
	 */
	public static function user() {
		return self::svg(
			'<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'
		);
	}

	/**
	 * @return string
	 */
	public static function login() {
		return self::svg(
			'<path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>'
		);
	}
}
