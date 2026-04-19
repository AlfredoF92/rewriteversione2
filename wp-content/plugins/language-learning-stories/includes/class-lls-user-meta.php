<?php
/**
 * Chiavi meta utente LLS (profilo lingue, progressi, coin).
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_User_Meta {

	const INTERFACE_LANG   = '_lls_interface_lang';
	const LEARNING_LANG    = '_lls_learning_lang';
	const COIN_BALANCE     = '_lls_coin_balance';
	const PHRASE_DONE      = '_lls_phrase_done';
	const UNLOCKED_STORIES = '_lls_unlocked_stories';
	const STORY_COMPLETED  = '_lls_story_completed';
	const COIN_LEDGER      = '_lls_coin_ledger';
	const DEMO_FLAG        = '_lls_demo_user';
	const BRAVO_GIVEN      = '_lls_bravo_given';

	public static function init() {
	}
}
