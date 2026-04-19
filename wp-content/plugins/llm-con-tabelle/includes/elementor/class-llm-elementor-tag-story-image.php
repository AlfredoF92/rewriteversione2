<?php
/**
 * Dynamic Tag Elementor — immagine di anteprima storia LLM.
 *
 * Estende Data_Tag (non Tag) così get_content() restituisce
 * l'array [ 'id' => ..., 'url' => ... ] che Control_Media
 * si aspetta sia per il widget Immagine che per i CSS background.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

/**
 * Tag dinamico immagine — featured image del post llm_story.
 */
class LLM_Elementor_Tag_Story_Image extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'llm-story-image-field';
	}

	public function get_title() {
		return __( 'Immagine anteprima storia LLM', 'llm-con-tabelle' );
	}

	public function get_group() {
		return array( 'llm-story' );
	}

	public function get_categories() {
		return array( DynamicTagsModule::IMAGE_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control(
			'image_size',
			array(
				'label'   => __( 'Dimensione immagine', 'llm-con-tabelle' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'thumbnail' => __( 'Miniatura', 'llm-con-tabelle' ),
					'medium'    => __( 'Media', 'llm-con-tabelle' ),
					'large'     => __( 'Grande', 'llm-con-tabelle' ),
					'full'      => __( 'Originale', 'llm-con-tabelle' ),
				),
				'default' => 'full',
			)
		);
	}

	/**
	 * Restituisce l'array immagine [ 'id', 'url' ] che Elementor
	 * usa sia nel widget Immagine (src) sia nei CSS background-image.
	 *
	 * @param array $options Opzioni (non usate).
	 * @return array
	 */
	protected function get_value( array $options = [] ) {
		$post_id  = get_the_ID();
		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		if ( ! $thumb_id ) {
			return array(
				'id'  => 0,
				'url' => '',
			);
		}

		$size = $this->get_settings( 'image_size' );
		$size = in_array( $size, array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $size : 'full';
		$url  = wp_get_attachment_image_url( $thumb_id, $size );

		return array(
			'id'  => $thumb_id,
			'url' => $url ?: '',
		);
	}
}
