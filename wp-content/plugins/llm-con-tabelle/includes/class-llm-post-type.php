<?php
/**
 * Custom post type storia (parallelo a LLS, dati frasi/media in tabelle).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Post_Type {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => _x( 'Storie LLM', 'post type general name', 'llm-con-tabelle' ),
			'singular_name'      => _x( 'Storia LLM', 'post type singular name', 'llm-con-tabelle' ),
			'menu_name'          => _x( 'Storie LLM', 'admin menu', 'llm-con-tabelle' ),
			'add_new'            => _x( 'Aggiungi nuova', 'storia', 'llm-con-tabelle' ),
			'add_new_item'       => __( 'Aggiungi nuova storia', 'llm-con-tabelle' ),
			'new_item'           => __( 'Nuova storia', 'llm-con-tabelle' ),
			'edit_item'          => __( 'Modifica storia', 'llm-con-tabelle' ),
			'view_item'          => __( 'Vedi storia', 'llm-con-tabelle' ),
			'all_items'          => __( 'Tutte le storie LLM', 'llm-con-tabelle' ),
			'search_items'       => __( 'Cerca storie', 'llm-con-tabelle' ),
			'not_found'          => __( 'Nessuna storia trovata.', 'llm-con-tabelle' ),
			'not_found_in_trash' => __( 'Nessuna storia nel cestino.', 'llm-con-tabelle' ),
		);

		register_post_type(
			LLM_STORY_CPT,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'menu_icon'          => 'dashicons-book',
				'menu_position'      => 26,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => 'llm-storie',
					'with_front' => false,
				),
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => false,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
				'show_in_rest'       => true,
			)
		);
	}
}
