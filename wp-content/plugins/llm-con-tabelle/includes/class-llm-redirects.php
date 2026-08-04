<?php
/**
 * Interruttore globale per i redirect frontend del plugin.
 *
 * Imposta LLM_REDIRECTS_ENABLED a true in llm-con-tabelle.php per riattivarli.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Redirects {

	/**
	 * @return bool
	 */
	public static function enabled() {
		return (bool) apply_filters(
			'llm_redirects_enabled',
			defined( 'LLM_REDIRECTS_ENABLED' ) && LLM_REDIRECTS_ENABLED
		);
	}
}
