<?php
/**
 * Tema colore del visitatore (light | dark).
 *
 * Unico punto di verità per tutto il sito:
 *   utente loggato → user meta
 *   ospite         → cookie (leggibile lato server già al primo render)
 *   riserva        → localStorage, che rimette il cookie se scade
 *
 * Default: light, per guest e utenti senza una scelta salvata.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Visitor_Theme {

	const COOKIE       = 'llm_game_theme';
	const COOKIE_DAYS  = 365;
	const DEFAULT      = 'light';
	const RESTORE_FLAG = 'llm_theme_restored';
	const QUERY_VAR    = 'llm_game_theme';

	const COOKIE_LAYOUT       = 'llm_story_layout';
	const DEFAULT_LAYOUT      = 'one';
	const RESTORE_FLAG_LAYOUT = 'llm_layout_restored';
	const QUERY_VAR_LAYOUT    = 'llm_story_layout';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_save_from_request' ), 5 );
		add_action( 'init', array( __CLASS__, 'refresh_cookie' ), 6 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_mirror_script' ), 2 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_login', array( __CLASS__, 'migrate_on_login' ), 10, 2 );
		add_action( 'user_register', array( __CLASS__, 'migrate_on_register' ) );
	}

	/**
	 * Salva la scelta dal query string e toglie il parametro dall’URL.
	 */
	public static function maybe_save_from_request() {
		if ( is_admin() ) {
			return;
		}

		$changed = false;

		if ( isset( $_GET[ self::QUERY_VAR ] ) ) {
			$theme = sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
			if ( self::is_valid( $theme ) ) {
				self::set( $theme );
				$changed = true;
			}
		}

		if ( isset( $_GET[ self::QUERY_VAR_LAYOUT ] ) ) {
			$layout = sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR_LAYOUT ] ) );
			if ( self::is_valid_layout( $layout ) ) {
				self::set_layout( $layout );
				$changed = true;
			}
		}

		if ( ! $changed || headers_sent() ) {
			return;
		}

		wp_safe_redirect( remove_query_arg( array( self::QUERY_VAR, self::QUERY_VAR_LAYOUT ) ) );
		exit;
	}

	/**
	 * Rinnova il cookie e allinea gli utenti loggati al profilo.
	 */
	public static function refresh_cookie() {
		if ( is_admin() || headers_sent() ) {
			return;
		}

		$stored = self::stored();
		if ( '' !== $stored ) {
			self::set_cookie( $stored );
		}

		$layout = self::stored_layout();
		if ( '' !== $layout ) {
			self::set_layout_cookie( $layout );
		}
	}

	/**
	 * @param string $theme Valore da validare.
	 * @return bool
	 */
	public static function is_valid( $theme ) {
		return in_array( $theme, array( 'light', 'dark' ), true );
	}

	/**
	 * @param string $layout Valore da validare.
	 * @return bool
	 */
	public static function is_valid_layout( $layout ) {
		return in_array( $layout, array( 'one', 'two' ), true );
	}

	/**
	 * @return string
	 */
	private static function layout_user_meta_key() {
		if ( class_exists( 'LLM_User_Meta' ) && defined( 'LLM_User_Meta::STORY_LAYOUT' ) ) {
			return LLM_User_Meta::STORY_LAYOUT;
		}
		return '_llm_story_layout';
	}

	/**
	 * @return string
	 */
	private static function user_meta_key() {
		if ( class_exists( 'LLM_User_Meta' ) && defined( 'LLM_User_Meta::UI_THEME' ) ) {
			return LLM_User_Meta::UI_THEME;
		}
		return '_llm_game_theme';
	}

	/**
	 * Tema risolto: salvato oppure default light.
	 *
	 * @return string light|dark
	 */
	public static function get() {
		$theme = self::stored();
		if ( '' === $theme ) {
			$theme = self::DEFAULT;
		}

		$theme = sanitize_key( (string) apply_filters( 'llm_visitor_theme', $theme ) );

		return self::is_valid( $theme ) ? $theme : self::DEFAULT;
	}

	/**
	 * Tema effettivamente salvato, '' se il visitatore non ha ancora scelto.
	 *
	 * @return string
	 */
	public static function stored() {
		if ( is_user_logged_in() ) {
			$meta = sanitize_key( (string) get_user_meta( get_current_user_id(), self::user_meta_key(), true ) );
			if ( self::is_valid( $meta ) ) {
				return $meta;
			}
		}

		$cookie = isset( $_COOKIE[ self::COOKIE ] )
			? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) )
			: '';

		return self::is_valid( $cookie ) ? $cookie : '';
	}

	/**
	 * Salva la scelta (profilo se loggato, cookie sempre).
	 *
	 * @param string $theme light|dark.
	 * @return bool
	 */
	public static function set( $theme ) {
		$theme = sanitize_key( (string) $theme );
		if ( ! self::is_valid( $theme ) ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::user_meta_key(), $theme );
		}

		self::set_cookie( $theme );
		return true;
	}

	/**
	 * @param string $theme light|dark.
	 */
	public static function set_cookie( $theme ) {
		$theme = sanitize_key( (string) $theme );
		if ( ! self::is_valid( $theme ) ) {
			return;
		}

		$_COOKIE[ self::COOKIE ] = $theme;

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$theme,
			time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS ),
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			is_ssl(),
			false
		);
	}

	/**
	 * Layout pagina storia: una colonna (default) o due.
	 *
	 * @return string one|two
	 */
	public static function get_layout() {
		$layout = self::stored_layout();
		if ( '' === $layout ) {
			$layout = self::DEFAULT_LAYOUT;
		}

		$layout = sanitize_key( (string) apply_filters( 'llm_visitor_story_layout', $layout ) );

		return self::is_valid_layout( $layout ) ? $layout : self::DEFAULT_LAYOUT;
	}

	/**
	 * Layout salvato, '' se il visitatore non ha ancora scelto.
	 *
	 * @return string
	 */
	public static function stored_layout() {
		if ( is_user_logged_in() ) {
			$meta = sanitize_key( (string) get_user_meta( get_current_user_id(), self::layout_user_meta_key(), true ) );
			if ( self::is_valid_layout( $meta ) ) {
				return $meta;
			}
		}

		$cookie = isset( $_COOKIE[ self::COOKIE_LAYOUT ] )
			? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_LAYOUT ] ) )
			: '';

		return self::is_valid_layout( $cookie ) ? $cookie : '';
	}

	/**
	 * @param string $layout one|two.
	 * @return bool
	 */
	public static function set_layout( $layout ) {
		$layout = sanitize_key( (string) $layout );
		if ( ! self::is_valid_layout( $layout ) ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::layout_user_meta_key(), $layout );
		}

		self::set_layout_cookie( $layout );
		return true;
	}

	/**
	 * @param string $layout one|two.
	 */
	public static function set_layout_cookie( $layout ) {
		$layout = sanitize_key( (string) $layout );
		if ( ! self::is_valid_layout( $layout ) ) {
			return;
		}

		$_COOKIE[ self::COOKIE_LAYOUT ] = $layout;

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE_LAYOUT,
			$layout,
			time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS ),
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			is_ssl(),
			false
		);
	}

	/**
	 * @param string[] $classes Classi body.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes[] = 'llm-ui-theme-' . self::get();
		$classes[] = 'llm-story-layout-' . self::get_layout();
		return $classes;
	}

	/**
	 * @param string       $user_login Login (non usato).
	 * @param WP_User|null $user       Utente autenticato.
	 */
	public static function migrate_on_login( $user_login, $user = null ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		self::migrate_cookie_to_user( (int) $user->ID );
	}

	/**
	 * @param int $user_id Nuovo utente.
	 */
	public static function migrate_on_register( $user_id ) {
		self::migrate_cookie_to_user( (int) $user_id );
	}

	/**
	 * Copia il tema dal cookie al profilo solo se il profilo è vuoto.
	 *
	 * @param int $user_id Utente destinazione.
	 */
	public static function migrate_cookie_to_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return;
		}

		$current = sanitize_key( (string) get_user_meta( $user_id, self::user_meta_key(), true ) );
		if ( ! self::is_valid( $current ) ) {
			$cookie = isset( $_COOKIE[ self::COOKIE ] )
				? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) )
				: '';
			if ( self::is_valid( $cookie ) ) {
				update_user_meta( $user_id, self::user_meta_key(), $cookie );
			}
		}

		$current_layout = sanitize_key( (string) get_user_meta( $user_id, self::layout_user_meta_key(), true ) );
		if ( ! self::is_valid_layout( $current_layout ) ) {
			$layout_cookie = isset( $_COOKIE[ self::COOKIE_LAYOUT ] )
				? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_LAYOUT ] ) )
				: '';
			if ( self::is_valid_layout( $layout_cookie ) ) {
				update_user_meta( $user_id, self::layout_user_meta_key(), $layout_cookie );
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function script_data() {
		return array(
			'cookieName'         => self::COOKIE,
			'queryVar'           => self::QUERY_VAR,
			'theme'              => self::get(),
			'themeStored'        => '' !== self::stored(),
			'cookieNameLayout'   => self::COOKIE_LAYOUT,
			'queryVarLayout'     => self::QUERY_VAR_LAYOUT,
			'layout'             => self::get_layout(),
			'layoutStored'       => '' !== self::stored_layout(),
			'isLoggedIn'         => is_user_logged_in(),
			'cookiePath'         => COOKIEPATH ? COOKIEPATH : '/',
			'cookieMaxAge'       => (int) ( self::COOKIE_DAYS * DAY_IN_SECONDS ),
			'secure'             => is_ssl(),
			'restoreFlag'        => self::RESTORE_FLAG,
			'restoreFlagLayout'  => self::RESTORE_FLAG_LAYOUT,
		);
	}

	public static function enqueue_mirror_script() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'llm-visitor-theme',
			LLM_TABELLE_URL . 'assets/llm-visitor-theme.js',
			array(),
			LLM_TABELLE_VERSION,
			false
		);

		wp_localize_script( 'llm-visitor-theme', 'llmVisitorTheme', self::script_data() );
	}
}
