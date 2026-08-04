<?php
/**
 * Lingue ammesse per le storie LLM.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Languages {

	/**
	 * @return array<string, string>
	 */
	public static function get_codes() {
		$codes = array(
			'en' => __( 'English', 'llm-con-tabelle' ),
			'it' => __( 'Italian', 'llm-con-tabelle' ),
			'pl' => __( 'Polish', 'llm-con-tabelle' ),
			'es' => __( 'Spanish', 'llm-con-tabelle' ),
		);

		return apply_filters( 'llm_language_codes', $codes );
	}

	public static function label( $code ) {
		$codes = self::get_codes();
		return isset( $codes[ $code ] ) ? $codes[ $code ] : $code;
	}

	public static function is_valid( $code ) {
		return array_key_exists( $code, self::get_codes() );
	}

	/**
	 * Emoji bandiera per codice lingua (it|en|pl|es).
	 *
	 * @param string $code Codice lingua.
	 * @return string
	 */
	public static function flag_emoji( $code ) {
		$flags = array(
			'it' => '🇮🇹',
			'en' => '🇬🇧',
			'pl' => '🇵🇱',
			'es' => '🇪🇸',
		);
		$c = sanitize_key( (string) $code );
		return $flags[ $c ] ?? '';
	}

	/**
	 * Mappa codice → emoji bandiera per tutte le lingue ammesse.
	 *
	 * @return array<string, string>
	 */
	public static function flag_map() {
		$map = array();
		foreach ( array_keys( self::get_codes() ) as $code ) {
			$emoji = self::flag_emoji( $code );
			if ( '' !== $emoji ) {
				$map[ $code ] = $emoji;
			}
		}
		return $map;
	}
}
