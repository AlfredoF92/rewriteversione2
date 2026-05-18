<?php
/**
 * Dynamic Tag Elementor — lingua obiettivo della storia.
 *
 * Legge _llm_target_lang dal post meta della storia corrente.
 * Non dipende dalla lingua dell'utente loggato.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

class LLM_Elementor_Tag_Story_Target_Lang extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'llm-story-target-lang';
	}

	public function get_title() {
		return __( 'Storia — Lingua obiettivo (da imparare)', 'llm-con-tabelle' );
	}

	public function get_group() {
		return array( 'llm-story' );
	}

	public function get_categories() {
		return array( DynamicTagsModule::TEXT_CATEGORY );
	}

	public function render() {
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$code = sanitize_key( (string) get_post_meta( $post->ID, LLM_Story_Meta::TARGET_LANG, true ) );
		if ( '' === $code ) {
			return;
		}
		echo esc_html( LLM_Languages::label( $code ) );
	}
}
