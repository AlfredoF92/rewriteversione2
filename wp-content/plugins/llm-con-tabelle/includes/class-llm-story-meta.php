<?php
/**
 * Meta scalari della storia (post meta, non JSON).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Meta {

	const KNOWN_LANG   = '_llm_known_lang';
	const TARGET_LANG  = '_llm_target_lang';
	const TITLE_TARGET = '_llm_title_target_lang';
	const COIN_COST    = '_llm_story_coin_cost';
	const COIN_REWARD  = '_llm_story_coin_reward';
	const STORY_PLOT           = '_llm_story_plot';
	const STORY_INTRO          = '_llm_story_intro';
	const STORY_FINALE         = '_llm_story_finale';
	const STORY_CARD_TEXT      = '_llm_story_card_text';
	const STORY_CEFR_LEVEL     = '_llm_story_cefr_level';
	const STORY_GRAMMAR_TOPICS = '_llm_story_grammar_topics';
	/** ID allegato dell'infografica mostrata nel popup dei topic. */
	const GRAMMAR_INFOGRAPHIC  = '_llm_story_grammar_infographic';
	/** JSON array di {label,url}, max SOURCES_MAX. */
	const STORY_SOURCES        = '_llm_story_sources';
	const SOURCES_MAX          = 3;
	/** yes | no | none — calcolato dal pulsante in lista storie. */
	const PHRASE_NOTES_STATUS    = '_llm_phrase_notes_status';
	const PHRASE_NOTES_AVG_WORDS = '_llm_phrase_notes_avg_words';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 11 );
	}

	public static function register_meta() {
		$pt = LLM_STORY_CPT;

		$scalar_string = array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
		);

		register_post_meta( $pt, self::KNOWN_LANG, array_merge( $scalar_string, array( 'sanitize_callback' => 'sanitize_key' ) ) );
		register_post_meta( $pt, self::TARGET_LANG, array_merge( $scalar_string, array( 'sanitize_callback' => 'sanitize_key' ) ) );
		register_post_meta( $pt, self::TITLE_TARGET, array_merge( $scalar_string, array( 'sanitize_callback' => 'sanitize_text_field' ) ) );
		register_post_meta( $pt, self::STORY_PLOT, array_merge( $scalar_string, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_plot' ) ) ) );
		register_post_meta( $pt, self::STORY_INTRO, array_merge( $scalar_string, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_plot' ) ) ) );
		register_post_meta( $pt, self::STORY_FINALE, array_merge( $scalar_string, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_plot' ) ) ) );
		register_post_meta( $pt, self::STORY_CARD_TEXT, array_merge( $scalar_string, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_plot' ) ) ) );
		register_post_meta( $pt, self::STORY_CEFR_LEVEL, array_merge( $scalar_string, array( 'sanitize_callback' => 'sanitize_text_field' ) ) );
		register_post_meta( $pt, self::STORY_GRAMMAR_TOPICS, array_merge( $scalar_string, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_plot' ) ) ) );
		register_post_meta(
			$pt,
			self::GRAMMAR_INFOGRAPHIC,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);
		register_post_meta(
			$pt,
			self::STORY_SOURCES,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_sources_meta' ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_post_meta(
			$pt,
			self::COIN_COST,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_coin' ),
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);
		register_post_meta(
			$pt,
			self::COIN_REWARD,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_coin' ),
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);
	}

	public static function sanitize_plot( $value ) {
		return is_string( $value ) ? sanitize_textarea_field( wp_unslash( $value ) ) : '';
	}

	public static function sanitize_coin( $value ) {
		$n = is_numeric( $value ) ? (int) $value : 0;
		return max( 0, $n );
	}

	/**
	 * Sanitize raw list of sources for storage (JSON string).
	 *
	 * @param mixed $value Array o JSON string.
	 * @return string JSON (può essere '[]').
	 */
	public static function sanitize_sources_meta( $value ) {
		$list = self::normalize_sources( $value );
		return wp_json_encode( $list );
	}

	/**
	 * @param mixed $value Array, JSON string, o vuoto.
	 * @return array<int,array{label:string,url:string}>
	 */
	public static function normalize_sources( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( wp_unslash( $value ) );
			if ( '' === $value ) {
				return array();
			}
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $row ) {
			if ( count( $out ) >= self::SOURCES_MAX ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( (string) $row['url'] ) ) : '';
			if ( '' === $label || '' === $url ) {
				continue;
			}
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}
			$out[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
		return $out;
	}

	/**
	 * @param int $story_id ID storia.
	 * @return array<int,array{label:string,url:string}>
	 */
	public static function get_sources( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}
		$raw = get_post_meta( $story_id, self::STORY_SOURCES, true );
		return self::normalize_sources( $raw );
	}

	/**
	 * @param int                                $story_id ID.
	 * @param array<int,array{label?:string,url?:string}> $sources Fonti.
	 * @return void
	 */
	public static function set_sources( $story_id, $sources ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}
		$list = self::normalize_sources( $sources );
		if ( empty( $list ) ) {
			delete_post_meta( $story_id, self::STORY_SOURCES );
			return;
		}
		update_post_meta( $story_id, self::STORY_SOURCES, wp_json_encode( $list ) );
	}
}
