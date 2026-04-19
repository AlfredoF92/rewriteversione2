<?php
/**
 * Meta della storia: registrazione, sanitizzazione, lettura.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Story_Meta {

	const KNOWN_LANG       = '_lls_known_lang';
	const TARGET_LANG      = '_lls_target_lang';
	const TITLE_TARGET     = '_lls_title_target_lang';
	const PHRASES          = '_lls_phrases';
	const MEDIA_BLOCKS     = '_lls_media_blocks';
	const COIN_COST        = '_lls_story_coin_cost';
	const COIN_REWARD      = '_lls_story_coin_reward';
	const STORY_PLOT       = '_lls_story_plot';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	public static function register_meta() {
		$post_type = LLS_CPT;

		register_post_meta(
			$post_type,
			self::KNOWN_LANG,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_lang_code' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);

		register_post_meta(
			$post_type,
			self::TARGET_LANG,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_lang_code' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);

		register_post_meta(
			$post_type,
			self::TITLE_TARGET,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);

		register_post_meta(
			$post_type,
			self::PHRASES,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_phrases_meta' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);

		register_post_meta(
			$post_type,
			self::MEDIA_BLOCKS,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_media_blocks_meta' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);

		register_post_meta(
			$post_type,
			self::COIN_COST,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_coin_int' ),
				'default'           => 0,
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);

		register_post_meta(
			$post_type,
			self::COIN_REWARD,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_coin_int' ),
				'default'           => 0,
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);

		register_post_meta(
			$post_type,
			self::STORY_PLOT,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_plot' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_meta' ),
			)
		);
	}

	/**
	 * @param mixed $value Valore.
	 * @return string
	 */
	public static function sanitize_plot( $value ) {
		return is_string( $value ) ? sanitize_textarea_field( wp_unslash( $value ) ) : '';
	}

	/**
	 * @return bool
	 */
	public static function auth_meta() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param string $code Valore grezzo.
	 * @return string Codice valido o stringa vuota.
	 */
	public static function sanitize_lang_code( $code ) {
		$code = is_string( $code ) ? sanitize_key( $code ) : '';
		return LLS_Languages::is_valid( $code ) ? $code : '';
	}

	/**
	 * @param mixed $value Valore (da meta API può essere già decodificato se non usiamo JSON).
	 * @return string JSON array di frasi.
	 */
	public static function sanitize_phrases_meta( $value ) {
		$phrases = self::sanitize_phrases_array( $value );
		return wp_json_encode( $phrases );
	}

	/**
	 * @param mixed $value Input (array o JSON string).
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize_phrases_array( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( wp_unslash( $value ), true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'interface' => isset( $row['interface'] ) ? sanitize_textarea_field( wp_unslash( $row['interface'] ) ) : '',
				'target'    => isset( $row['target'] ) ? sanitize_textarea_field( wp_unslash( $row['target'] ) ) : '',
				'grammar'   => isset( $row['grammar'] ) ? sanitize_textarea_field( wp_unslash( $row['grammar'] ) ) : '',
				'alt'       => isset( $row['alt'] ) ? sanitize_textarea_field( wp_unslash( $row['alt'] ) ) : '',
			);
		}
		return $out;
	}

	/**
	 * @param mixed $value Input.
	 * @return string JSON.
	 */
	public static function sanitize_media_blocks_meta( $value ) {
		$blocks = self::sanitize_media_blocks_array( $value );
		return wp_json_encode( $blocks );
	}

	/**
	 * @param mixed $value Input (array o JSON).
	 * @return array<int, array{attachment_id:int, after_phrase_index:int}>
	 */
	public static function sanitize_media_blocks_array( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( wp_unslash( $value ), true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$aid   = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$after = isset( $row['after_phrase_index'] ) ? intval( $row['after_phrase_index'] ) : -1;
			if ( $aid && wp_attachment_is_image( $aid ) ) {
				$out[] = array(
					'attachment_id'       => $aid,
					'after_phrase_index'  => max( -1, $after ),
				);
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Valore.
	 * @return int
	 */
	public static function sanitize_coin_int( $value ) {
		$n = is_numeric( $value ) ? (int) $value : 0;
		return max( 0, $n );
	}

	/**
	 * @param int $post_id ID post.
	 * @return array<int, array<string, string>>
	 */
	public static function get_phrases( $post_id ) {
		$raw = get_post_meta( $post_id, self::PHRASES, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param int $post_id ID post.
	 * @return array<int, array{attachment_id:int, after_phrase_index:int}>
	 */
	public static function get_media_blocks( $post_id ) {
		$raw = get_post_meta( $post_id, self::MEDIA_BLOCKS, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $raw ) ? $raw : array();
	}
}
