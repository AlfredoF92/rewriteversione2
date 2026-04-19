<?php
/**
 * CPT attività community (feed interno, non pubblico).
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Activity_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'          => _x( 'Attività LLS', 'post type general name', 'language-learning-stories' ),
			'singular_name' => _x( 'Attività', 'post type singular name', 'language-learning-stories' ),
		);

		register_post_type(
			LLS_ACTIVITY_CPT,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'author' ),
			)
		);
	}
}
