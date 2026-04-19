<?php
/**
 * Lingue ammesse (codice => etichetta).
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Languages {

	/**
	 * Codici e label predefiniti; filtrabili.
	 *
	 * @return array<string, string>
	 */
	public static function get_codes() {
		$codes = array(
			'en' => __( 'English', 'language-learning-stories' ),
			'it' => __( 'Italian', 'language-learning-stories' ),
			'pl' => __( 'Polish', 'language-learning-stories' ),
			'es' => __( 'Spanish', 'language-learning-stories' ),
		);

		/**
		 * Filtro: codici lingua ammessi per interfaccia / obiettivo.
		 *
		 * @param array $codes slug => label
		 */
		return apply_filters( 'lls_language_codes', $codes );
	}

	/**
	 * @param string $code Codice.
	 * @return string Label o codice grezzo.
	 */
	public static function label( $code ) {
		$codes = self::get_codes();
		return isset( $codes[ $code ] ) ? $codes[ $code ] : $code;
	}

	/**
	 * @param string $code Codice.
	 * @return bool
	 */
	public static function is_valid( $code ) {
		return array_key_exists( $code, self::get_codes() );
	}
}
