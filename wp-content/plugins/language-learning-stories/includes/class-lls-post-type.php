<?php
/**
 * Custom post type Storia.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Post_Type {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'                  => _x( 'Storie', 'post type general name', 'language-learning-stories' ),
			'singular_name'         => _x( 'Storia', 'post type singular name', 'language-learning-stories' ),
			'menu_name'             => _x( 'Storie', 'admin menu', 'language-learning-stories' ),
			'name_admin_bar'        => _x( 'Storia', 'add new on admin bar', 'language-learning-stories' ),
			'add_new'               => _x( 'Aggiungi nuova', 'storia', 'language-learning-stories' ),
			'add_new_item'          => __( 'Aggiungi nuova storia', 'language-learning-stories' ),
			'new_item'              => __( 'Nuova storia', 'language-learning-stories' ),
			'edit_item'             => __( 'Modifica storia', 'language-learning-stories' ),
			'view_item'             => __( 'Vedi storia', 'language-learning-stories' ),
			'all_items'             => __( 'Tutte le storie', 'language-learning-stories' ),
			'search_items'          => __( 'Cerca storie', 'language-learning-stories' ),
			'not_found'             => __( 'Nessuna storia trovata.', 'language-learning-stories' ),
			'not_found_in_trash'    => __( 'Nessuna storia nel cestino.', 'language-learning-stories' ),
			'featured_image'        => __( 'Immagine in evidenza', 'language-learning-stories' ),
			'set_featured_image'    => __( 'Imposta immagine in evidenza', 'language-learning-stories' ),
			'remove_featured_image' => __( 'Rimuovi immagine in evidenza', 'language-learning-stories' ),
			'use_featured_image'    => __( 'Usa come immagine in evidenza', 'language-learning-stories' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-book-alt',
			'menu_position'      => 5,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'storie',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments' ),
			'show_in_rest'       => true,
		);

		register_post_type( LLS_CPT, $args );
	}
}
