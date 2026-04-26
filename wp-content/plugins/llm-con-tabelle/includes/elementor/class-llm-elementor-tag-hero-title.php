<?php
/**
 * Dynamic Tag Elementor — Hero Titolo con animazione typewriter.
 *
 * Emette uno <span> con classe llm-hero-typewriter e data-text; il JS in
 * llm-hero.js anima i caratteri uno ad uno al caricamento della pagina.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

class LLM_Elementor_Tag_Hero_Title extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'llm-hero-title';
	}

	public function get_title() {
		return __( 'LLM Hero — Titolo (typewriter)', 'llm-con-tabelle' );
	}

	public function get_group() {
		return array( 'llm-hero' );
	}

	public function get_categories() {
		return array( DynamicTagsModule::TEXT_CATEGORY );
	}

	public function render() {
		$text = LLM_Hero_Translations::get_text( 'title' );
		// Il testo è visibile subito (accessibilità + no-JS); il JS lo anima.
		printf(
			'<span class="llm-hero-typewriter" data-text="%s" aria-label="%s">%s</span>',
			esc_attr( $text ),
			esc_attr( $text ),
			esc_html( $text )
		);
	}
}
