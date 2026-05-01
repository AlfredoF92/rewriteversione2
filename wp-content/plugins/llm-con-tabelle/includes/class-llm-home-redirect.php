<?php
/**
 * Shortcode [llm_home_redirect] — redirect automatico per coppia linguistica.
 *
 * Quando lo shortcode viene eseguito:
 *  1. Legge la lingua nota (_llm_interface_lang) e la lingua da imparare (_llm_learning_lang)
 *     dalle preferenze dell'utente loggato.
 *  2. Se esiste un URL configurato per quella coppia → reindirizza lì.
 *  3. Altrimenti (utente ospite, lingue non impostate, coppia senza URL) → reindirizza al fallback.
 *
 * Le pagine di destinazione si configurano in:
 * WP Admin → Storie LLM → Redirect Homepage
 *
 * Utilizzo: [llm_home_redirect fallback="/"]
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Home_Redirect {

	const SHORTCODE = 'llm_home_redirect';

	/** Nome opzione WordPress che mappa le coppie alle pagine. */
	const OPT_PAIRS = 'llm_home_redirect_pairs';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Render shortcode: emette uno script JS che reindirizza immediatamente.
	 *
	 * @param array<string,string>|string $atts
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'fallback' => '/',
			),
			$atts,
			self::SHORTCODE
		);

		$fallback_url = home_url( (string) $atts['fallback'] );
		$redirect_url = $fallback_url;

		if ( is_user_logged_in() ) {
			$uid      = get_current_user_id();
			$known    = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, true ) );
			$learning = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true ) );

			if (
				LLM_Languages::is_valid( $known ) &&
				LLM_Languages::is_valid( $learning ) &&
				$known !== $learning
			) {
				$pair_url = self::pair_url( $known, $learning );
				if ( '' !== $pair_url ) {
					$redirect_url = $pair_url;
				}
			}
		}

		$encoded_url = wp_json_encode( $redirect_url );

		return '<script>window.location.replace(' . $encoded_url . ');</script>'
			. '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr( $redirect_url ) . '"></noscript>';
	}

	/**
	 * Restituisce il permalink della pagina configurata per la coppia linguistica.
	 * Restituisce '' se non configurata o non pubblicata.
	 *
	 * @param string $known    Codice lingua conosciuta (es. 'it').
	 * @param string $learning Codice lingua appresa (es. 'en').
	 * @return string
	 */
	public static function pair_url( $known, $learning ) {
		$pairs   = (array) get_option( self::OPT_PAIRS, array() );
		$key     = $known . '_' . $learning;
		$page_id = isset( $pairs[ $key ] ) ? absint( $pairs[ $key ] ) : 0;

		if ( $page_id <= 0 ) {
			return '';
		}

		$page = get_post( $page_id );
		if ( ! $page || 'publish' !== $page->post_status ) {
			return '';
		}

		$permalink = get_permalink( $page_id );
		return $permalink ? (string) $permalink : '';
	}
}
