<?php
/**
 * Shortcode [llm_home_redirect] — redirect automatico per coppia linguistica.
 *
 * Quando lo shortcode è presente (tipicamente in home):
 *  1. Utente loggato → pagina storie della coppia in profilo.
 *  2. Ospite con cookie → pagina storie della coppia scelta in precedenza.
 *  3. Ospite alla prima visita → default italiano → inglese, cookie salvati 30 giorni.
 *
 * Le pagine di destinazione si configurano in:
 * WP Admin → Storie LLM → Redirect Homepage
 *
 * Utilizzo: [llm_home_redirect]
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

	/** Attivo dopo che lo shortcode è stato renderizzato almeno una volta. */
	const OPTION_FLAG = 'llm_home_redirect_active';

	/** Default ospite prima visita: italiano → inglese. */
	const DEFAULT_KNOWN    = 'it';
	const DEFAULT_LEARNING = 'en';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 6 );
	}

	/**
	 * Redirect server-side dalla home (cookie impostabili prima dell’output).
	 */
	public static function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( self::should_bypass_for_site_editors() ) {
			return;
		}
		if ( ! get_option( self::OPTION_FLAG ) ) {
			return;
		}
		if ( ! self::is_current_page_home() ) {
			return;
		}

		$pair = self::resolve_pair();
		if ( ! $pair ) {
			return;
		}

		$known    = $pair['known'];
		$learning = $pair['learning'];

		self::persist_choice( $known, $learning );

		$pair_url = self::pair_url( $known, $learning );
		if ( '' === $pair_url || self::is_current_url( $pair_url ) ) {
			return;
		}

		wp_safe_redirect( $pair_url );
		exit;
	}

	/**
	 * Nessun redirect se stai modificando il sito (admin WP / Elementor / Customizer).
	 *
	 * @return bool
	 */
	private static function should_bypass_for_site_editors() {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( is_customize_preview() || is_preview() ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo rilevamento contesto editor.
		if ( ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['elementor_library'] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && 'elementor' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			return true;
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
				return true;
			}
			if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
				return true;
			}
		}

		return (bool) apply_filters( 'llm_home_redirect_bypass', false );
	}

	/**
	 * Render shortcode: attiva il redirect e fallback JS se siamo ancora in home.
	 *
	 * @param array<string,string>|string $atts
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(),
			$atts,
			self::SHORTCODE
		);

		if ( ! get_option( self::OPTION_FLAG ) ) {
			update_option( self::OPTION_FLAG, '1', true );
		}

		if ( self::should_bypass_for_site_editors() ) {
			return '';
		}

		$pair = self::resolve_pair();
		if ( ! $pair ) {
			return '';
		}

		$known    = $pair['known'];
		$learning = $pair['learning'];

		self::persist_choice( $known, $learning );

		$pair_url = self::pair_url( $known, $learning );
		if ( '' === $pair_url ) {
			return '';
		}

		if ( self::is_current_url( $pair_url ) ) {
			return '';
		}

		$encoded_url = wp_json_encode( $pair_url );
		$cookie_js   = self::cookie_bootstrap_script( $known, $learning );

		return $cookie_js
			. '<script>window.location.replace(' . $encoded_url . ');</script>'
			. '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr( $pair_url ) . '"></noscript>';
	}

	/**
	 * Risolve la coppia da usare per il redirect.
	 *
	 * @return array{known:string,learning:string}|null
	 */
	private static function resolve_pair() {
		if ( is_user_logged_in() ) {
			$uid      = get_current_user_id();
			$known    = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, true ) );
			$learning = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true ) );

			if (
				! LLM_Languages::is_valid( $known ) ||
				! LLM_Languages::is_valid( $learning ) ||
				$known === $learning
			) {
				return null;
			}

			return array(
				'known'    => $known,
				'learning' => $learning,
			);
		}

		$known    = sanitize_key( wp_unslash( $_COOKIE[ LLM_Lang_Cards_Shortcode::COOKIE_KNOWN ] ?? '' ) );
		$learning = sanitize_key( wp_unslash( $_COOKIE[ LLM_Lang_Cards_Shortcode::COOKIE_LEARNING ] ?? '' ) );

		if (
			LLM_Languages::is_valid( $known ) &&
			LLM_Languages::is_valid( $learning ) &&
			$known !== $learning
		) {
			return array(
				'known'    => $known,
				'learning' => $learning,
			);
		}

		/* Ospite prima visita (o cookie incompleti): sempre IT → EN. */
		return array(
			'known'    => self::DEFAULT_KNOWN,
			'learning' => self::DEFAULT_LEARNING,
		);
	}

	/**
	 * Salva/rinnova cookie (e meta se loggato già ha le lingue).
	 *
	 * @param string $known
	 * @param string $learning
	 */
	private static function persist_choice( $known, $learning ) {
		if ( class_exists( 'LLM_Lang_Cards_Shortcode' ) ) {
			LLM_Lang_Cards_Shortcode::persist_pair_cookies( $known, $learning );
		}
	}

	/**
	 * Fallback JS per i cookie se PHP non ha potuto impostarli (headers già inviati).
	 *
	 * @param string $known
	 * @param string $learning
	 * @return string
	 */
	private static function cookie_bootstrap_script( $known, $learning ) {
		$max_age = (int) ( 30 * DAY_IN_SECONDS );
		$path    = COOKIEPATH ? COOKIEPATH : '/';
		$secure  = is_ssl() ? '; Secure' : '';

		$known_js    = wp_json_encode( (string) $known );
		$learning_js = wp_json_encode( (string) $learning );
		$path_js     = wp_json_encode( (string) $path );

		return '<script>(function(){var p=' . $path_js . ',m=' . $max_age . ',s=' . wp_json_encode( $secure ) . ';'
			. 'document.cookie="llm_interface_lang="+encodeURIComponent(' . $known_js . ')+"; path="+p+"; max-age="+m+"; SameSite=Lax"+s;'
			. 'document.cookie="llm_learning_lang="+encodeURIComponent(' . $learning_js . ')+"; path="+p+"; max-age="+m+"; SameSite=Lax"+s;'
			. '})();</script>';
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

	/**
	 * True se l'URL richiesto corrisponde all'URL assoluto (path + query + host).
	 *
	 * @param string $url URL assoluto.
	 * @return bool
	 */
	private static function is_current_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}

		$current = ( is_ssl() ? 'https://' : 'http://' );
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$current .= wp_unslash( $_SERVER['HTTP_HOST'] );
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$current .= wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		return untrailingslashit( strtolower( $url ) ) === untrailingslashit( strtolower( $current ) );
	}
}
