<?php
/**
 * CPT attività community (feed interno).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Activity_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'          => _x( 'Attività LLM', 'post type general name', 'llm-con-tabelle' ),
			'singular_name' => _x( 'Attività LLM', 'post type singular name', 'llm-con-tabelle' ),
		);

		register_post_type(
			LLM_ACTIVITY_CPT,
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'query_var'          => false,
				'rewrite'            => false,
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'hierarchical'       => false,
				'supports'           => array( 'title', 'author' ),
			)
		);
	}
}
