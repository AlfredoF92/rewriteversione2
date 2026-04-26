<?php
/**
 * Dynamic Tag Elementor — Hero Sottotitolo.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

class LLM_Elementor_Tag_Hero_Subtitle extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'llm-hero-subtitle';
	}

	public function get_title() {
		return __( 'LLM Hero — Sottotitolo', 'llm-con-tabelle' );
	}

	public function get_group() {
		return array( 'llm-hero' );
	}

	public function get_categories() {
		return array( DynamicTagsModule::TEXT_CATEGORY );
	}

	public function render() {
		echo esc_html( LLM_Hero_Translations::get_text( 'subtitle' ) );
	}
}
