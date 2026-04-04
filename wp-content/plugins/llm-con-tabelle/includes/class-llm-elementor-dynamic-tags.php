<?php
/**
 * Registrazione Dynamic Tags Elementor per il CPT llm_story.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Elementor_Dynamic_Tags {

	public static function init() {
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_tags' ) );
	}

	/**
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Manager.
	 */
	public static function register_tags( $dynamic_tags_manager ) {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
			return;
		}

		$dynamic_tags_manager->register_group(
			'llm-story',
			array(
				'title' => __( 'LLM Storia', 'llm-con-tabelle' ),
			)
		);

		require_once LLM_TABELLE_DIR . 'includes/elementor/class-llm-elementor-tag-story-text.php';
		require_once LLM_TABELLE_DIR . 'includes/elementor/class-llm-elementor-tag-story-url.php';

		$dynamic_tags_manager->register( new LLM_Elementor_Tag_Story_Text() );
		$dynamic_tags_manager->register( new LLM_Elementor_Tag_Story_Url() );
	}
}
