<?php
/**
 * Dynamic Tag Elementor — campi testo storia LLM.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

/**
 * Tag dinamico testo (trama, lingue, coin, titolo, contenuto…).
 */
class LLM_Elementor_Tag_Story_Text extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'llm-story-text-field';
	}

	public function get_title() {
		return __( 'Campo testuale storia LLM', 'llm-con-tabelle' );
	}

	public function get_group() {
		return array( 'llm-story' );
	}

	public function get_categories() {
		return array( DynamicTagsModule::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$options = array();
		foreach ( LLM_Story_Template_Vars::get_text_field_options() as $label => $key ) {
			$options[ $key ] = $label;
		}

		$this->add_control(
			'field_key',
			array(
				'label'   => __( 'Campo', 'llm-con-tabelle' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $options,
				'default' => 'story_plot',
			)
		);
	}

	public function render() {
		$key = $this->get_settings( 'field_key' );
		$key = is_string( $key ) ? sanitize_key( $key ) : '';
		if ( '' === $key ) {
			return;
		}

		$val = LLM_Story_Template_Vars::get_text_value( $key, null );

		if ( 'post_content' === $key ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenuto filtrato come the_content.
			echo $val;
			return;
		}

		if ( 'story_plot' === $key ) {
			echo wp_kses_post( nl2br( esc_html( $val ) ) );
			return;
		}

		echo esc_html( $val );
	}
}
