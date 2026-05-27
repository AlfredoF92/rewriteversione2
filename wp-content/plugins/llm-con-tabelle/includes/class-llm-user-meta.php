<?php
/**
 * Chiavi user meta scalari (lingue, flag demo). Progressi/coin/Bravo → tabelle.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_User_Meta {

	const INTERFACE_LANG  = '_llm_interface_lang';
	const LEARNING_LANG   = '_llm_learning_lang';
	const DEMO_FLAG       = '_llm_demo_user';
	const STRICT_ACCENTS  = '_llm_game_strict_accents';

	public static function init() {
	}

	/**
	 * Restituisce true se l'utente vuole il controllo degli accenti (default: true).
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function get_strict_accents( $user_id ) {
		$val = get_user_meta( (int) $user_id, self::STRICT_ACCENTS, true );
		if ( '' === $val ) {
			return true;
		}
		return (bool) $val;
	}

	/**
	 * @param int  $user_id
	 * @param bool $strict
	 */
	public static function set_strict_accents( $user_id, $strict ) {
		update_user_meta( (int) $user_id, self::STRICT_ACCENTS, $strict ? '1' : '0' );
	}
}
