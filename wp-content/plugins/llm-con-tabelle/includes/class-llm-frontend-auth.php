<?php
/**
 * Frontend auth: nasconde admin bar, redirect login/logout su pagine dedicate.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Frontend_Auth {

	/** Percorso pagina login (Elementor / shortcode). */
	const DEFAULT_LOGIN_PATH = '/login';

	/** Dopo login riuscito. */
	const DEFAULT_AFTER_LOGIN_PATH = '/area-personale';

	/** Dopo logout (stessa pagina del login con [llm_login_form]). */
	const DEFAULT_AFTER_LOGOUT_PATH = '/login';

	public static function init() {
		add_filter( 'show_admin_bar', '__return_false' );
		add_action( 'get_header', array( __CLASS__, 'remove_admin_bar_bump' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'logout_redirect', array( __CLASS__, 'logout_redirect' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_logged_in_from_login' ) );
		add_action( 'init', array( __CLASS__, 'maybe_redirect_wp_login' ), 1 );
	}

	/**
	 * Rimuove il margine superiore che WordPress aggiunge per la admin bar.
	 */
	public static function remove_admin_bar_bump() {
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
	}

	/**
	 * @return string Path con slash iniziale.
	 */
	public static function login_path() {
		$path = apply_filters( 'llm_auth_login_path', self::DEFAULT_LOGIN_PATH );
		return self::normalize_path( (string) $path );
	}

	/**
	 * @return string
	 */
	public static function after_login_path() {
		$path = apply_filters( 'llm_auth_after_login_path', self::DEFAULT_AFTER_LOGIN_PATH );
		return self::normalize_path( (string) $path );
	}

	/**
	 * @return string
	 */
	public static function after_logout_path() {
		$path = apply_filters( 'llm_auth_after_logout_path', '/' );
		return self::normalize_path( (string) $path );
	}

	/**
	 * @return string URL assoluta.
	 */
	public static function login_url() {
		return home_url( self::login_path() );
	}

	/**
	 * @return string URL assoluta.
	 */
	public static function after_login_url() {
		return home_url( self::after_login_path() );
	}

	/**
	 * @return string URL assoluta.
	 */
	public static function after_logout_url() {
		return home_url( self::after_logout_path() );
	}

	/**
	 * Dopo login: sempre pagina dedicata (area personale), salvo redirect interno sicuro.
	 *
	 * @param string           $redirect_to           URL di destinazione.
	 * @param string           $requested_redirect_to URL richiesta.
	 * @param WP_User|WP_Error $user                  Utente.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( $user instanceof WP_Error ) {
			return $redirect_to;
		}

		if ( self::is_safe_internal_url( $requested_redirect_to ) && ! self::url_targets_wp_admin( $requested_redirect_to ) ) {
			return $requested_redirect_to;
		}

		return self::get_language_home_url( $user->ID );
	}

	/**
	 * Dopo logout: pagina dedicata (es. /logout).
	 *
	 * @param string  $redirect_to           Destinazione.
	 * @param string  $requested_redirect_to Richiesta (da wp_logout_url).
	 * @param WP_User $user                  Utente che esce.
	 * @return string
	 */
	public static function logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
		unset( $redirect_to, $requested_redirect_to, $user );
		return self::after_logout_url();
	}

	/**
	 * Utente già loggato sulla pagina login → home per coppia linguistica.
	 */
	public static function maybe_redirect_logged_in_from_login() {
		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}

		$req_path = self::current_request_path();
		if ( $req_path === self::login_path() ) {
			wp_safe_redirect( self::get_language_home_url( get_current_user_id() ) );
			exit;
		}
	}

	/**
	 * Restituisce l'URL home per la coppia linguistica dell'utente,
	 * con fallback alla home del sito.
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function get_language_home_url( $user_id ) {
		$user_id  = absint( $user_id );
		$known    = sanitize_key( (string) get_user_meta( $user_id, LLM_User_Meta::INTERFACE_LANG, true ) );
		$learning = sanitize_key( (string) get_user_meta( $user_id, LLM_User_Meta::LEARNING_LANG, true ) );

		if (
			$known && $learning && $known !== $learning &&
			class_exists( 'LLM_Home_Redirect' )
		) {
			$url = LLM_Home_Redirect::pair_url( $known, $learning );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Reindirizza wp-login.php alla pagina /login solo per utenti frontend.
	 * Accesso wp-admin / wp-login con redirect_to admin resta disponibile.
	 */
	public static function maybe_redirect_wp_login() {
		if ( is_admin() ) {
			return;
		}

		global $pagenow;
		if ( 'wp-login.php' !== $pagenow ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$allowed = array(
			'logout',
			'lostpassword',
			'retrievepassword',
			'resetpass',
			'rp',
			'postpass',
			'confirm_admin_email',
			'register',
		);
		if ( in_array( $action, $allowed, true ) ) {
			return;
		}

		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';
		if ( is_string( $redirect_to ) && self::url_targets_wp_admin( $redirect_to ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::get_language_home_url( get_current_user_id() ) );
			exit;
		}

		wp_safe_redirect( self::login_url() );
		exit;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private static function url_targets_wp_admin( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}
		$admin_path = wp_parse_url( admin_url(), PHP_URL_PATH );
		if ( ! is_string( $admin_path ) || '' === $admin_path ) {
			return false;
		}
		return 0 === strpos( self::normalize_path( $path ), self::normalize_path( $admin_path ) );
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_safe_internal_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}
		$validated = wp_validate_redirect( $url, false );
		if ( ! $validated ) {
			return false;
		}
		$home = wp_parse_url( home_url( '/' ) );
		$dest = wp_parse_url( $validated );
		if ( empty( $home['host'] ) || empty( $dest['host'] ) ) {
			return false;
		}
		return strtolower( $home['host'] ) === strtolower( $dest['host'] );
	}

	/**
	 * @return string Path corrente (senza query), con slash iniziale.
	 */
	private static function current_request_path() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '/';
		}
		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		return is_string( $path ) ? self::normalize_path( $path ) : '/';
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = trim( $path );
		if ( '' === $path ) {
			return '/';
		}
		if ( '/' !== $path[0] ) {
			return '/' . $path;
		}
		return $path;
	}
}
