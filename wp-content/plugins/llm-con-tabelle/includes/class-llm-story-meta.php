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
}
