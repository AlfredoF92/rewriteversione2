<?php
/**
 * Frontend auth: admin bar solo per gestori del sito, redirect login/logout su pagine dedicate.
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
		add_filter( 'show_admin_bar', array( __CLASS__, 'filter_show_admin_bar' ), 99 );
		add_action( 'get_header', array( __CLASS__, 'remove_admin_bar_bump' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'logout_redirect', array( __CLASS__, 'logout_redirect' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_logged_in_from_login' ) );
		add_action( 'init', array( __CLASS__, 'maybe_redirect_wp_login' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_block_wp_admin' ), 1 );
	}

	/**
	 * Studenti (non admin) fuori da /wp-admin/. Guest → login gestito da WordPress.
	 * Lascia aperti admin-ajax.php e admin-post.php.
	 */
	public static function maybe_block_wp_admin() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		if ( is_string( $pagenow ) && in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) : '';
		if ( $script && ( false !== strpos( $script, 'admin-ajax.php' ) || false !== strpos( $script, 'admin-post.php' ) ) ) {
			return;
		}

		wp_safe_redirect( self::get_language_home_url( get_current_user_id() ) );
		exit;
	}

	/**
	 * Barra WP in frontend solo per chi può gestire il sito (admin).
	 *
	 * @param bool $show Valore precedente.
	 * @return bool
	 */
	public static function filter_show_admin_bar( $show ) {
		unset( $show );
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Rimuove il margine superiore della admin bar quando la barra è nascosta.
	 */
	public static function remove_admin_bar_bump() {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}
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
	 * Dopo login:
	 * - admin verso wp-admin (se richiesto) o destinazione WP;
	 * - studente mai in wp-admin → home / pagina storie della coppia.
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

		$is_admin = ( $user instanceof WP_User ) && user_can( $user, 'manage_options' );

		if ( $is_admin ) {
			if ( self::url_targets_wp_admin( $requested_redirect_to ) ) {
				return $requested_redirect_to;
			}
			if ( self::url_targets_wp_admin( $redirect_to ) ) {
				return $redirect_to;
			}
			if ( LLM_Redirects::enabled() ) {
				if ( self::is_safe_internal_url( $requested_redirect_to ) ) {
					return $requested_redirect_to;
				}
				return self::get_language_home_url( $user->ID );
			}
			return $redirect_to;
		}

		/* Studente: mai backend. */
		if ( self::url_targets_wp_admin( $redirect_to ) || self::url_targets_wp_admin( $requested_redirect_to ) ) {
			return self::get_language_home_url( $user->ID );
		}

		if ( LLM_Redirects::enabled() ) {
			if ( self::is_safe_internal_url( $requested_redirect_to ) && ! self::url_targets_wp_admin( $requested_redirect_to ) ) {
				return $requested_redirect_to;
			}
			return self::get_language_home_url( $user->ID );
		}

		/* Anche con redirect globali spenti: se WP manderebbe in admin, forza home. */
		return self::url_targets_wp_admin( $redirect_to )
			? self::get_language_home_url( $user->ID )
			: $redirect_to;
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
		if ( ! LLM_Redirects::enabled() ) {
			return $redirect_to;
		}

		unset( $redirect_to, $requested_redirect_to, $user );
		return self::after_logout_url();
	}

	/**
	 * Utente già loggato sulla pagina login → home per coppia linguistica.
	 */
	public static function maybe_redirect_logged_in_from_login() {
		if ( ! LLM_Redirects::enabled() ) {
			return;
		}

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
	 * Reindirizza wp-login.php (accesso) alla pagina interna /login.
	 * Mantiene redirect_to (anche verso wp-admin) così dopo il login admin torna in backend.
	 * logout / reset password restano su wp-login.php.
	 */
	public static function maybe_redirect_wp_login() {
		if ( is_admin() ) {
			return;
		}

		global $pagenow;
		if ( 'wp-login.php' !== $pagenow ) {
			return;
		}

		$action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
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
		$redirect_to = is_string( $redirect_to ) ? $redirect_to : '';

		if ( is_user_logged_in() ) {
			if ( current_user_can( 'manage_options' ) ) {
				$dest = ( '' !== $redirect_to && self::url_targets_wp_admin( $redirect_to ) )
					? $redirect_to
					: admin_url();
				wp_safe_redirect( $dest );
				exit;
			}
			wp_safe_redirect( self::get_language_home_url( get_current_user_id() ) );
			exit;
		}

		$login_url = self::login_url();
		if ( '' !== $redirect_to && self::is_safe_internal_url( $redirect_to ) ) {
			$login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
		}

		wp_safe_redirect( $login_url );
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
