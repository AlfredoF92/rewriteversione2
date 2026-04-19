<?php
/**
 * Dynamic Tag Elementor — URL storia LLM.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

/**
 * Tag dinamico URL (permalink, immagine in evidenza).
 */
class LLM_Elementor_Tag_Story_Url extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'llm-story-url-field';
	}

	public function get_title() {
		return __( 'URL storia LLM', 'llm-con-tabelle' );
	}

	public function get_group() {
		return array( 'llm-story' );
	}

	public function get_categories() {
		return array( DynamicTagsModule::URL_CATEGORY );
	}

	protected function register_controls() {
		$options = array();
		foreach ( LLM_Story_Template_Vars::get_url_field_options() as $label => $key ) {
			$options[ $key ] = $label;
		}

		$this->add_control(
			'field_key',
			array(
				'label'   => __( 'Campo', 'llm-con-tabelle' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $options,
				'default' => 'permalink',
			)
		);
	}

	public function render() {
		$key = $this->get_settings( 'field_key' );
		$key = is_string( $key ) ? sanitize_key( $key ) : '';
		if ( '' === $key ) {
			return;
		}

		echo esc_url( LLM_Story_Template_Vars::get_url_value( $key, null ) );
	}
}
