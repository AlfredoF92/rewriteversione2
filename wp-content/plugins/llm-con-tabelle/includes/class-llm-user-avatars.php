<?php
/**
 * Catalogo avatar in assets/avatars — assegnazione casuale, senza galleria.
 *
 * Aggiungere altri PNG nella stessa cartella: list() li include da solo.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_User_Avatars {

	const META = '_llm_avatar';

	const OPT_BACKFILL = 'llm_avatars_backfill_v1';

	const REL_DIR = 'assets/avatars';

	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'on_register' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_backfill' ), 40 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 4 );
	}

	/**
	 * @param int $user_id ID.
	 */
	public static function on_register( $user_id ) {
		self::ensure_user( (int) $user_id );
	}

	/**
	 * Assegna un avatar a chi non ce l’ha ancora (una volta).
	 */
	public static function maybe_backfill() {
		if ( wp_doing_ajax() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return;
		}
		if ( get_option( self::OPT_BACKFILL ) ) {
			return;
		}
		if ( ! self::list() ) {
			return;
		}
		$ids = get_users(
			array(
				'fields' => 'ID',
				'number' => 0,
			)
		);
		foreach ( (array) $ids as $id ) {
			self::ensure_user( (int) $id );
		}
		update_option( self::OPT_BACKFILL, '1', false );
	}

	/**
	 * @return string
	 */
	public static function dir() {
		return trailingslashit( LLM_TABELLE_DIR ) . self::REL_DIR;
	}

	/**
	 * @return string
	 */
	public static function url_base() {
		return trailingslashit( LLM_TABELLE_URL ) . self::REL_DIR;
	}

	/**
	 * @return string[] Nomi file PNG.
	 */
	public static function list() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			$cache = array();
			return $cache;
		}
		$paths = glob( $dir . '/*.png' );
		$files = array();
		if ( is_array( $paths ) ) {
			foreach ( $paths as $path ) {
				$name = self::sanitize( basename( (string) $path ) );
				if ( $name ) {
					$files[] = $name;
				}
			}
		}
		sort( $files, SORT_STRING );
		$cache = array_values( array_unique( $files ) );
		return $cache;
	}

	/**
	 * @param string $file Nome file.
	 * @return string
	 */
	public static function sanitize( $file ) {
		$file = strtolower( basename( (string) $file ) );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]*\.png$/', $file ) ) {
			return '';
		}
		return $file;
	}

	/**
	 * @param string $file Nome file.
	 * @return bool
	 */
	public static function is_valid( $file ) {
		$file = self::sanitize( $file );
		return '' !== $file && in_array( $file, self::list(), true );
	}

	/**
	 * @param string $file Nome file.
	 * @return string
	 */
	public static function file_url( $file ) {
		$file = self::sanitize( $file );
		if ( ! self::is_valid( $file ) ) {
			return '';
		}
		$ver = defined( 'LLM_TABELLE_VERSION' ) ? LLM_TABELLE_VERSION : '1';
		return trailingslashit( self::url_base() ) . rawurlencode( $file ) . '?v=' . rawurlencode( $ver );
	}

	/**
	 * @param string $exclude File da evitare.
	 * @return string
	 */
	public static function random( $exclude = '' ) {
		$files = self::list();
		if ( ! $files ) {
			return '';
		}
		$exclude = self::sanitize( $exclude );
		$pool    = array();
		foreach ( $files as $file ) {
			if ( $file !== $exclude ) {
				$pool[] = $file;
			}
		}
		if ( ! $pool ) {
			$pool = $files;
		}
		return $pool[ wp_rand( 0, count( $pool ) - 1 ) ];
	}

	/**
	 * @param int $user_id ID.
	 * @return string
	 */
	public static function ensure_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return '';
		}
		$current = self::sanitize( (string) get_user_meta( $user_id, self::META, true ) );
		if ( self::is_valid( $current ) ) {
			return $current;
		}
		$file = self::random();
		if ( $file ) {
			update_user_meta( $user_id, self::META, $file );
		}
		return $file;
	}

	/**
	 * @param int    $user_id ID.
	 * @param string $file    Nome file.
	 * @return string
	 */
	public static function set_user( $user_id, $file ) {
		$user_id = (int) $user_id;
		$file    = self::sanitize( $file );
		if ( $user_id <= 0 || ! self::is_valid( $file ) ) {
			return '';
		}
		update_user_meta( $user_id, self::META, $file );
		return $file;
	}

	/**
	 * @param int $user_id ID.
	 * @return string
	 */
	public static function user_file( $user_id ) {
		return self::ensure_user( $user_id );
	}

	/**
	 * @param int $user_id ID.
	 * @return string
	 */
	public static function user_url( $user_id ) {
		$file = self::ensure_user( $user_id );
		return $file ? self::file_url( $file ) : '';
	}

	/**
	 * Dati per JS (ospite + Cambia Avatar).
	 *
	 * @return array{files:string[],baseUrl:string,version:string}
	 */
	public static function frontend_data() {
		return array(
			'files'   => self::list(),
			'baseUrl' => trailingslashit( self::url_base() ),
			'version' => defined( 'LLM_TABELLE_VERSION' ) ? LLM_TABELLE_VERSION : '1',
		);
	}

	public static function enqueue() {
		wp_enqueue_script(
			'llm-guest-browser-store',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-store.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_enqueue_script(
			'llm-user-avatars',
			LLM_TABELLE_URL . 'assets/llm-user-avatars.js',
			array( 'llm-guest-browser-store' ),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-user-avatars',
			'llmUserAvatars',
			self::frontend_data()
		);
	}
}
