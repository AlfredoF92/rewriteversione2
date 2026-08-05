<?php
/**
 * Coppia linguistica del visitatore (lingua conosciuta + lingua da imparare).
 *
 * Unico punto di verità per tutto il plugin:
 *   utente loggato → user meta
 *   ospite         → cookie (leggibile lato server, quindi valido già al primo render)
 *   riserva        → copia in localStorage, che rimette il cookie se scade o viene pulito
 *
 * Il cookie non è HttpOnly di proposito: è una preferenza di lingua, non un dato
 * sensibile, e il mirror in localStorage funziona solo se JS può rileggerlo.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Visitor_Lang {

	const COOKIE_KNOWN    = 'llm_interface_lang';
	const COOKIE_LEARNING = 'llm_learning_lang';
	const COOKIE_DAYS     = 30;

	const DEFAULT_KNOWN    = 'it';
	const DEFAULT_LEARNING = 'en';

	/** Marcatore di sessione per evitare cicli di ricaricamento nel ripristino JS. */
	const RESTORE_FLAG = 'llm_lang_restored';

	public static function init() {
		/* Dopo i form di scelta lingua (priorità 5), così rinnova già il valore nuovo. */
		add_action( 'init', array( __CLASS__, 'refresh_cookies' ), 6 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_mirror_script' ), 2 );
		add_action( 'wp_login', array( __CLASS__, 'migrate_on_login' ), 10, 2 );
		add_action( 'user_register', array( __CLASS__, 'migrate_on_register' ) );
	}

	/**
	 * Riscrive a ogni visita i cookie della coppia linguistica.
	 *
	 * Fa tre cose in una: sposta in avanti la scadenza di chi torna, allinea il cookie
	 * al profilo degli utenti loggati (utile dopo il logout) e converte i vecchi cookie
	 * HttpOnly, che il mirror in localStorage non potrebbe rimettere.
	 */
	public static function refresh_cookies() {
		if ( is_admin() || headers_sent() ) {
			return;
		}

		$known = self::stored_known();
		if ( '' !== $known ) {
			self::set_cookie( self::COOKIE_KNOWN, $known );
		}

		$learning = self::stored_learning();
		if ( '' !== $learning ) {
			self::set_cookie( self::COOKIE_LEARNING, $learning );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Lettura                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Lingua che il visitatore conosce (interfaccia).
	 *
	 * @return string
	 */
	public static function known() {
		$code = self::stored_known();
		if ( '' === $code ) {
			$code = self::DEFAULT_KNOWN;
		}

		/**
		 * Permette di forzare la lingua interfaccia del visitatore.
		 *
		 * @param string $code Codice lingua risolto.
		 */
		$code = sanitize_key( (string) apply_filters( 'llm_visitor_known_lang', $code ) );

		return LLM_Languages::is_valid( $code ) ? $code : self::DEFAULT_KNOWN;
	}

	/**
	 * Lingua che il visitatore sta imparando (target).
	 *
	 * @return string
	 */
	public static function learning() {
		$code = self::stored_learning();
		if ( '' === $code ) {
			$code = self::DEFAULT_LEARNING;
		}

		/**
		 * Permette di forzare la lingua da imparare del visitatore.
		 *
		 * @param string $code Codice lingua risolto.
		 */
		$code = sanitize_key( (string) apply_filters( 'llm_visitor_learning_lang', $code ) );

		return LLM_Languages::is_valid( $code ) ? $code : self::DEFAULT_LEARNING;
	}

	/**
	 * Coppia completa già normalizzata.
	 *
	 * @return array{known:string,learning:string}
	 */
	public static function pair() {
		return array(
			'known'    => self::known(),
			'learning' => self::learning(),
		);
	}

	/**
	 * Lingua conosciuta effettivamente salvata, '' se il visitatore non ha ancora scelto.
	 *
	 * @return string
	 */
	public static function stored_known() {
		return self::stored( LLM_User_Meta::INTERFACE_LANG, self::COOKIE_KNOWN );
	}

	/**
	 * Lingua da imparare effettivamente salvata, '' se il visitatore non ha ancora scelto.
	 *
	 * @return string
	 */
	public static function stored_learning() {
		return self::stored( LLM_User_Meta::LEARNING_LANG, self::COOKIE_LEARNING );
	}

	/**
	 * @param string $meta_key    Chiave user meta.
	 * @param string $cookie_name Nome cookie ospite.
	 * @return string Codice valido oppure ''.
	 */
	private static function stored( $meta_key, $cookie_name ) {
		if ( is_user_logged_in() ) {
			$code = sanitize_key( (string) get_user_meta( get_current_user_id(), $meta_key, true ) );
			if ( LLM_Languages::is_valid( $code ) ) {
				return $code;
			}
		}

		$code = isset( $_COOKIE[ $cookie_name ] )
			? sanitize_key( wp_unslash( $_COOKIE[ $cookie_name ] ) )
			: '';

		return LLM_Languages::is_valid( $code ) ? $code : '';
	}

	/* ------------------------------------------------------------------ */
	/* Scrittura                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * @param string $code Codice lingua.
	 * @return bool True se salvata.
	 */
	public static function set_known( $code ) {
		$code = sanitize_key( (string) $code );
		if ( ! LLM_Languages::is_valid( $code ) ) {
			return false;
		}
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), LLM_User_Meta::INTERFACE_LANG, $code );
		}
		self::set_cookie( self::COOKIE_KNOWN, $code );
		return true;
	}

	/**
	 * @param string $code Codice lingua.
	 * @return bool True se salvata.
	 */
	public static function set_learning( $code ) {
		$code = sanitize_key( (string) $code );
		if ( ! LLM_Languages::is_valid( $code ) ) {
			return false;
		}
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), LLM_User_Meta::LEARNING_LANG, $code );
		}
		self::set_cookie( self::COOKIE_LEARNING, $code );
		return true;
	}

	/**
	 * Salva la coppia solo se entrambi i codici sono validi.
	 *
	 * @param string $known    Lingua conosciuta.
	 * @param string $learning Lingua da imparare.
	 * @return bool
	 */
	public static function set_pair( $known, $learning ) {
		$known    = sanitize_key( (string) $known );
		$learning = sanitize_key( (string) $learning );

		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $learning ) ) {
			return false;
		}

		self::set_known( $known );
		self::set_learning( $learning );
		return true;
	}

	/**
	 * Scrive il cookie e allinea $_COOKIE, così il resto della richiesta vede già il valore nuovo.
	 *
	 * @param string $name  Nome cookie.
	 * @param string $value Codice lingua.
	 */
	public static function set_cookie( $name, $value ) {
		$value = sanitize_key( (string) $value );
		if ( ! LLM_Languages::is_valid( $value ) ) {
			return;
		}

		$_COOKIE[ $name ] = $value;

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			$name,
			$value,
			time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS ),
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			is_ssl(),
			false
		);
	}

	/* ------------------------------------------------------------------ */
	/* Migrazione al login / registrazione                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Al login copia nel profilo le lingue scelte da ospite, ma solo se il profilo è vuoto.
	 *
	 * @param string       $user_login Login (non usato).
	 * @param WP_User|null $user       Utente autenticato.
	 */
	public static function migrate_on_login( $user_login, $user = null ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		self::migrate_cookies_to_user( (int) $user->ID );
	}

	/**
	 * @param int $user_id Nuovo utente.
	 */
	public static function migrate_on_register( $user_id ) {
		self::migrate_cookies_to_user( (int) $user_id );
	}

	/**
	 * Copia le lingue dai cookie al profilo lasciando intatto ciò che l'utente ha già scelto.
	 *
	 * @param int $user_id Utente destinazione.
	 */
	public static function migrate_cookies_to_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return;
		}

		$map = array(
			LLM_User_Meta::INTERFACE_LANG => self::COOKIE_KNOWN,
			LLM_User_Meta::LEARNING_LANG  => self::COOKIE_LEARNING,
		);

		foreach ( $map as $meta_key => $cookie_name ) {
			$current = sanitize_key( (string) get_user_meta( $user_id, $meta_key, true ) );
			if ( LLM_Languages::is_valid( $current ) ) {
				continue;
			}

			$cookie = isset( $_COOKIE[ $cookie_name ] )
				? sanitize_key( wp_unslash( $_COOKIE[ $cookie_name ] ) )
				: '';

			if ( LLM_Languages::is_valid( $cookie ) ) {
				update_user_meta( $user_id, $meta_key, $cookie );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Mirror lato browser                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Dati per il mirror localStorage.
	 *
	 * @return array<string, mixed>
	 */
	public static function script_data() {
		return array(
			'codes'          => array_keys( LLM_Languages::get_codes() ),
			'knownKey'       => self::COOKIE_KNOWN,
			'learningKey'    => self::COOKIE_LEARNING,
			'known'          => self::known(),
			'learning'       => self::learning(),
			'knownStored'    => '' !== self::stored_known(),
			'learningStored' => '' !== self::stored_learning(),
			'isLoggedIn'     => is_user_logged_in(),
			'cookiePath'     => COOKIEPATH ? COOKIEPATH : '/',
			'cookieMaxAge'   => (int) ( self::COOKIE_DAYS * DAY_IN_SECONDS ),
			'secure'         => is_ssl(),
			'restoreFlag'    => self::RESTORE_FLAG,
		);
	}

	public static function enqueue_mirror_script() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'llm-visitor-lang',
			LLM_TABELLE_URL . 'assets/llm-visitor-lang.js',
			array(),
			LLM_TABELLE_VERSION,
			false
		);

		wp_localize_script( 'llm-visitor-lang', 'llmVisitorLang', self::script_data() );
	}
}
